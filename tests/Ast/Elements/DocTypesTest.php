<?php

declare(strict_types=1);

use Forte\Ast\DoctypeNode;
use Forte\Ast\Document\Document;

describe('DOCTYPE Parsing', function (): void {
    dataset('doctypes', [
        'HTML5' => [
            'doctype' => '<!DOCTYPE html>',
            'isHtml5' => true,
            'isXhtml' => false,
            'type' => 'html',
        ],
        'HTML5 self-closing' => [
            'doctype' => '<!DOCTYPE html />',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'html',
        ],
        'HTML 4.01 Strict' => [
            'doctype' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'html',
        ],
        'HTML 4.01 Transitional' => [
            'doctype' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'html',
        ],
        'HTML 4.01 Frameset' => [
            'doctype' => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'html',
        ],
        'XHTML 1.0 Strict' => [
            'doctype' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
            'isHtml5' => false,
            'isXhtml' => true,
            'type' => 'html',
        ],
        'XHTML 1.0 Transitional' => [
            'doctype' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
            'isHtml5' => false,
            'isXhtml' => true,
            'type' => 'html',
        ],
        'XHTML 1.0 Frameset' => [
            'doctype' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
            'isHtml5' => false,
            'isXhtml' => true,
            'type' => 'html',
        ],
        'XHTML 1.1' => [
            'doctype' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
            'isHtml5' => false,
            'isXhtml' => true,
            'type' => 'html',
        ],
        'SVG 1.1' => [
            'doctype' => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'unknown',
        ],
        'SVG 1.0' => [
            'doctype' => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.0//EN" "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'unknown',
        ],
        'MathML' => [
            'doctype' => '<!DOCTYPE math SYSTEM "http://www.w3.org/Math/DTD/mathml2/mathml2.dtd">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'unknown',
        ],
        'HTML 3.2' => [
            'doctype' => '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'html',
        ],
        'Legacy IETF HTML' => [
            'doctype' => '<!DOCTYPE html PUBLIC "-//IETF//DTD HTML//EN">',
            'isHtml5' => false,
            'isXhtml' => false,
            'type' => 'html',
        ],
    ]);

    test('parses and renders doctype correctly', function (string $doctype, bool $isHtml5, bool $isXhtml, string $type): void {
        $doc = Document::parse($doctype);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DoctypeNode::class);

        /** @var DoctypeNode $doctypeNode */
        $doctypeNode = $children[0];

        expect($doctypeNode->render())->toBe($doctype)
            ->and($doctypeNode->isHtml5())->toBe($isHtml5)
            ->and($doctypeNode->isXhtml())->toBe($isXhtml)
            ->and($doctypeNode->type())->toBe($type);
    })->with('doctypes');

    it('preserves original casing and spacing when rendering doctype', function (): void {
        $template = '<!DocType html>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DoctypeNode::class)
            ->and($nodes[0]->render())->toBe($template)
            ->and($doc->render())->toBe($template);
    });

    it('preserves original casing when doctype is unclosed', function (): void {
        $template = '<!DocType svg PUBLIC';
        $doc = $this->parse($template);
        $docType = $doc->firstChild()->asDoctype();

        expect($docType)->toBeInstanceOf(DoctypeNode::class)
            ->and($docType->render())->toBe($template)
            ->and($doc->render())->toBe($template);
    });
});
