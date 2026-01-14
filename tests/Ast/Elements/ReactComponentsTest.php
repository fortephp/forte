<?php

declare(strict_types=1);

describe('React Components - Generic Type Arguments', function (): void {
    it('parses component with generic type argument block and attributes', function (): void {
        $html = '<Table<{ id: number }> items="data"></Table>';

        $el = $this->parseElement($html);

        expect($el)->not()->toBeNull()
            ->and($el->tagNameText())->toBe('Table')
            ->and($el->genericTypeArguments())->toBe('<{ id: number }>')
            ->and($el->isPaired())->toBeTrue();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1)
            ->and($attrs[0]->nameText())->toBe('items')
            ->and($attrs[0]->valueText())->toBe('data');
    });

    it('parses self-closing component with generic type arguments', function (): void {
        $html = '<List<User> items="data" />';

        $el = $this->parseElement($html);

        expect($el)->not()->toBeNull()
            ->and($el->tagNameText())->toBe('List')
            ->and($el->genericTypeArguments())->toBe('<User>')
            ->and($el->isSelfClosing())->toBeTrue();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1)
            ->and($attrs[0]->nameText())->toBe('items')
            ->and($attrs[0]->valueText())->toBe('data');
    });

    it('parses nested generic arguments', function (): void {
        $html = '<Map<Record<string, Array<Foo>>>></Map>';

        $el = $this->parseElement($html);

        expect($el)->not()->toBeNull()
            ->and($el->tagNameText())->toBe('Map')
            ->and($el->genericTypeArguments())->toBe('<Record<string, Array<Foo>>>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('preserves source offsets for generic type arguments', function (): void {
        $template = '<Map<Record<string, number>>> data="ok"></Map>';

        $el = $this->parseElement($template);

        expect($el)->not()->toBeNull();

        $rendered = $el->render();
        expect($rendered)->toBe($template);
    });

    it('parses component with generic type argument block and multiline attributes', function (): void {
        $html = <<<'HTML'
<Table<{ id: number }>
      items="[{ id: \"1\", name: \"Matt\" }]"
      renderItem="(item) => <div>{item.id}</div>"
    ></Table>
HTML;

        $el = $this->parseElement($html);

        expect($el->tagNameText())->toBe('Table')
            ->and($el->genericTypeArguments())->toBe('<{ id: number }>')
            ->and($el->isPaired())->toBeTrue();

        $doc = $el->getDocumentContent();
        expect($doc)->toContain('items="[{ id: \"1\", name: \"Matt\" }]"')
            ->and($doc)->toContain('renderItem="(item) => <div>{item.id}</div>"');
    });
});
