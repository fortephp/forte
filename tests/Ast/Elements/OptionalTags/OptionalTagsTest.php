<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('HTML5 Optional Tags', function (): void {
    it('handles 3-level nested lists', function (): void {
        $input = <<<'HTML'
<ul>
<li>L1
<ul>
<li>L2
<ul>
<li>L3
</ul>
</ul>
</ul>
HTML;

        $doc = $this->parse($input);
        $ul1 = $doc->firstChild();
        $li1 = $ul1->firstChildOfType(ElementNode::class);
        $ul2 = $li1->firstChildOfType(ElementNode::class);
        $li2 = $ul2->firstChildOfType(ElementNode::class);
        $ul3 = $li2->firstChildOfType(ElementNode::class);
        $li3 = $ul3->firstChildOfType(ElementNode::class);

        expect($li1->isPaired())->toBeFalse()
            ->and($li2->asElement()->isPaired())->toBeFalse()
            ->and($li3->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles empty <li> elements', function (): void {
        $input = <<<'HTML'
<ul>
<li>
<li>
<li>Content
</ul>
HTML;

        $doc = $this->parse($input);
        $ul = $doc->firstChild();
        $lis = $ul->getChildrenOfType(ElementNode::class);

        expect($lis)->toHaveCount(3)
            ->and($lis[0]->isPaired())->toBeFalse()
            ->and($lis[1]->isPaired())->toBeFalse()
            ->and($lis[2]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles empty <option> elements', function (): void {
        $input = '<select><option><option>Text<option></select>';

        $doc = $this->parse($input);
        $select = $doc->firstChild();
        $options = $select->getChildrenOfType(ElementNode::class);

        expect($options)->toHaveCount(3)
            ->and($doc->render())->toBe($input);
    });

    it('handles empty table cells', function (): void {
        $input = '<table><tr><td><td>Content<td></table>';

        expect($this->parse($input)->render())->toBe($input);
    });

    it('handles transition from dt to dd to dt', function (): void {
        $input = '<dl><dt>T1<dd>D1<dt>T2<dd>D2<dt>T3</dl>';

        $doc = $this->parse($input);
        $dl = $doc->firstChild()->asElement();
        $elements = $dl->getChildrenOfType(ElementNode::class);

        expect($elements)->toHaveCount(5)
            ->and($elements[0]->isPaired())->toBeFalse()
            ->and($elements[1]->isPaired())->toBeFalse()
            ->and($elements[2]->isPaired())->toBeFalse()
            ->and($elements[3]->isPaired())->toBeFalse()
            ->and($elements[4]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles transition from thead to tbody to tfoot', function (): void {
        $input = '<table><thead><tr><th>H<tbody><tr><td>B<tfoot><tr><td>F</table>';

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        $sections = $table->getChildrenOfType(ElementNode::class);

        expect($sections[0]->isPaired())->toBeFalse()
            ->and($sections[1]->isPaired())->toBeFalse()
            ->and($sections[2]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles mix of explicit and implicit closings in same list', function (): void {
        $input = '<ul><li>Omitted<li>Also omitted</li><li>Explicit<li>Omitted again</ul>';

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();
        $lis = $ul->getChildrenOfType(ElementNode::class);

        expect($lis)->toHaveCount(4)
            ->and($lis[0]->isPaired())->toBeFalse()
            ->and($lis[1]->isPaired())->toBeTrue()
            ->and($lis[2]->isPaired())->toBeFalse()
            ->and($lis[3]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles cascading closure at correct boundary', function (): void {
        $input = '<table><tr><td>A<tr><td>B</table>';

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        $trs = $table->getChildrenOfType(ElementNode::class);

        expect($trs)->toHaveCount(2)
            ->and($trs[0]->tagNameText())->toBe('tr')
            ->and($trs[1]->tagNameText())->toBe('tr')
            ->and($doc->render())->toBe($input);
    });

    it('handles multiple cascading closures', function (): void {
        $input = '<table><tbody><tr><td>A<tbody><tr><td>B</table>';

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        $tbodies = $table->getChildrenOfType(ElementNode::class);

        expect($tbodies)->toHaveCount(2)
            ->and($tbodies[0]->tagNameText())->toBe('tbody')
            ->and($tbodies[1]->tagNameText())->toBe('tbody')
            ->and($doc->render())->toBe($input);
    });

    it('handles context boundary in cascading', function (): void {
        $input = '<ul><li>Outer<ul><li>Inner</ul></ul>';

        $doc = $this->parse($input);
        $outerUl = $doc->firstChild();
        $outerLis = $outerUl->getChildrenOfType(ElementNode::class);

        expect($outerLis)->toHaveCount(1);

        $innerUl = $outerLis[0]->firstChildOfType(ElementNode::class)->asElement();
        $innerLis = $innerUl->getChildrenOfType(ElementNode::class);

        expect($innerLis)->toHaveCount(1)
            ->and($doc->render())->toBe($input);
    });

    it('handles long text in li', function (): void {
        $longText = str_repeat('A', 1000);
        $input = "<ul><li>{$longText}<li>Short</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();
        $lis = $ul->getChildrenOfType(ElementNode::class);

        expect($lis)->toHaveCount(2)
            ->and($lis[0]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles many siblings', function (): void {
        $items = array_map(fn ($i) => "<li>Item {$i}", range(1, 50));
        $input = '<ul>'.implode('', $items).'</ul>';

        $doc = $this->parse($input);
        $ul = $doc->firstChild();
        $lis = $ul->getChildrenOfType(ElementNode::class);

        expect($lis)->toHaveCount(50)
            ->and(collect($lis)->every(fn ($li) => ! $li->isPaired()))->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <li> when followed by another <li>', function (): void {
        $input = "<ul>\n<li>First\n<li>Second\n<li>Third\n</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();
        $items = $ul->nodes()->elements()->all();

        expect($items)->toHaveCount(3)
            ->and($items[0]->asElement()->tagNameText())->toBe('li')
            ->and($items[0]->asElement()->isPaired())->toBeFalse()
            ->and($items[1]->asElement()->isPaired())->toBeFalse()
            ->and($items[2]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <li> at </ul> parent end', function (): void {
        $input = "<ul>\n<li>Only item\n</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();
        $items = $ul->nodes()->elements()->all();

        expect($items)->toHaveCount(1)
            ->and($items[0]->asElement()->tagNameText())->toBe('li')
            ->and($items[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <li> at </ol> parent end', function (): void {
        $input = "<ol>\n<li>First\n<li>Second\n</ol>";

        $doc = $this->parse($input);
        $ol = $doc->firstChild()->asElement();
        $items = $ol->nodes()->elements()->all();

        expect($items)->toHaveCount(2)
            ->and($items[0]->asElement()->isPaired())->toBeFalse()
            ->and($items[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('preserves explicit </li> closing tags', function (): void {
        $input = "<ul>\n<li>First</li>\n<li>Second</li>\n</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();
        $items = $ul->nodes()->elements()->all();

        expect($items)->toHaveCount(2)
            ->and($items[0]->asElement()->isPaired())->toBeTrue()
            ->and($items[1]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('handles nested content in <li> with omitted tags', function (): void {
        $input = "<ul>\n<li><strong>Bold text</strong>\n<li><em>Italic text</em>\n</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();
        $items = $ul->nodes()->elements()->all();

        expect($items)->toHaveCount(2);

        $firstLiChildren = $items[0]->nodes()->elements()->all();
        expect($firstLiChildren)->toHaveCount(1)
            ->and($firstLiChildren[0]->asElement()->tagNameText())->toBe('strong');

        $secondLiChildren = $items[1]->nodes()->elements()->all();
        expect($secondLiChildren)->toHaveCount(1)
            ->and($secondLiChildren[0]->asElement()->tagNameText())->toBe('em')
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <dt> when followed by <dd>', function (): void {
        $input = "<dl>\n<dt>Term\n<dd>Definition\n</dl>";

        $doc = $this->parse($input);
        $dl = $doc->firstChild()->asElement();
        $items = $dl->nodes()->elements()->all();

        expect($items)->toHaveCount(2)
            ->and($items[0]->asElement()->tagNameText())->toBe('dt')
            ->and($items[0]->asElement()->isPaired())->toBeFalse()
            ->and($items[1]->asElement()->tagNameText())->toBe('dd')
            ->and($items[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <dt> when followed by another <dt>', function (): void {
        $input = "<dl>\n<dt>First term\n<dt>Second term\n<dd>Definition for second\n</dl>";

        $doc = $this->parse($input);
        $dl = $doc->firstChild()->asElement();
        $items = $dl->nodes()->elements()->all();

        expect($items)->toHaveCount(3)
            ->and($items[0]->asElement()->tagNameText())->toBe('dt')
            ->and($items[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <dd> when followed by <dt>', function (): void {
        $input = "<dl>\n<dt>First\n<dd>First definition\n<dt>Second\n<dd>Second definition\n</dl>";

        $doc = $this->parse($input);
        $dl = $doc->firstChild()->asElement();
        $items = $dl->nodes()->elements()->all();

        expect($items)->toHaveCount(4)
            ->and($items[1]->asElement()->tagNameText())->toBe('dd')
            ->and($items[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <dd> when followed by another <dd>', function (): void {
        $input = "<dl>\n<dt>Term\n<dd>First definition\n<dd>Second definition\n</dl>";

        $doc = $this->parse($input);
        $dl = $doc->firstChild()->asElement();
        $items = $dl->nodes()->elements()->all();

        expect($items)->toHaveCount(3)
            ->and($items[1]->asElement()->tagNameText())->toBe('dd')
            ->and($items[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('preserves explicit closing tags in definition lists', function (): void {
        $input = "<dl>\n<dt>Term</dt>\n<dd>Definition</dd>\n</dl>";

        $doc = $this->parse($input);
        $dl = $doc->firstChild()->asElement();
        $items = $dl->nodes()->elements()->all();

        expect($items)->toHaveCount(2)
            ->and($items[0]->asElement()->isPaired())->toBeTrue()
            ->and($items[1]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('handles mix of omitted and explicit closing tags', function (): void {
        $input = "<ul>\n<li>First (omitted)\n<li>Second (explicit)</li>\n<li>Third (omitted)\n</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();
        $items = $ul->nodes()->elements()->all();

        expect($items)->toHaveCount(3)
            ->and($items[0]->asElement()->isPaired())->toBeFalse()
            ->and($items[1]->asElement()->isPaired())->toBeTrue()
            ->and($items[2]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles nested lists with omitted tags', function (): void {
        $input = "<ul>\n<li>Parent 1\n<ul>\n<li>Child 1\n<li>Child 2\n</ul>\n<li>Parent 2\n</ul>";

        expect($this->parse($input)->render())->toBe($input);
    });

    it('validates <ul> with explicit closing tags', function (): void {
        $input = "<ul>\n<li>First</li>\n<li>Second</li>\n</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild()->asElement();

        expect($ul)->toBeInstanceOf(ElementNode::class)
            ->and($ul->tagNameText())->toBe('ul')
            ->and($ul->isPaired())->toBeTrue();

        $ulChildren = $ul->getChildren();
        expect($ulChildren)->toHaveCount(5)
            ->and($ulChildren[0]->asText())->toBeInstanceOf(TextNode::class)
            ->and($ulChildren[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($ulChildren[1]->asElement()->tagNameText())->toBe('li')
            ->and($ulChildren[1]->asElement()->isPaired())->toBeTrue()
            ->and($ulChildren[2]->asText())->toBeInstanceOf(TextNode::class)
            ->and($ulChildren[3]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($ulChildren[3]->asElement()->tagNameText())->toBe('li')
            ->and($ulChildren[3]->asElement()->isPaired())->toBeTrue()
            ->and($ulChildren[4]->asText())->toBeInstanceOf(TextNode::class)
            ->and($doc->render())->toBe($input);
    });

    it('validates nested <ul> with omitted tags', function (): void {
        $input = "<ul>\n<li>Parent\n<ul>\n<li>Child 1\n<li>Child 2\n</ul>\n</ul>";

        $doc = $this->parse($input);
        $outerUl = $doc->firstChild()->asElement();
        $outerLis = $outerUl->nodes()->elements()->all();

        expect($outerLis)->toHaveCount(1);

        $parentLi = $outerLis[0]->asElement();
        expect($parentLi)->toBeInstanceOf(ElementNode::class)
            ->and($parentLi->tagNameText())->toBe('li')
            ->and($parentLi->isPaired())->toBeFalse();

        $innerUl = $parentLi->nodes()->elements()->first()->asElement();
        expect($innerUl)->toBeInstanceOf(ElementNode::class)
            ->and($innerUl->tagNameText())->toBe('ul');

        $innerLis = $innerUl->nodes()->elements()->all();
        expect($innerLis)->toHaveCount(2)
            ->and($innerLis[0]->asElement()->isPaired())->toBeFalse()
            ->and($innerLis[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates <dl> with omitted tags', function (): void {
        $input = "<dl>\n<dt>Term 1\n<dd>Definition 1\n<dt>Term 2\n<dd>Definition 2\n</dl>";

        $doc = $this->parse($input);
        $dl = $doc->firstChild()->asElement();
        $dlElements = $dl->nodes()->elements()->all();

        expect($dlElements)->toHaveCount(4)
            ->and($dlElements[0]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($dlElements[0]->asElement()->tagNameText())->toBe('dt')
            ->and($dlElements[0]->asElement()->isPaired())->toBeFalse()
            ->and($dlElements[1]->asElement()->tagNameText())->toBe('dd')
            ->and($dlElements[1]->asElement()->isPaired())->toBeFalse()
            ->and($dlElements[2]->asElement()->tagNameText())->toBe('dt')
            ->and($dlElements[3]->asElement()->tagNameText())->toBe('dd')
            ->and($doc->render())->toBe($input);
    });

    it('validates <ol> with omitted tags', function (): void {
        $input = "<ol>\n<li>First\n<li>Second\n<li>Third\n</ol>";

        $doc = $this->parse($input);
        $ol = $doc->firstChild()->asElement();
        $liElements = $ol->nodes()->elements()->all();

        expect($ol->tagNameText())->toBe('ol')
            ->and($ol->isPaired())->toBeTrue()
            ->and($liElements)->toHaveCount(3)
            ->and($liElements[0]->asElement()->isPaired())->toBeFalse()
            ->and($liElements[1]->asElement()->isPaired())->toBeFalse()
            ->and($liElements[2]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates <li> in <menu> context with omitted tags', function (): void {
        $input = "<menu>\n<li>Item 1\n<li>Item 2\n</menu>";

        $doc = $this->parse($input);
        $menu = $doc->firstChild()->asElement();
        $items = $menu->nodes()->elements()->all();

        expect($menu->tagNameText())->toBe('menu')
            ->and($items)->toHaveCount(2)
            ->and($items[0]->asElement()->tagNameText())->toBe('li')
            ->and($items[0]->asElement()->isPaired())->toBeFalse()
            ->and($items[1]->asElement()->tagNameText())->toBe('li')
            ->and($items[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with explicit closing tags with div and paragraphs', function (): void {
        $input = <<<'HTML'
<div>
<p>First paragraph</p>
<p>Second paragraph</p>
</div>
HTML;

        $doc = $this->parse($input);
        $div = $doc->getChildren()[0]->asElement();

        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();

        $pElements = $div->nodes()->elements()->all();
        expect($pElements)->toHaveCount(2)
            ->and($pElements[0]->asElement()->tagNameText())->toBe('p')
            ->and($pElements[0]->asElement()->isPaired())->toBeTrue()
            ->and($pElements[1]->asElement()->tagNameText())->toBe('p')
            ->and($pElements[1]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with omitted closing tags with divs and paragraphs', function (): void {
        $input = <<<'HTML'
<div>
<p>First paragraph
<p>Second paragraph
<div>Third section</div>
</div>
HTML;

        $doc = $this->parse($input);
        $div = $doc->getChildren()[0]->asElement();
        $elements = $div->nodes()->elements()->all();

        expect($elements)->toHaveCount(3)
            ->and($elements[0]->asElement()->tagNameText())->toBe('p')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($elements[1]->asElement()->tagNameText())->toBe('p')
            ->and($elements[1]->asElement()->isPaired())->toBeFalse()
            ->and($elements[2]->asElement()->tagNameText())->toBe('div')
            ->and($elements[2]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with explicit closing tags with simple select', function (): void {
        $input = <<<'HTML'
<select>
<option value="1">One</option>
<option value="2">Two</option>
</select>
HTML;

        $doc = $this->parse($input);
        $select = $doc->getChildren()[0]->asElement();

        expect($select->tagNameText())->toBe('select')
            ->and($select->isPaired())->toBeTrue();

        $options = $select->nodes()->elements()->all();
        expect($options)->toHaveCount(2)
            ->and($options[0]->asElement()->tagNameText())->toBe('option')
            ->and($options[0]->asElement()->isPaired())->toBeTrue()
            ->and($options[1]->asElement()->tagNameText())->toBe('option')
            ->and($options[1]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with omitted closing tags with simple select', function (): void {
        $input = <<<'HTML'
<select>
<option value="1">One
<option value="2">Two
</select>
HTML;

        $doc = $this->parse($input);
        $select = $doc->getChildren()[0]->asElement();
        $options = $select->nodes()->elements()->all();

        expect($options)->toHaveCount(2)
            ->and($options[0]->asElement()->tagNameText())->toBe('option')
            ->and($options[0]->asElement()->isPaired())->toBeFalse()
            ->and($options[1]->asElement()->tagNameText())->toBe('option')
            ->and($options[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure in datalist context', function (): void {
        $input = <<<'HTML'
<datalist id="browsers">
<option value="Chrome">
<option value="Firefox">
<option value="Safari">
</datalist>
HTML;

        $doc = $this->parse($input);
        $datalist = $doc->getChildren()[0]->asElement();

        expect($datalist->tagNameText())->toBe('datalist');

        $options = $datalist->nodes()->elements()->all();
        expect($options)->toHaveCount(3)
            ->and($options[0]->asElement()->isPaired())->toBeFalse()
            ->and($options[1]->asElement()->isPaired())->toBeFalse()
            ->and($options[2]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with explicit closing tags in select', function (): void {
        $input = <<<'HTML'
<select>
<optgroup label="Group 1">
<option>Item 1</option>
</optgroup>
<optgroup label="Group 2">
<option>Item 2</option>
</optgroup>
</select>
HTML;

        $doc = $this->parse($input);
        $select = $doc->getChildren()[0]->asElement();
        $optgroups = $select->nodes()->whereElementIs('optgroup')->all();

        expect($optgroups)->toHaveCount(2)
            ->and($optgroups[0]->asElement()->isPaired())->toBeTrue()
            ->and($optgroups[1]->asElement()->isPaired())->toBeTrue();

        $options1 = $optgroups[0]->nodes()->elements()->all();
        expect($options1)->toHaveCount(1)
            ->and($options1[0]->asElement()->tagNameText())->toBe('option')
            ->and($options1[0]->asElement()->isPaired())->toBeTrue();

        $options2 = $optgroups[1]->nodes()->elements()->all();
        expect($options2)->toHaveCount(1)
            ->and($options2[0]->asElement()->tagNameText())->toBe('option')
            ->and($options2[0]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with omitted closing tags select and optgroup', function (): void {
        $input = <<<'HTML'
<select>
<optgroup label="Group 1">
<option>Item 1
<optgroup label="Group 2">
<option>Item 2
</select>
HTML;

        $doc = $this->parse($input);
        $select = $doc->getChildren()[0]->asElement();
        /** @var ElementNode[] $optgroups */
        $optgroups = $select->nodes()->whereElementIs('optgroup')->all();

        expect($optgroups)->toHaveCount(2)
            ->and($optgroups[0]->isPaired())->toBeFalse()
            ->and($optgroups[1]->isPaired())->toBeFalse();

        $firstOptions = $optgroups[0]->nodes()->elements()->all();
        expect($firstOptions)->toHaveCount(1)
            ->and($firstOptions[0]->isPaired())->toBeFalse();

        $secondOptions = $optgroups[1]->nodes()->elements()->all();
        expect($secondOptions)->toHaveCount(1)
            ->and($secondOptions[0]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <p> when followed by <div>', function (): void {
        $input = "<div>\n<p>First paragraph\n<div>Second div</div>\n</div>";

        $doc = $this->parse($input);
        $outerDiv = $doc->firstChild()->asElement();
        $elements = $outerDiv->nodes()->elements()->all();

        expect($elements)->toHaveCount(2)
            ->and($elements[0]->asElement()->tagNameText())->toBe('p')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <p> when followed by heading', function (): void {
        $input = "<div>\n<p>Some text\n<h1>Main heading</h1>\n</div>";

        $doc = $this->parse($input);
        $div = $doc->firstChild()->asElement();
        $elements = $div->nodes()->elements()->all();

        expect($elements)->toHaveCount(2)
            ->and($elements[0]->asElement()->tagNameText())->toBe('p')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('does not auto-close <p> when followed by inline element', function (): void {
        $input = "<div>\n<p>Some <span>text</span></p>\n</div>";

        $doc = $this->parse($input);
        $div = $doc->firstChild()->asElement();
        $elements = $div->nodes()->elements()->all();

        expect($elements)->toHaveCount(1)
            ->and($elements[0]->asElement()->tagNameText())->toBe('p')
            ->and($elements[0]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('preserves explicit </p> closing tags', function (): void {
        $input = "<div>\n<p>First paragraph</p>\n<p>Second paragraph</p>\n</div>";

        $doc = $this->parse($input);
        $div = $doc->firstChild()->asElement();
        $elements = $div->nodes()->elements()->all();

        expect($elements)->toHaveCount(2)
            ->and($elements[0]->asElement()->isPaired())->toBeTrue()
            ->and($elements[1]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <option> when followed by another <option>', function (): void {
        $input = "<select>\n<option>First\n<option>Second\n<option>Third\n</select>";

        $doc = $this->parse($input);
        $select = $doc->firstChild()->asElement();
        $options = $select->nodes()->elements()->all();

        expect($options)->toHaveCount(3)
            ->and($options[0]->asElement()->isPaired())->toBeFalse()
            ->and($options[1]->asElement()->isPaired())->toBeFalse()
            ->and($options[2]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <option> at </select> parent end', function (): void {
        $input = "<select>\n<option>Only option\n</select>";

        $doc = $this->parse($input);
        $select = $doc->firstChild()->asElement();
        $options = $select->nodes()->elements()->all();

        expect($options)->toHaveCount(1)
            ->and($options[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <optgroup> when followed by another <optgroup>', function (): void {
        $input = "<select>\n<optgroup label=\"Group 1\">\n<option>Option 1\n<optgroup label=\"Group 2\">\n<option>Option 2\n</select>";

        $doc = $this->parse($input);
        $select = $doc->firstChild()->asElement();
        $groups = $select->nodes()->whereElementIs('optgroup')->all();

        expect($groups)->toHaveCount(2)
            ->and($groups[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <optgroup> at </select> parent end', function (): void {
        $input = "<select>\n<optgroup label=\"Only group\">\n<option>Option\n</select>";

        $doc = $this->parse($input);
        $select = $doc->firstChild()->asElement();
        $groups = $select->nodes()->whereElementIs('optgroup')->all();

        expect($groups)->toHaveCount(1)
            ->and($groups[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('preserves explicit closing tags in forms', function (): void {
        $input = "<select>\n<option>First</option>\n<option>Second</option>\n</select>";

        $doc = $this->parse($input);
        $select = $doc->firstChild()->asElement();
        $options = $select->nodes()->elements()->all();

        expect($options)->toHaveCount(2)
            ->and($options[0]->asElement()->isPaired())->toBeTrue()
            ->and($options[1]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with explicit closing tags with table and rows', function (): void {
        $input = <<<'HTML'
<table>
<tr>
<td>A</td>
<td>B</td>
</tr>
<tr>
<td>C</td>
<td>D</td>
</tr>
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();

        expect($table->tagNameText())->toBe('table')
            ->and($table->isPaired())->toBeTrue();

        $trs = $table->nodes()->elements()->all();
        expect($trs)->toHaveCount(2)
            ->and($trs[0]->asElement()->tagNameText())->toBe('tr')
            ->and($trs[0]->asElement()->isPaired())->toBeTrue()
            ->and($trs[1]->asElement()->tagNameText())->toBe('tr')
            ->and($trs[1]->asElement()->isPaired())->toBeTrue();

        $tds1 = $trs[0]->nodes()->elements()->all();
        expect($tds1)->toHaveCount(2)
            ->and($tds1[0]->asElement()->tagNameText())->toBe('td')
            ->and($tds1[0]->asElement()->isPaired())->toBeTrue()
            ->and($tds1[1]->asElement()->tagNameText())->toBe('td')
            ->and($tds1[1]->asElement()->isPaired())->toBeTrue();

        $tds2 = $trs[1]->nodes()->elements()->all();
        expect($tds2)->toHaveCount(2)
            ->and($tds2[0]->asElement()->tagNameText())->toBe('td')
            ->and($tds2[0]->asElement()->isPaired())->toBeTrue()
            ->and($tds2[1]->asElement()->tagNameText())->toBe('td')
            ->and($tds2[1]->asElement()->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with omitted closing tags', function (): void {
        $input = <<<'HTML'
<table>
<tr>
<td>A
<td>B
<tr>
<td>C
<td>D
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();
        $trs = $table->nodes()->elements()->all();

        expect($trs)->toHaveCount(2)
            ->and($trs[0]->asElement()->tagNameText())->toBe('tr')
            ->and($trs[0]->asElement()->isPaired())->toBeFalse()
            ->and($trs[1]->asElement()->tagNameText())->toBe('tr')
            ->and($trs[1]->asElement()->isPaired())->toBeFalse();

        $firstTds = $trs[0]->nodes()->elements()->all();
        expect($firstTds)->toHaveCount(2)
            ->and($firstTds[0]->asElement()->isPaired())->toBeFalse()
            ->and($firstTds[1]->asElement()->isPaired())->toBeFalse();

        $secondTds = $trs[1]->nodes()->elements()->all();
        expect($secondTds)->toHaveCount(2)
            ->and($secondTds[0]->asElement()->isPaired())->toBeFalse()
            ->and($secondTds[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates <th> elements with omitted tags', function (): void {
        $input = <<<'HTML'
<table>
<tr>
<th>Header 1
<th>Header 2
<tr>
<td>Data 1
<td>Data 2
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();
        $trs = $table->nodes()->elements()->all();
        $ths = $trs[0]->nodes()->elements()->all();

        expect($ths)->toHaveCount(2)
            ->and($ths[0]->asElement()->tagNameText())->toBe('th')
            ->and($ths[0]->asElement()->isPaired())->toBeFalse()
            ->and($ths[1]->asElement()->tagNameText())->toBe('th')
            ->and($ths[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates multiple tbody sections with omitted tags', function (): void {
        $input = <<<'HTML'
<table>
<tbody>
<tr><td>Section 1
<tbody>
<tr><td>Section 2
<tbody>
<tr><td>Section 3
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();
        $tbodies = $table->nodes()->elements()->all();

        expect($tbodies)->toHaveCount(3)
            ->and($tbodies[0]->asElement()->isPaired())->toBeFalse()
            ->and($tbodies[1]->asElement()->isPaired())->toBeFalse()
            ->and($tbodies[2]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with explicit closing tags w/ colgroup', function (): void {
        $input = <<<'HTML'
<table>
<colgroup>
<col style="width:50%">
<col style="width:50%">
</colgroup>
<tr><td>A</td><td>B</td></tr>
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();
        $elements = $table->nodes()->elements()->all();

        expect($elements[0]->asElement()->tagNameText())->toBe('colgroup')
            ->and($elements[0]->asElement()->isPaired())->toBeTrue();

        $cols = $elements[0]->nodes()->elements()->all();
        expect($cols)->toHaveCount(2)
            ->and($cols[0]->asElement()->tagNameText())->toBe('col')
            ->and($cols[1]->asElement()->tagNameText())->toBe('col')
            ->and($doc->render())->toBe($input);
    });

    it('validates full AST structure with omitted closing tags and table w/ colgroup', function (): void {
        $input = <<<'HTML'
<table>
<colgroup>
<col>
<col>
<thead>
<tr><th>Header
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();
        $elements = $table->nodes()->elements()->all();

        expect($elements[0]->asElement()->tagNameText())->toBe('colgroup')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($elements[1]->asElement()->tagNameText())->toBe('thead')
            ->and($elements[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates colgroup auto-closing when followed by tr', function (): void {
        $input = <<<'HTML'
<table>
<colgroup>
<col>
<tr>
<td>Data
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();
        $elements = $table->nodes()->elements()->all();

        expect($elements[0]->asElement()->tagNameText())->toBe('colgroup')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($elements[1]->asElement()->tagNameText())->toBe('tr')
            ->and($elements[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('validates complete table with all section types and omitted tags', function (): void {
        $input = <<<'HTML'
<table>
<colgroup>
<col>
<col>
<thead>
<tr>
<th>Col 1
<th>Col 2
<tbody>
<tr>
<td>A1
<td>A2
<tr>
<td>B1
<td>B2
<tfoot>
<tr>
<td>F1
<td>F2
</table>
HTML;

        $doc = $this->parse($input);
        $table = $doc->getChildren()[0]->asElement();
        $sections = $table->nodes()->elements()->all();

        expect($sections)->toHaveCount(4)
            ->and($sections[0]->asElement()->tagNameText())->toBe('colgroup')
            ->and($sections[0]->asElement()->isPaired())->toBeFalse()
            ->and($sections[1]->asElement()->tagNameText())->toBe('thead')
            ->and($sections[1]->asElement()->isPaired())->toBeFalse()
            ->and($sections[2]->asElement()->tagNameText())->toBe('tbody')
            ->and($sections[2]->asElement()->isPaired())->toBeFalse()
            ->and($sections[3]->asElement()->tagNameText())->toBe('tfoot')
            ->and($sections[3]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <tr> when followed by another <tr>', function (): void {
        $input = "<table>\n<tr>\n<td>Cell 1\n<tr>\n<td>Cell 2\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $trs */
        $trs = $table->nodes()->whereElementIs('tr')->all();

        expect($trs)->toHaveCount(2)
            ->and($trs[0]->isPaired())->toBeFalse()
            ->and($trs[1]->isPaired())->toBeFalse();

        $tds0 = $trs[0]->nodes()->whereElementIs('td')->all();
        $tds1 = $trs[1]->nodes()->whereElementIs('td')->all();

        expect($tds0)->toHaveCount(1)
            ->and($tds1)->toHaveCount(1)
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <td> when followed by another <td>', function (): void {
        $input = "<table>\n<tr>\n<td>Cell 1\n<td>Cell 2\n<td>Cell 3\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $trs */
        $trs = $table->nodes()->whereElementIs('tr')->all();
        /** @var ElementNode[] $tds */
        $tds = $trs[0]->nodes()->whereElementIs('td')->all();

        expect($trs)->toHaveCount(1)
            ->and($tds)->toHaveCount(3)
            ->and($tds[0]->isPaired())->toBeFalse()
            ->and($tds[1]->isPaired())->toBeFalse()
            ->and($tds[2]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <th> when followed by another <th>', function (): void {
        $input = "<table>\n<thead>\n<tr>\n<th>Header 1\n<th>Header 2\n<th>Header 3\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $theads */
        $theads = $table->nodes()->whereElementIs('thead')->all();
        /** @var ElementNode[] $trs */
        $trs = $theads[0]->nodes()->whereElementIs('tr')->all();
        /** @var ElementNode[] $ths */
        $ths = $trs[0]->nodes()->whereElementIs('th')->all();

        expect($theads)->toHaveCount(1)
            ->and($trs)->toHaveCount(1)
            ->and($ths)->toHaveCount(3)
            ->and($ths[0]->isPaired())->toBeFalse()
            ->and($ths[1]->isPaired())->toBeFalse()
            ->and($ths[2]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <td> at </tr> parent end', function (): void {
        $input = "<table>\n<tr>\n<td>Only cell\n</tr>\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $trs */
        $trs = $table->nodes()->whereElementIs('tr')->all();
        /** @var ElementNode[] $tds */
        $tds = $trs[0]->nodes()->whereElementIs('td')->all();

        expect($trs)->toHaveCount(1)
            ->and($tds)->toHaveCount(1)
            ->and($tds[0]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('preserves explicit closing tags', function (): void {
        $input = "<table>\n<tr>\n<td>Cell 1</td>\n<td>Cell 2</td>\n</tr>\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $trs */
        $trs = $table->nodes()->whereElementIs('tr')->all();
        /** @var ElementNode[] $tds */
        $tds = $trs[0]->nodes()->whereElementIs('td')->all();

        expect($tds[0]->isPaired())->toBeTrue()
            ->and($tds[1]->isPaired())->toBeTrue()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <thead> when followed by <tbody>', function (): void {
        $input = "<table>\n<thead>\n<tr>\n<th>Header\n<tbody>\n<tr>\n<td>Data\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $sections */
        $sections = $table->nodes()->elements()->all();

        expect($sections)->toHaveCount(2)
            ->and($sections[0]->tagNameText())->toBe('thead')
            ->and($sections[0]->isPaired())->toBeFalse()
            ->and($sections[1]->tagNameText())->toBe('tbody')
            ->and($sections[1]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <thead> at </table> parent end', function (): void {
        $input = "<table>\n<thead>\n<tr>\n<th>Header\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $sections */
        $sections = $table->nodes()->whereElementIs('thead')->all();

        expect($sections)->toHaveCount(1)
            ->and($sections[0]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <tbody> at </table> parent end', function (): void {
        $input = "<table>\n<tbody>\n<tr>\n<td>Data\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $sections */
        $sections = $table->nodes()->whereElementIs('tbody')->all();

        expect($sections)->toHaveCount(1)
            ->and($sections[0]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <tfoot> at </table> parent end', function (): void {
        $input = "<table>\n<tfoot>\n<tr>\n<td>Footer\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $sections */
        $sections = $table->nodes()->whereElementIs('tfoot')->all();

        expect($sections)->toHaveCount(1)
            ->and($sections[0]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('renders complete table sections', function (): void {
        $input = "<table>\n<thead>\n<tr>\n<th>Name\n<th>Age\n<tbody>\n<tr>\n<td>John\n<td>30\n<tr>\n<td>Jane\n<td>25\n<tfoot>\n<tr>\n<td>Total\n<td>2 people\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $sections */
        $sections = $table->nodes()->elements()->all();

        expect($sections)->toHaveCount(3)
            ->and($sections[0]->tagNameText())->toBe('thead')
            ->and($sections[0]->isPaired())->toBeFalse()
            ->and($sections[1]->tagNameText())->toBe('tbody')
            ->and($sections[1]->isPaired())->toBeFalse()
            ->and($sections[2]->tagNameText())->toBe('tfoot')
            ->and($sections[2]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes <colgroup> at </table> parent end', function (): void {
        $input = "<table>\n<colgroup>\n<col>\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $colgroups */
        $colgroups = $table->nodes()->whereElementIs('colgroup')->all();

        expect($colgroups)->toHaveCount(1)
            ->and($colgroups[0]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles complete table with all optional tags omitted', function (): void {
        $input = "<table>\n<colgroup>\n<col>\n<col>\n<thead>\n<tr>\n<th>Column 1\n<th>Column 2\n<tbody>\n<tr>\n<td>Data 1\n<td>Data 2\n<tr>\n<td>Data 3\n<td>Data 4\n<tfoot>\n<tr>\n<td>Footer 1\n<td>Footer 2\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $sections */
        $sections = $table->nodes()->elements()->all();

        expect($sections)->toHaveCount(4)
            ->and($sections[0]->tagNameText())->toBe('colgroup')
            ->and($sections[0]->isPaired())->toBeFalse()
            ->and($sections[1]->tagNameText())->toBe('thead')
            ->and($sections[1]->isPaired())->toBeFalse()
            ->and($sections[2]->tagNameText())->toBe('tbody')
            ->and($sections[2]->isPaired())->toBeFalse()
            ->and($sections[3]->tagNameText())->toBe('tfoot')
            ->and($sections[3]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles tables without section elements', function (): void {
        $input = "<table>\n<tr>\n<td>A\n<td>B\n<tr>\n<td>C\n<td>D\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild()->asElement();
        /** @var ElementNode[] $trs */
        $trs = $table->nodes()->whereElementIs('tr')->all();

        expect($table->tagNameText())->toBe('table')
            ->and($trs)->toHaveCount(2)
            ->and($trs[0]->isPaired())->toBeFalse()
            ->and($trs[1]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('handles nested tables with optional tags', function (): void {
        $input = "<table>\n<tr>\n<td>Outer\n<td>\n<table>\n<tr>\n<td>Inner 1\n<td>Inner 2\n</table>\n</table>";

        $doc = $this->parse($input);
        $outerTable = $doc->firstChild()->asElement();
        /** @var ElementNode[] $outerTrs */
        $outerTrs = $outerTable->nodes()->whereElementIs('tr')->all();
        /** @var ElementNode[] $outerCells */
        $outerCells = $outerTrs[0]->nodes()->whereElementIs('td')->all();
        /** @var ElementNode[] $innerTables */
        $innerTables = $outerCells[1]->nodes()->whereElementIs('table')->all();

        expect($outerTable->tagNameText())->toBe('table')
            ->and($outerTrs)->toHaveCount(1)
            ->and($outerTrs[0]->isPaired())->toBeFalse()
            ->and($outerCells)->toHaveCount(2)
            ->and($outerCells[0]->isPaired())->toBeFalse()
            ->and($outerCells[1]->isPaired())->toBeFalse()
            ->and($innerTables)->toHaveCount(1)
            ->and($doc->render())->toBe($input);
    });
});
