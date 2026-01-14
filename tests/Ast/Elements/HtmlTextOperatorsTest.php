<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('HTML text with comparison operators', function (): void {
    test('paragraph text preserves > and < around variables', function (): void {
        $template = '<p>The condition checks if $x > 5 or $y<3.</p>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template);

        $p = $nodes[0]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->tagNameText())->toBe('p')
            ->and($p->startLine())->toBe(1)
            ->and($p->endLine())->toBe(1)
            ->and($p->startOffset())->toBe(0)
            ->and($p->endOffset())->toBe(46)
            ->and($p->getDocumentContent())->toBe('<p>The condition checks if $x > 5 or $y<3.</p>')
            ->and($p->isPaired())->toBeTrue()
            ->and($p->isSelfClosing())->toBeFalse();

        $pChildren = $p->getChildren();
        expect($pChildren)->toHaveCount(1);

        $textNode = $pChildren[0]->asText();
        expect($textNode)->toBeInstanceOf(TextNode::class)
            ->and($textNode->getContent())->toBe('The condition checks if $x > 5 or $y<3.')
            ->and($textNode->getDocumentContent())->toBe('The condition checks if $x > 5 or $y<3.')
            ->and($textNode->startLine())->toBe(1)
            ->and($textNode->endLine())->toBe(1)
            ->and($textNode->startOffset())->toBe(3)
            ->and($textNode->endOffset())->toBe(42)
            ->and($textNode->getContent())
            ->toContain('$x > 5')
            ->and($textNode->getContent())->toContain('$y<3')
            ->and($textNode->getContent())->toContain(' > ')
            ->and($textNode->getContent())->toContain('<3')
            ->and($p->attributes()->all())->toHaveCount(0)
            ->and($doc->render())->toBe($template)
            ->and($p->render())->toBe('<p>The condition checks if $x > 5 or $y<3.</p>')
            ->and($textNode->render())->toBe('The condition checks if $x > 5 or $y<3.');

    });

    test('multiple operators and variants remain text', function (): void {
        $template = '<p>Compute: 1 < 2 && 3 > 4, also a<=b and c>=d, finally x<10 and y>20.</p>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template);

        $p = $nodes[0]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->tagNameText())->toBe('p')
            ->and($p->startLine())->toBe(1)
            ->and($p->endLine())->toBe(1)
            ->and($p->startOffset())->toBe(0)
            ->and($p->endOffset())->toBe(74)
            ->and($p->getDocumentContent())->toBe('<p>Compute: 1 < 2 && 3 > 4, also a<=b and c>=d, finally x<10 and y>20.</p>')
            ->and($p->isPaired())->toBeTrue()
            ->and($p->isSelfClosing())->toBeFalse();

        $pChildren = $p->getChildren();
        expect($pChildren)->toHaveCount(1);

        $textNode = $pChildren[0]->asText();
        expect($textNode)->toBeInstanceOf(TextNode::class)
            ->and($textNode->getContent())->toBe('Compute: 1 < 2 && 3 > 4, also a<=b and c>=d, finally x<10 and y>20.')
            ->and($textNode->getDocumentContent())->toBe('Compute: 1 < 2 && 3 > 4, also a<=b and c>=d, finally x<10 and y>20.')
            ->and($textNode->startLine())->toBe(1)
            ->and($textNode->endLine())->toBe(1)
            ->and($textNode->startOffset())->toBe(3)
            ->and($textNode->endOffset())->toBe(70)
            ->and($textNode->getContent())
            ->toContain('1 < 2')
            ->and($textNode->getContent())->toContain('3 > 4')
            ->and($textNode->getContent())->toContain('a<=b')
            ->and($textNode->getContent())->toContain('c>=d')
            ->and($textNode->getContent())->toContain('x<10')
            ->and($textNode->getContent())->toContain('y>20')
            ->and($textNode->getContent())
            ->toContain(' < ')
            ->and($textNode->getContent())->toContain(' > ')
            ->and($textNode->getContent())->toContain('<=')
            ->and($textNode->getContent())->toContain('>=')
            ->and($textNode->getContent())->toContain('<10')
            ->and($textNode->getContent())->toContain('>20')
            ->and($textNode->getContent())
            ->toContain('&&')
            ->and($textNode->getContent())->toContain(' and ')
            ->and($p->attributes()->all())->toHaveCount(0)
            ->and($doc->render())->toBe($template)
            ->and($p->render())->toBe('<p>Compute: 1 < 2 && 3 > 4, also a<=b and c>=d, finally x<10 and y>20.</p>')
            ->and($textNode->render())->toBe('Compute: 1 < 2 && 3 > 4, also a<=b and c>=d, finally x<10 and y>20.')
            ->and($textNode->getContent())->toBe('Compute: 1 < 2 && 3 > 4, also a<=b and c>=d, finally x<10 and y>20.');
    });
});
