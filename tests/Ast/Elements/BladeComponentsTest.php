<?php

declare(strict_types=1);

use Forte\Ast\Components\ComponentNode;

describe('Blade Components', function (): void {
    it('parses basic self-closing component', function (): void {
        $template = '<x-alert type="success" message="Saved" />';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ComponentNode::class);

        $el = $nodes[0]->asComponent();

        expect($el->tagNameText())->toBe('x-alert')
            ->and($el->isSelfClosing())->toBeTrue()
            ->and($el->getType())->toBe('blade')
            ->and($el->getPrefix())->toBe('x-')
            ->and($el->getComponentName())->toBe('alert')
            ->and($el->isSlot())->toBeFalse();

        $attributes = $el->attributes()->all();
        expect($attributes)->toHaveCount(2);

        $a0 = $attributes[0];
        expect($a0->type())->toBe('static')
            ->and($a0->nameText())->toBe('type')
            ->and($a0->quote())->toBe('"')
            ->and($a0->valueText())->toBe('success');

        $a1 = $attributes[1];
        expect($a1->nameText())->toBe('message')
            ->and($a1->valueText())->toBe('Saved');
    });

    it('parses component with tag pair and children', function (): void {
        $template = '<x-alert type="error"><span>Oops</span></x-alert>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ComponentNode::class);

        $el = $nodes[0]->asComponent();

        expect($el->tagNameText())->toBe('x-alert')
            ->and($el->isPaired())->toBeTrue()
            ->and($el->isSelfClosing())->toBeFalse()
            ->and($el->getType())->toBe('blade')
            ->and($el->getComponentName())->toBe('alert');

        $elementChildren = $el->nodes()->elements()->all();

        expect($elementChildren)->toHaveCount(1)
            ->and($elementChildren[0]->tagNameText())->toBe('span')
            ->and($elementChildren[0]->isPaired())->toBeTrue();
    });

    it('parses component with named slot using name attribute', function (): void {
        $template = '<x-card><x-slot name="title">Title</x-slot><p>Body</p></x-card>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ComponentNode::class);

        $card = $nodes[0]->asComponent();

        expect($card->tagNameText())->toBe('x-card')
            ->and($card->isPaired())->toBeTrue()
            ->and($card->getComponentName())->toBe('card');

        $elementChildren = $card->nodes()->elements()->all();

        expect($elementChildren)->toHaveCount(2)
            ->and($elementChildren[0]->asComponent())->toBeInstanceOf(ComponentNode::class)
            ->and($elementChildren[0]->asComponent()->tagNameText())->toBe('x-slot')
            ->and($elementChildren[0]->asComponent()->isSlot())->toBeTrue()
            ->and($elementChildren[1]->asElement()->tagNameText())->toBe('p');

        $slotAttrs = $elementChildren[0]->asElement()->attributes()->all();
        expect($slotAttrs)->toHaveCount(1);

        $nameAttr = $slotAttrs[0];
        expect($nameAttr->nameText())->toBe('name')
            ->and($nameAttr->quote())->toBe('"')
            ->and($nameAttr->valueText())->toBe('title');

        $slotChildren = $elementChildren[0]->getChildren();
        expect($slotChildren)->toHaveCount(1)
            ->and(trim((string) $slotChildren[0]->getDocumentContent()))->toBe('Title');
    });

    it('parses component with shorthand named slot tag', function (): void {
        $template = '<x-card><x-slot:title>Title</x-slot:title><div>Body</div></x-card>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ComponentNode::class);

        $card = $nodes[0]->asComponent();

        $elementChildren = $card->nodes()->elements()->all();

        expect($elementChildren)->toHaveCount(2)
            ->and($elementChildren[0]->asComponent())->toBeInstanceOf(ComponentNode::class)
            ->and($elementChildren[0]->asComponent()->tagNameText())->toBe('x-slot:title')
            ->and($elementChildren[0]->asComponent()->isSlot())->toBeTrue()
            ->and($elementChildren[0]->asComponent()->getSlotName())->toBe('title')
            ->and($elementChildren[1]->asElement()->tagNameText())->toBe('div');

        $slotChildren = $elementChildren[0]->getChildren();
        expect($slotChildren)->toHaveCount(1)
            ->and(trim((string) $slotChildren[0]->getDocumentContent()))->toBe('Title');
    });

    it('parses nested components', function (): void {
        $template = '<x-card><x-button>Go</x-button></x-card>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ComponentNode::class);

        $card = $nodes[0]->asComponent();

        $componentChildren = $card->getChildrenOfType(ComponentNode::class);

        expect($componentChildren)->toHaveCount(1)
            ->and($componentChildren[0]->asComponent())->toBeInstanceOf(ComponentNode::class)
            ->and($componentChildren[0]->asComponent()->tagNameText())->toBe('x-button')
            ->and($componentChildren[0]->asComponent()->getComponentName())->toBe('button');
    });

    it('parses dynamic component tag with bound component attribute', function (): void {
        $template = '<x-dynamic-component :component="$cmp" class="p-4">Body</x-dynamic-component>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ComponentNode::class);

        $dynamic = $nodes[0]->asComponent();

        expect($dynamic->tagNameText())->toBe('x-dynamic-component')
            ->and($dynamic->isPaired())->toBeTrue()
            ->and($dynamic->getComponentName())->toBe('dynamic-component');

        $attributes = $dynamic->attributes()->all();
        expect($attributes)->toHaveCount(2);

        $a0 = $attributes[0];
        expect($a0->type())->toBe('bound')
            ->and($a0->nameText())->toBe('component')
            ->and($a0->quote())->toBe('"')
            ->and($a0->valueText())->toBe('$cmp');

        $a1 = $attributes[1];
        expect($a1->nameText())->toBe('class')
            ->and($a1->valueText())->toBe('p-4');
    });

    it('parses namespaced/dotted anonymous component names', function (): void {
        $template = '<x-admin.alert/><x-admin.card>Body</x-admin.card>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(ComponentNode::class)
            ->and($nodes[1])->toBeInstanceOf(ComponentNode::class);

        $c1 = $nodes[0]->asComponent();
        expect($c1->tagNameText())->toBe('x-admin.alert')
            ->and($c1->isSelfClosing())->toBeTrue()
            ->and($c1->getComponentName())->toBe('admin.alert')
            ->and($c1->getNameParts())->toBe(['admin', 'alert']);

        $c2 = $nodes[1]->asComponent();
        expect($c2->tagNameText())->toBe('x-admin.card')
            ->and($c2->isPaired())->toBeTrue()
            ->and($c2->getComponentName())->toBe('admin.card')
            ->and($c2->getNameParts())->toBe(['admin', 'card']);
    });
});
