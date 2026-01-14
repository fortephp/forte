<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Nested HTML Elements', function (): void {
    it('parses nested unordered list structure', function (): void {
        $html = <<<'HTML'
<ul id="list">
  <li>Item 1</li>
  <li>Item 2
    <ul class="inner">
      <li>Child A</li>
      <li>Child B</li>
    </ul>
  </li>
  <li>Item 3</li>
</ul>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();
        $ul = $nodes[0]->asElement();

        expect($nodes)->toHaveCount(1)
            ->and($ul)->toBeInstanceOf(ElementNode::class)
            ->and($ul->tagNameText())->toBe('ul')
            ->and($ul->isPaired())->toBeTrue();

        $children = $ul->getChildren();

        expect($children)->toHaveCount(7)
            ->and($children[1]->asElement()->tagNameText())->toBe('li')
            ->and($children[3]->asElement()->tagNameText())->toBe('li')
            ->and($children[5]->asElement()->tagNameText())->toBe('li');

        $secondLiChildren = $children[3]->getChildren();
        expect($secondLiChildren)->toHaveCount(3)
            ->and($secondLiChildren[1]->asElement()->tagNameText())->toBe('ul');
    });

    it('parses table with thead tbody tfoot', function (): void {
        $html = <<<'HTML'
<table class="data">
  <thead>
    <tr><th>H1</th><th>H2</th></tr>
  </thead>
  <tbody>
    <tr><td>A1</td><td>A2</td></tr>
    <tr><td>B1</td><td>B2</td></tr>
  </tbody>
  <tfoot>
    <tr><td>T1</td><td>T2</td></tr>
  </tfoot>
</table>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($nodes[0]->asElement()->tagNameText())->toBe('table')
            ->and($nodes[0]->asElement()->isPaired())->toBeTrue();

        $table = $nodes[0];
        $sections = $table->getChildren();

        expect($sections)->toHaveCount(7)
            ->and($sections[1]->asElement()->tagNameText())->toBe('thead')
            ->and($sections[3]->asElement()->tagNameText())->toBe('tbody')
            ->and($sections[5]->asElement()->tagNameText())->toBe('tfoot');
    });

    it('parses form with various inputs and selects', function (): void {
        $html = <<<'HTML'
<form method="post" action="/submit">
  <input type="text" name="a" value="x">
  <input type="checkbox" name="c" checked>
  <select name="s">
    <option value="1">One</option>
    <option value="2">Two</option>
  </select>
  <textarea name="t">Hello</textarea>
  <button type="submit">Go</button>
</form>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($nodes[0]->asElement()->tagNameText())->toBe('form')
            ->and($nodes[0]->asElement()->isPaired())->toBeTrue();

        $form = $nodes[0];
        $tags = $form->getChildren();

        expect($tags)->toHaveCount(11)
            ->and($tags[0])->toBeInstanceOf(TextNode::class)
            ->and($tags[1])->toBeInstanceOf(ElementNode::class)
            ->and($tags[1]->asElement()->tagNameText())->toBe('input')
            ->and($tags[2])->toBeInstanceOf(TextNode::class)
            ->and($tags[3])->toBeInstanceOf(ElementNode::class)
            ->and($tags[3]->asElement()->tagNameText())->toBe('input')
            ->and($tags[4])->toBeInstanceOf(TextNode::class)
            ->and($tags[5])->toBeInstanceOf(ElementNode::class)
            ->and($tags[5]->asElement()->tagNameText())->toBe('select')
            ->and($tags[6])->toBeInstanceOf(TextNode::class)
            ->and($tags[7])->toBeInstanceOf(ElementNode::class)
            ->and($tags[7]->asElement()->tagNameText())->toBe('textarea')
            ->and($tags[8])->toBeInstanceOf(TextNode::class)
            ->and($tags[9])->toBeInstanceOf(ElementNode::class)
            ->and($tags[9]->asElement()->tagNameText())->toBe('button')
            ->and($tags[10])->toBeInstanceOf(TextNode::class);
    });
});
