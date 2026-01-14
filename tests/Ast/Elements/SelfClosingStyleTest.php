<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;

describe('Self-Closing Style Detection', function (): void {
    it('detects no-space self-closing style', function (): void {
        $element = $this->parseElement('<br/>');

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeTrue()
            ->and($element->selfClosingStyle())->toBe('/');
    });

    it('detects space self-closing style', function (): void {
        $element = $this->parseElement('<br />');

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeTrue()
            ->and($element->selfClosingStyle())->toBe(' /');
    });

    it('returns null for void element without explicit slash', function (): void {
        $element = $this->parseElement('<br>');

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeFalse()
            ->and($element->selfClosingStyle())->toBeNull();
    });

    it('returns null for paired elements', function (): void {
        $element = $this->parseElement('<div></div>');

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isPaired())->toBeTrue()
            ->and($element->selfClosingStyle())->toBeNull();
    });

    it('detects self-closing on non-void element', function (): void {
        $element = $this->parseElement('<div />');

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeTrue()
            ->and($element->selfClosingStyle())->toBe(' /');
    });

    it('detects no-space style on img element', function (): void {
        $element = $this->parseElement('<img src="test.jpg"/>');

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeTrue()
            ->and($element->selfClosingStyle())->toBe('/');
    });

    it('detects space style on input element', function (): void {
        $element = $this->parseElement('<input type="text" />');

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeTrue()
            ->and($element->selfClosingStyle())->toBe(' /');
    });

    it('preserves self-closing style', function (string $template): void {
        expect($this->parse($template)->render())->toBe($template);
    })->with([
        '<br/>',
        '<br />',
        '<img src="test.jpg"/>',
        '<input type="text" />',
        '<div />',
    ]);
});
