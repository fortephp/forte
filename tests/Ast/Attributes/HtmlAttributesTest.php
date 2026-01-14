<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\Document;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\Attribute;
use Forte\Ast\PhpTagNode;

describe('HTML Attributes', function (): void {
    it('parses static attribute', function (): void {
        $template = '<div class="mt-2"></div>';
        $el = $this->parseElement($template);

        expect($el)->not()->toBeNull();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->nameText())->toBe('class')
            ->and($attr->quote())->toBe('"')
            ->and($attr->type())->toBe('static')
            ->and($attr->valueText())->toBe('mt-2');
    });

    it('parses bound attribute', function (): void {
        $template = '<div :class="{}"></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->nameText())->toBe('class')
            ->and($attr->quote())->toBe('"')
            ->and($attr->type())->toBe('bound')
            ->and($attr->isBound())->toBeTrue()
            ->and($attr->valueText())->toBe('{}');
    });

    it('parses escaped attribute', function (): void {
        $template = '<div ::escaped="thing"></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->nameText())->toBe('escaped')
            ->and($attr->quote())->toBe('"')
            ->and($attr->type())->toBe('escaped');
    });

    it('parses boolean attribute', function (): void {
        $template = '<div data-attribute></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->nameText())->toBe('data-attribute')
            ->and($attr->quote())->toBeNull()
            ->and($attr->isBoolean())->toBeTrue()
            ->and($attr->type())->toBe('static');
    });

    it('parses standalone echo as attribute', function (): void {
        $template = '<div {{ $nameText }}></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->isBladeConstruct())->toBeTrue();

        $standaloneNode = $attr->getBladeConstruct()->asEcho();
        expect($standaloneNode)->toBeInstanceOf(EchoNode::class)
            ->and($standaloneNode->getDocumentContent())->toBe('{{ $nameText }}')
            ->and($standaloneNode->content())->toBe(' $nameText ')
            ->and($standaloneNode->echoType())->toBe('escaped');
    });

    it('renders static attributes correctly', function (): void {
        $template = '<div class="mt-2"></div>';
        $el = $this->parseElement($template);
        expect($el->render())->toBe($template);
    });

    it('renders bound attributes correctly', function (): void {
        $template = '<div :class="{}"></div>';
        $el = $this->parseElement($template);
        expect($el->render())->toBe($template);
    });

    it('renders echo attributes correctly', function (): void {
        $template = '<div {{ $attrs }}></div>';
        $el = $this->parseElement($template);
        expect($el->render())->toBe($template);
    });

    it('preserves falsey attribute values', function (): void {
        $template = '<linearGradient x1="0"></linearGradient>';
        $el = $this->parseElement($template);
        expect($el->render())->toBe($template);
    });

    it('handles multibyte characters in attribute values', function (): void {
        $template = '<input type="text" class="w-full border rounded px-3 py-2" placeholder="Search…">';
        $el = $this->parseElement($template);
        $rendered = $el->render();
        expect($rendered)->toContain('placeholder="Search…"');
    });

    it('parses boolean attributes without equals sign', function (): void {
        $template = '<input disabled required>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(2)
            ->and($attrs[0]->nameText())->toBe('disabled')
            ->and($attrs[0]->isBoolean())->toBeTrue()
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[1]->nameText())->toBe('required')
            ->and($attrs[1]->isBoolean())->toBeTrue()
            ->and($attrs[1]->type())->toBe('static');
    });

    it('parses interpolated attribute name text with echo', function (): void {
        $template = '<div data-{{ $value }}="value"></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->render())->toBe('data-{{ $value }}="value"')
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->valueText())->toBe('value');

        $nameParts = $attr->name()->getParts();
        expect($nameParts)->toHaveCount(2)
            ->and($nameParts[0])->toBe('data-')
            ->and($nameParts[1])->toBeInstanceOf(EchoNode::class);
    });

    it('parses interpolated attribute name text with multiple parts', function (): void {
        $template = '<div data-{{ $value }}-thing="{{ $thing }}"></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->render())->toBe('data-{{ $value }}-thing="{{ $thing }}"')
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->hasComplexValue())->toBeTrue();

        $nameParts = $attr->name()->getParts();
        expect($nameParts)->toHaveCount(3)
            ->and($nameParts[0])->toBe('data-')
            ->and($nameParts[1])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[2])->toBe('-thing');

        $valueParts = $attr->value()->getParts();
        expect($valueParts)->toHaveCount(1)
            ->and($valueParts[0])->toBeInstanceOf(EchoNode::class);
    });

    it('parses unquoted attribute with echo value', function (): void {
        $template = '<div data={{ $thing }}></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->render())->toBe('data={{ $thing }}')
            ->and($attr->nameText())->toBe('data')
            ->and($attr->quote())->toBeNull()
            ->and($attr->hasComplexValue())->toBeTrue();
    });

    it('parses composite echo attribute', function (): void {
        $template = '<div {{ $thing }}={{ $anotherThing }}></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->render())->toBe('{{ $thing }}={{ $anotherThing }}')
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->hasComplexValue())->toBeTrue();
    });

    it('parses complex interpolated attributes with values', function (): void {
        $template = <<<'HTML'
<div
    {{ $thing }}-{{ $thing }}-@something('here')-attribute="value"
>
    <p>Inner content.</p>
</div>
HTML;

        $el = $this->parseElement($template);
        $attrs = $el->attributes()->all();

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->quote())->toBe('"')
            ->and($attr->valueText())->toBe('value');

        $nameParts = $attr->name()->getParts();
        expect($nameParts)->toHaveCount(10)
            ->and($nameParts[0])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[1])->toBe('-')
            ->and($nameParts[2])->toBeInstanceOf(EchoNode::class);
    });

    it('parses complex interpolated attributes without values', function (): void {
        $template = <<<'HTML'
<div
    {{ $thing }}-{{ $thing }}-@something('here')-attribute
>
    <p>Inner content.</p>
</div>
HTML;

        $el = $this->parseElement($template);
        $attrs = $el->attributes()->all();

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->quote())->toBeNull()
            ->and($attr->isBoolean())->toBeTrue();
    });

    it('parses complex interpolated with known directive', function (): void {
        $template = '<div {{ $thing }}-{{ $thing }}-@if(true==true)thing-here-@endif></div>';

        $el = $this->parseElement($template);
        $attrs = $el->attributes()->all();

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->isBoolean())->toBeTrue();
    });

    it('can parse html attributes with php tags', function (): void {
        $template = <<<'BLADE'
<div
    data-<?php echo 'thing'; ?>more="that"
>
    <p>Inner content.</p>
</div>
BLADE;

        $el = $this->parseElement($template);
        $attrs = $el->attributes()->all();

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr->render())->toBe("data-<?php echo 'thing'; ?>more=\"that\"")
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->valueText())->toBe('that');
    });

    it('can parse html attributes with php echo tag', function (): void {
        $template = <<<'BLADE'
<div
    data-<?= $thing ?>more="that"
>
    <p>Inner content.</p>
</div>
BLADE;

        $el = $this->parseElement($template);
        $attrs = $el->attributes()->all();

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr->render())->toBe('data-<?= $thing ?>more="that"')
            ->and($attr->hasComplexName())->toBeTrue()
            ->and($attr->valueText())->toBe('that');
    });

    it('parses all attribute types in one element', function (): void {
        $template = <<<'HTML'
<div
    class="mt-2"
    :class="{}"
    :$bound
    :dynamic="var"
    ::escaped="thing"
    data-attribute
    {{ $nameText }}
    data-{{ $value }}="value"
    data-{{ $value }}-thing="{{ $thing }}"
    data={{ $thing }}
    {{ $thing }}={{ $anotherThing }}
>
    <p>Inner content.</p>
</div>
HTML;

        $el = $this->parseElement($template);
        $attrs = $el->attributes()->all();

        expect($attrs)->toHaveCount(11)
            ->and($attrs[0]->nameText())->toBe('class')
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->valueText())->toBe('mt-2')
            ->and($attrs[1]->nameText())->toBe('class')
            ->and($attrs[1]->type())->toBe('bound')
            ->and($attrs[1]->valueText())->toBe('{}')
            ->and($attrs[2]->nameText())->toBe('bound')
            ->and($attrs[2]->type())->toBe('shorthand')
            ->and($attrs[2]->isVariableShorthand())->toBeTrue()
            ->and($attrs[2]->isBoolean())->toBeFalse()
            ->and($attrs[3]->nameText())->toBe('dynamic')
            ->and($attrs[3]->type())->toBe('bound')
            ->and($attrs[3]->valueText())->toBe('var')
            ->and($attrs[4]->nameText())->toBe('escaped')
            ->and($attrs[4]->type())->toBe('escaped')
            ->and($attrs[4]->isEscaped())->toBeTrue()
            ->and($attrs[4]->valueText())->toBe('thing')
            ->and($attrs[5]->nameText())->toBe('data-attribute')
            ->and($attrs[5]->type())->toBe('static')
            ->and($attrs[5]->isBoolean())->toBeTrue()
            ->and($attrs[6]->isBladeConstruct())->toBeTrue()
            ->and($attrs[6]->getBladeConstruct())->toBeInstanceOf(EchoNode::class)
            ->and($attrs[7]->hasComplexName())->toBeTrue()
            ->and($attrs[7]->valueText())->toBe('value')
            ->and($attrs[8]->hasComplexName())->toBeTrue()
            ->and($attrs[8]->hasComplexValue())->toBeTrue()
            ->and($attrs[9]->nameText())->toBe('data')
            ->and($attrs[9]->hasComplexValue())->toBeTrue()
            ->and($attrs[10]->hasComplexName())->toBeTrue()
            ->and($attrs[10]->hasComplexValue())->toBeTrue();
    });

    it('handles double quotes', function (): void {
        $template = '<div class="foo"></div>';
        $el = $this->parseElement($template);
        expect($el->attributes()->all()[0]->quote())->toBe('"');
    });

    it('handles single quotes', function (): void {
        $template = "<div class='foo'></div>";
        $el = $this->parseElement($template);
        expect($el->attributes()->all()[0]->quote())->toBe("'");
    });

    it('handles unquoted values', function (): void {
        $template = '<div class=foo></div>';
        $el = $this->parseElement($template);
        expect($el->attributes()->all()[0]->quote())->toBeNull();
    });

    it('handles mixed quote styles', function (): void {
        $template = '<div class="double" id=\'single\' data=unquoted></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        expect($attrs[0]->quote())->toBe('"')
            ->and($attrs[1]->quote())->toBe("'")
            ->and($attrs[2]->quote())->toBeNull();
    });

    it('preserves unterminated quoted attribute when rendering', function (): void {
        $snippet = '<html lang="';
        $doc = Document::parse($snippet);
        expect($doc->render())->toBe($snippet);
    });

    it('preserves escaped brackets in attribute values', function (): void {
        $template = '<div onclick="handle(\'\\}\')">test</div>';
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    it('preserves safe separators in interpolated attribute names', function (): void {
        $template = <<<'BLADE'
<div {{ $a }}-_.{{ $b }}>
</div>
BLADE;

        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1)
            ->and($attrs[0]->hasComplexName())->toBeTrue();

        $nameParts = $attrs[0]->name()->getParts();
        expect($nameParts)->toHaveCount(3)
            ->and($nameParts[0])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[1])->toBe('-_.')
            ->and($nameParts[2])->toBeInstanceOf(EchoNode::class)
            ->and($el->render())->toBe($template);
    });

    it('preserves single space when > is missing', function (): void {
        $template = '<div class="card" data-id="{{ $id }}" @if($active) ';
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    it('preserves mixed whitespace when > is missing', function (): void {
        $template = "<div id=\"x\" data-a=\"1\"\t  \n";
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });
});

describe('HTML Attributes - Self-Closing Tags', function (): void {
    it('parses self-closing tags with attributes', function (): void {
        $template = '<input type="text" value="test" />';
        $el = $this->parseElement($template);

        expect($el->isSelfClosing())->toBeTrue();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(2)
            ->and($attrs[0]->nameText())->toBe('type')
            ->and($attrs[0]->valueText())->toBe('text')
            ->and($attrs[1]->nameText())->toBe('value')
            ->and($attrs[1]->valueText())->toBe('test');
    });

    it('explicit self-closing /> with spaces before closing tokens', function (): void {
        $template = '<input type="text"  />';
        $doc = $this->parse($template);
        $el = $this->parseElement($template);

        expect($doc->render())->toBe($template)
            ->and($el->isSelfClosing())->toBeTrue()
            ->and($el->attributes()->all())->toHaveCount(1);
    });

    it('provides full EchoNode AST access in attribute nameText parts', function (): void {
        $template = '<div data-{{ $key }}-suffix="value"></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $nameParts = $attr->name()->getParts();

        expect($nameParts)->toHaveCount(3)
            ->and($nameParts[0])->toBe('data-')
            ->and($nameParts[2])->toBe('-suffix');

        $echoNode = $nameParts[1]->asEcho();
        expect($echoNode)->toBeInstanceOf(EchoNode::class)
            ->and($echoNode->expression())->toBe('$key')
            ->and($echoNode->content())->toBe(' $key ')
            ->and($echoNode->isEscaped())->toBeTrue()
            ->and($echoNode->isRaw())->toBeFalse()
            ->and($echoNode->echoType())->toBe('escaped')
            ->and($echoNode->getDocumentContent())->toBe('{{ $key }}');
    });

    it('provides full EchoNode AST access in attribute value parts', function (): void {
        $template = '<div class="prefix-{{ $class }}-suffix"></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $valueParts = $attr->value()->getParts();

        expect($valueParts)->toHaveCount(3)
            ->and($valueParts[0])->toBe('prefix-')
            ->and($valueParts[2])->toBe('-suffix');

        // Full AST access on the EchoNode
        $echoNode = $valueParts[1];
        expect($echoNode)->toBeInstanceOf(EchoNode::class)
            ->and($echoNode->expression())->toBe('$class')
            ->and($echoNode->isEscaped())->toBeTrue();
    });

    it('provides full RawEchoNode AST access in attribute parts', function (): void {
        $template = '<div data-html="{!! $rawHtml !!}"></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $valueParts = $attr->value()->getParts();

        expect($valueParts)->toHaveCount(1);

        $rawEchoNode = $valueParts[0];
        expect($rawEchoNode)->toBeInstanceOf(EchoNode::class)
            ->and($rawEchoNode->expression())->toBe('$rawHtml')
            ->and($rawEchoNode->isRaw())->toBeTrue()
            ->and($rawEchoNode->isEscaped())->toBeFalse()
            ->and($rawEchoNode->echoType())->toBe('raw')
            ->and($rawEchoNode->getDocumentContent())->toBe('{!! $rawHtml !!}');
    });

    it('provides full PhpTagNode AST access in attribute parts', function (): void {
        $template = '<div data-<?php echo $var; ?>-suffix="value"></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $nameParts = $attr->name()->getParts();

        expect($nameParts)->toHaveCount(3)
            ->and($nameParts[0])->toBe('data-')
            ->and($nameParts[2])->toBe('-suffix');

        $phpNode = $nameParts[1]->asPhpTag();
        expect($phpNode)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpNode->content())->toBe(' echo $var; ')
            ->and($phpNode->getDocumentContent())->toBe('<?php echo $var; ?>');
    });

    it('provides full PhpTagNode AST access for short echo tag', function (): void {
        $template = '<div data-<?= $var ?>-more="x"></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $nameParts = $attr->name()->getParts();

        expect($nameParts)->toHaveCount(3);

        $phpNode = $nameParts[1]->asPhpTag();
        expect($phpNode)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpNode->content())->toBe(' $var ')
            ->and($phpNode->getDocumentContent())->toBe('<?= $var ?>');
    });

    it('provides DirectiveBlockNode AST directive attributes', function (): void {
        $template = '<div @if($show)class="visible"@endif></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr->isBladeConstruct())->toBeTrue();

        $node = $attr->getBladeConstruct()->asDirectiveBlock();
        expect($node)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($node->nameText())->toBe('if')
            ->and($node->arguments())->toBe('($show)')
            ->and($node->isIf())->toBeTrue()
            ->and($node->getDocumentContent())->toBe('@if($show)class="visible"@endif');

        $startDirective = $node->startDirective();
        $endDirective = $node->endDirective();

        expect($startDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($startDirective->nameText())->toBe('if')
            ->and($startDirective->arguments())->toBe('($show)')
            ->and($endDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endDirective->nameText())->toBe('endif');

        $ifChildren = $startDirective->getChildren();
        expect($ifChildren)->toHaveCount(1)
            ->and($ifChildren[0])->toBeInstanceOf(Attribute::class)
            ->and($ifChildren[0]->nameText())->toBe('class')
            ->and($ifChildren[0]->valueText())->toBe('visible');
    });

    it('provides access to multiple attribute children of directive blocks', function (): void {
        $template = '<div @if($active)class="active" data-state="on"@endif></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $node = $attr->getBladeConstruct()->asDirectiveBlock();

        expect($node)->toBeInstanceOf(DirectiveBlockNode::class);

        $fullContent = $node->getDocumentContent();
        expect($fullContent)->toBe('@if($active)class="active" data-state="on"@endif');

        $startDirective = $node->startDirective();

        $childAttrs = $startDirective->getChildrenOfType(Attribute::class);
        expect($childAttrs)->toHaveCount(2)
            ->and($childAttrs[0])->toBeInstanceOf(Attribute::class)
            ->and($childAttrs[0]->nameText())->toBe('class')
            ->and($childAttrs[0]->valueText())->toBe('active')
            ->and($childAttrs[1])->toBeInstanceOf(Attribute::class)
            ->and($childAttrs[1]->nameText())->toBe('data-state')
            ->and($childAttrs[1]->valueText())->toBe('on');
    });

    it('provides AST access for multiple echo nodes in same attribute', function (): void {
        $template = '<div data-{{ $a }}-{{ $b }}-{{ $c }}="value"></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $nameParts = $attr->name()->getParts();

        expect($nameParts)->toHaveCount(6)
            ->and($nameParts[0])->toBe('data-')
            ->and($nameParts[1])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[2])->toBe('-')
            ->and($nameParts[3])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[4])->toBe('-')
            ->and($nameParts[5])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[1]->asEcho()->expression())->toBe('$a')
            ->and($nameParts[3]->asEcho()->expression())->toBe('$b')
            ->and($nameParts[5]->asEcho()->expression())->toBe('$c');
    });

    it('provides AST for echo and directive in attribute context', function (): void {
        $template = '<div {{ $prefix }}-@if($show)visible @endif></div>';
        $el = $this->parseElement($template);

        $attrs = $el->attributes()->all();

        expect(count($attrs))->toBeGreaterThanOrEqual(1);

        $firstAttr = $attrs[0];
        expect($firstAttr->hasComplexName())->toBeTrue();

        $nameParts = $firstAttr->name()->getParts();

        expect($nameParts[0])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[0]->asEcho()->expression())->toBe('$prefix');
    });

    it('provides AST for foreach directive in attributes', function (): void {
        $template = '<div @foreach($items as $item)data-{{ $item }}@endforeach></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];
        $node = $attr->getBladeConstruct()->asDirectiveBlock();

        expect($node)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($node->nameText())->toBe('foreach')
            ->and($node->arguments())->toBe('($items as $item)')
            ->and($node->isForeach())->toBeTrue();

        $fullContent = $node->getDocumentContent();
        expect($fullContent)->toContain('{{ $item }}')
            ->and($fullContent)->toContain('@endforeach');

        $startDirective = $node->startDirective();
        expect($startDirective->nameText())->toBe('foreach');

        /** @var Attribute[] $foreachAttrs */
        $foreachAttrs = $startDirective->getChildrenOfType(Attribute::class);
        expect($foreachAttrs)->toHaveCount(1);

        $attrInForeach = $foreachAttrs[0];
        expect($attrInForeach)->toBeInstanceOf(Attribute::class)
            ->and($attrInForeach->hasComplexName())->toBeTrue();

        $nameParts = $attrInForeach->name()->getParts();
        expect($nameParts[0])->toBe('data-')
            ->and($nameParts[1])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[1]->asEcho()->expression())->toBe('$item');
    });

    it('parses complex attribute name and values', function (): void {
        $template = '<div data-{{ $key }}-suffix="prefix-{{ $value }}-end"></div>';
        $el = $this->parseElement($template);

        $attr = $el->attributes()->all()[0];

        expect($attr->hasComplexName())->toBeTrue()
            ->and($attr->hasComplexValue())->toBeTrue();

        $nameParts = $attr->name()->getParts();
        expect($nameParts)->toHaveCount(3)
            ->and($nameParts[0])->toBe('data-')
            ->and($nameParts[1])->toBeInstanceOf(EchoNode::class)
            ->and($nameParts[2])->toBe('-suffix');

        $valueParts = $attr->value()->getParts();
        expect($valueParts)->toHaveCount(3)
            ->and($valueParts[0])->toBe('prefix-')
            ->and($valueParts[1])->toBeInstanceOf(EchoNode::class)
            ->and($valueParts[2])->toBe('-end');

        $nameEcho = $nameParts[1]->asEcho();
        $valueEcho = $valueParts[1]->asEcho();

        expect($nameEcho->expression())->toBe('$key')
            ->and($nameEcho->isEscaped())->toBeTrue()
            ->and($nameEcho->getDocumentContent())->toBe('{{ $key }}')
            ->and($valueEcho->expression())->toBe('$value')
            ->and($valueEcho->isEscaped())->toBeTrue()
            ->and($valueEcho->getDocumentContent())->toBe('{{ $value }}');
    });

    it('parses simple attribute with double quotes', function (): void {
        $source = '<div class="test"></div>';
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->nameText())->toBe('class')
            ->and($attr->valueText())->toBe('test')
            ->and($attr->quote())->toBe('"');
    });

    it('parses simple attribute with single quotes', function (): void {
        $source = "<div class='test'></div>";
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr->quote())->toBe("'");
    });

    it('parses attribute with spaces before equals', function (): void {
        $source = '<div class   ="test"></div>';
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr->nameText())->toBe('class')
            ->and($attr->valueText())->toBe('test');
    });

    it('parses attribute with spaces after equals', function (): void {
        $source = '<div class=   "test"></div>';
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr->valueText())->toBe('test');
    });

    it('parses attribute with spaces on both sides of equals', function (): void {
        $source = '<div class   =   "test"></div>';
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr->nameText())->toBe('class')
            ->and($attr->valueText())->toBe('test');
    });

    it('parses boolean attribute (no value)', function (): void {
        $source = '<input disabled>';
        $el = $this->parseElement($source);

        expect($el->attributes()->has('disabled'))->toBeTrue();
        $attr = $el->attributes()->get('disabled');
        expect($attr->isBoolean())->toBeTrue()
            ->and($attr->valueText())->toBeNull();
    });

    it('parses multiple attributes', function (): void {
        $source = '<div class="test" id="main" data-value="123"></div>';
        $el = $this->parseElement($source);

        expect($el->attributes()->has('class'))->toBeTrue()
            ->and($el->attributes()->has('id'))->toBeTrue()
            ->and($el->attributes()->has('data-value'))->toBeTrue()
            ->and($el->attributes()->get('class')->valueText())->toBe('test')
            ->and($el->attributes()->get('id')->valueText())->toBe('main')
            ->and($el->attributes()->get('data-value')->valueText())->toBe('123');
    });

    it('parses attributes with hyphenated names', function (): void {
        $source = '<div data-test-id="123" aria-label="Test"></div>';
        $el = $this->parseElement($source);

        expect($el->attributes()->has('data-test-id'))->toBeTrue()
            ->and($el->attributes()->has('aria-label'))->toBeTrue();
    });

    it('parses attribute without quotes', function (): void {
        $source = '<div class=test></div>';
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr->valueText())->toBe('test')
            ->and($attr->quote())->toBeNull();
    });

    it('parses empty string attribute value', function (): void {
        $source = '<div class=""></div>';
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr->valueText())->toBe('');
    });

    it('parses bound attribute (Alpine/Vue style)', function (): void {
        $source = '<div :class="active"></div>';
        $el = $this->parseElement($source);

        $attr = $el->attributes()->get('class');
        expect($attr)->not()->toBeNull()
            ->and($attr->type())->toBe('bound')
            ->and($attr->isBound())->toBeTrue()
            ->and($attr->render())->toBe(':class="active"');
    });

    it('parses mixed boolean and valued attributes', function (): void {
        $source = '<input type="text" required disabled nameText="test">';
        $el = $this->parseElement($source);

        expect($el->attributes()->has('type'))->toBeTrue()
            ->and($el->attributes()->get('type')->isBoolean())->toBeFalse()
            ->and($el->attributes()->has('required'))->toBeTrue()
            ->and($el->attributes()->get('required')->isBoolean())->toBeTrue()
            ->and($el->attributes()->has('disabled'))->toBeTrue()
            ->and($el->attributes()->get('disabled')->isBoolean())->toBeTrue()
            ->and($el->attributes()->has('nameText'))->toBeTrue()
            ->and($el->attributes()->get('nameText')->isBoolean())->toBeFalse();
    });

    it('parses attribute with numeric value', function (): void {
        $source = '<div tabindex="0" data-count="42"></div>';
        $el = $this->parseElement($source);

        expect($el->attributes()->get('tabindex')->valueText())->toBe('0')
            ->and($el->attributes()->get('data-count')->valueText())->toBe('42');
    });

    it('parses attributes with special characters in values', function (): void {
        $source = '<div data-test="test-value_123" class="foo bar baz"></div>';
        $el = $this->parseElement($source);

        expect($el->attributes()->get('data-test')->valueText())->toBe('test-value_123')
            ->and($el->attributes()->get('class')->valueText())->toBe('foo bar baz');
    });

    it('renders static attribute back to source', function (): void {
        $el = $this->parseElement('<div x-data="{ open: false }"></div>');

        $attr = $el->attributes()->get('x-data');
        expect($attr->render())->toBe('x-data="{ open: false }"');
    });

    it('renders bound attribute with prefix', function (): void {
        $el = $this->parseElement('<div :class="classes"></div>');

        $attr = $el->attributes()->get('class');
        expect($attr->render())->toBe(':class="classes"');
    });

    it('renders boolean attribute', function (): void {
        $el = $this->parseElement('<div x-cloak></div>');

        $attr = $el->attributes()->get('x-cloak');
        expect($attr->render())->toBe('x-cloak');
    });

    it('recovers leading whitespace on attributes', function (): void {
        $el = $this->parseElement('<div   class="foo"   id="bar"></div>');

        $attrs = $el->attributes();
        $all = $attrs->all();

        expect($all[0]->nameText())->toBe('class')
            ->and($all[0]->leadingWhitespace())->toBe('   ')
            ->and($all[1]->nameText())->toBe('id')
            ->and($all[1]->leadingWhitespace())->toBe('   ');
    });

    it('recovers single space leading whitespace', function (): void {
        $el = $this->parseElement('<div class="foo" id="bar"></div>');

        $attrs = $el->attributes();
        $all = $attrs->all();

        expect($all[0]->leadingWhitespace())->toBe(' ')
            ->and($all[1]->leadingWhitespace())->toBe(' ');
    });

    it('recovers trailing whitespace before >', function (): void {
        $el = $this->parseElement('<div class="foo"   >');

        expect($el->trailingWhitespace())->toBe('   ');
    });

    it('returns empty string when no trailing whitespace', function (): void {
        $el = $this->parseElement('<div class="foo">');

        expect($el->trailingWhitespace())->toBe('');
    });

    it('recovers mixed whitespace (tabs and spaces)', function (): void {
        $el = $this->parseElement("<div\t  class=\"foo\"\t\t>");

        $attrs = $el->attributes();
        $all = $attrs->all();

        expect($all[0]->leadingWhitespace())->toBe("\t  ")
            ->and($el->trailingWhitespace())->toBe("\t\t");
    });

    it('recovers newlines in whitespace', function (): void {
        $html = "<div\n    class=\"foo\"\n    id=\"bar\"\n>";

        $el = $this->parseElement($html);
        $attrs = $el->attributes();
        $all = $attrs->all();

        expect($all[0]->leadingWhitespace())->toBe("\n    ")
            ->and($all[1]->leadingWhitespace())->toBe("\n    ")
            ->and($el->trailingWhitespace())->toBe("\n");
    });

    it('recovers whitespace with bound attributes', function (): void {
        $el = $this->parseElement('<div   :class="classes"   :id="itemId"   >');

        $attrs = $el->attributes();
        $all = $attrs->all();

        expect($all[0]->nameText())->toBe('class')
            ->and($all[0]->isBound())->toBeTrue()
            ->and($all[0]->leadingWhitespace())->toBe('   ')
            ->and($all[1]->nameText())->toBe('id')
            ->and($all[1]->isBound())->toBeTrue()
            ->and($all[1]->leadingWhitespace())->toBe('   ')
            ->and($el->trailingWhitespace())->toBe('   ');
    });

    it('reconstructs full opening tag with whitespace', function (): void {
        $el = $this->parseElement('<div   class="foo"   id="bar"   >');

        $attrs = $el->attributes();
        $reconstructed = '<div'.collect($attrs->all())->map(fn ($attr) => $attr->leadingWhitespace().$attr->render())->implode('').$attrs->trailingWhitespace().'>';

        expect($reconstructed)->toBe('<div   class="foo"   id="bar"   >');
    });

    it('handles self-closing elements with whitespace', function (): void {
        $el = $this->parseElement('<br   />');

        expect($el->isSelfClosing())->toBeTrue()
            ->and($el->trailingWhitespace())->toBe('   ');
    });

    it('handles element with no attributes', function (): void {
        $el = $this->parseElement('<div>');

        expect($el->attributes()->count())->toBe(0)
            ->and($el->trailingWhitespace())->toBe('');
    });
});
