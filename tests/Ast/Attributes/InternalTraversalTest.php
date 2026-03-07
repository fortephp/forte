<?php

declare(strict_types=1);

use Forte\Ast\EchoNode;

describe('Attribute Internal Traversal', function (): void {
    it('collects internal echoes from compound and directive attributes', function (): void {
        $template = <<<'BLADE'
<div data-{{ $key }}="x-{{ $value }}" @if($ok)aria-{{ $k2 }}="{{ $v2 }}"@endif></div>
BLADE;

        $element = $this->parseElement($template);
        $attributes = $element->attributes()->all();

        expect($attributes)->toHaveCount(2);

        $compoundEchoes = $attributes[0]->internalEchoes();
        $compound = [];
        foreach ($compoundEchoes as $echo) {
            $compound[] = $echo->expression();
        }

        $directiveEchoes = $attributes[1]->internalEchoes();
        $directive = [];
        foreach ($directiveEchoes as $echo) {
            $directive[] = $echo->expression();
        }

        sort($directive);

        $all = $element->attributes()->internalEchoes()
            ->map(fn (EchoNode $echo): string => $echo->expression())
            ->all();
        sort($all);

        expect($compound)->toBe(['$key', '$value'])
            ->and($directive)->toBe(['$k2', '$v2'])
            ->and($all)->toBe(['$k2', '$key', '$v2', '$value']);
    });
});
