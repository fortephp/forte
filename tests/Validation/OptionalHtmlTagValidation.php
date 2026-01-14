<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;

describe('Optional HTML Tag Validation', function (): void {
    it('parses <li> in <ul>', function (): void {
        $input = '<ul><li>A<li>B</ul>';
        $doc = $this->parse($input);

        $ul = $doc->firstChild()->asElement();
        expect($ul)->toBeInstanceOf(ElementNode::class)
            ->and($ul->tagNameText())->toBe('ul');

        $lis = $ul->getChildrenOfType(ElementNode::class);
        expect($lis)->toHaveCount(2)
            ->and($lis[0]->asElement()->tagNameText())->toBe('li')
            ->and($lis[1]->asElement()->tagNameText())->toBe('li')
            ->and($lis[0]->asElement()->isPaired())->toBeFalse()
            ->and($lis[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <li> in <ol>', function (): void {
        $input = '<ol><li>A<li>B</ol>';
        $doc = $this->parse($input);

        $ol = $doc->firstChild()->asElement();
        $lis = $ol->getChildrenOfType(ElementNode::class);

        expect($lis)->toHaveCount(2)
            ->and($lis[0]->asElement()->isPaired())->toBeFalse()
            ->and($lis[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <li> in <menu>', function (): void {
        $input = '<menu><li>A<li>B</menu>';
        $doc = $this->parse($input);

        $menu = $doc->firstChild()->asElement();
        $lis = $menu->getChildrenOfType(ElementNode::class);

        expect($lis)->toHaveCount(2)
            ->and($lis[0]->asElement()->isPaired())->toBeFalse()
            ->and($lis[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <dt> and <dd> in <dl>', function (): void {
        $input = '<dl><dt>T<dd>D</dl>';
        $doc = $this->parse($input);

        $dl = $doc->firstChild()->asElement();
        $elements = $dl->getChildrenOfType(ElementNode::class);

        expect($elements)->toHaveCount(2)
            ->and($elements[0]->asElement()->tagNameText())->toBe('dt')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($elements[1]->asElement()->tagNameText())->toBe('dd')
            ->and($elements[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <option> in <select>', function (): void {
        $input = '<select><option>A<option>B</select>';
        $doc = $this->parse($input);

        $select = $doc->firstChild()->asElement();
        $options = $select->getChildrenOfType(ElementNode::class);

        expect($options)->toHaveCount(2)
            ->and($options[0]->asElement()->isPaired())->toBeFalse()
            ->and($options[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <option> in <datalist>', function (): void {
        $input = '<datalist><option>A<option>B</datalist>';
        $doc = $this->parse($input);

        $datalist = $doc->firstChild()->asElement();
        $options = $datalist->getChildrenOfType(ElementNode::class);

        expect($options)->toHaveCount(2)
            ->and($options[0]->asElement()->isPaired())->toBeFalse()
            ->and($options[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <option> in <optgroup>', function (): void {
        $input = '<select><optgroup label="G"><option>A<option>B</select>';
        $doc = $this->parse($input);

        $select = $doc->firstChild()->asElement();
        $optgroup = $select->firstChildOfType(ElementNode::class)->asElement();
        $options = $optgroup->getChildrenOfType(ElementNode::class);

        expect($options)->toHaveCount(2)
            ->and($options[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <optgroup> in <select>', function (): void {
        $input = '<select><optgroup label="G1"><optgroup label="G2"></select>';
        $doc = $this->parse($input);

        $select = $doc->firstChild()->asElement();
        $optgroups = $select->getChildrenOfType(ElementNode::class);

        expect($optgroups)->toHaveCount(2)
            ->and($optgroups[0]->asElement()->isPaired())->toBeFalse()
            ->and($optgroups[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <tr> in <table>', function (): void {
        $input = '<table><tr><td>A<tr><td>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $trs = $table->getChildrenOfType(ElementNode::class);

        expect($trs)->toHaveCount(2)
            ->and($trs[0]->asElement()->isPaired())->toBeFalse()
            ->and($trs[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <tr> in <thead>', function (): void {
        $input = '<table><thead><tr><th>A<tr><th>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $thead = $table->firstChildOfType(ElementNode::class)->asElement();
        $trs = $thead->getChildrenOfType(ElementNode::class);

        expect($trs)->toHaveCount(2)
            ->and($trs[0]->asElement()->isPaired())->toBeFalse()
            ->and($trs[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <tr> in <tbody>', function (): void {
        $input = '<table><tbody><tr><td>A<tr><td>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $tbody = $table->firstChildOfType(ElementNode::class)->asElement();
        $trs = $tbody->getChildrenOfType(ElementNode::class);

        expect($trs)->toHaveCount(2)
            ->and($trs[0]->asElement()->isPaired())->toBeFalse()
            ->and($trs[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <tr> in <tfoot>', function (): void {
        $input = '<table><tfoot><tr><td>A<tr><td>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $tfoot = $table->firstChildOfType(ElementNode::class)->asElement();
        $trs = $tfoot->getChildrenOfType(ElementNode::class);

        expect($trs)->toHaveCount(2)
            ->and($trs[0]->asElement()->isPaired())->toBeFalse()
            ->and($trs[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <td> in <tr>', function (): void {
        $input = '<table><tr><td>A<td>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $tr = $table->firstChildOfType(ElementNode::class)->asElement();
        $tds = $tr->getChildrenOfType(ElementNode::class);

        expect($tds)->toHaveCount(2)
            ->and($tds[0]->asElement()->isPaired())->toBeFalse()
            ->and($tds[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <th> in <tr>', function (): void {
        $input = '<table><tr><th>A<th>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $tr = $table->firstChildOfType(ElementNode::class)->asElement();
        $ths = $tr->getChildrenOfType(ElementNode::class);

        expect($ths)->toHaveCount(2)
            ->and($ths[0]->asElement()->isPaired())->toBeFalse()
            ->and($ths[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses mixed <td> and <th> in <tr>', function (): void {
        $input = '<table><tr><th>H<td>D</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $tr = $table->firstChildOfType(ElementNode::class)->asElement();
        $cells = $tr->getChildrenOfType(ElementNode::class);

        expect($cells)->toHaveCount(2)
            ->and($cells[0]->asElement()->tagNameText())->toBe('th')
            ->and($cells[0]->asElement()->isPaired())->toBeFalse()
            ->and($cells[1]->asElement()->tagNameText())->toBe('td')
            ->and($cells[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <thead> in <table>', function (): void {
        $input = '<table><thead><tr><th>A<tbody><tr><td>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $sections = $table->getChildrenOfType(ElementNode::class);

        expect($sections)->toHaveCount(2)
            ->and($sections[0]->asElement()->tagNameText())->toBe('thead')
            ->and($sections[0]->asElement()->isPaired())->toBeFalse()
            ->and($sections[1]->asElement()->tagNameText())->toBe('tbody')
            ->and($sections[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <tbody> in <table>', function (): void {
        $input = '<table><tbody><tr><td>A<tbody><tr><td>B</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $tbodies = $table->getChildrenOfType(ElementNode::class);

        expect($tbodies)->toHaveCount(2)
            ->and($tbodies[0]->asElement()->isPaired())->toBeFalse()
            ->and($tbodies[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <tfoot> in <table>', function (): void {
        $input = '<table><tfoot><tr><td>F</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $tfoot = $table->firstChildOfType(ElementNode::class)->asElement();

        expect($tfoot->tagNameText())->toBe('tfoot')
            ->and($tfoot->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses <colgroup> in <table>', function (): void {
        $input = '<table><colgroup><col><thead><tr><th>H</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $elements = $table->getChildrenOfType(ElementNode::class);

        expect($elements[0]->asElement()->tagNameText())->toBe('colgroup')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses all table sections together', function (): void {
        $input = '<table><colgroup><col><thead><tr><th>H<tbody><tr><td>D<tfoot><tr><td>F</table>';
        $doc = $this->parse($input);

        $table = $doc->firstChild()->asElement();
        $sections = $table->getChildrenOfType(ElementNode::class);

        expect($sections)->toHaveCount(4)
            ->and($sections[0]->asElement()->tagNameText())->toBe('colgroup')
            ->and($sections[1]->asElement()->tagNameText())->toBe('thead')
            ->and($sections[2]->asElement()->tagNameText())->toBe('tbody')
            ->and($sections[3]->asElement()->tagNameText())->toBe('tfoot')
            ->and($sections[0]->asElement()->isPaired())->toBeFalse()
            ->and($sections[1]->asElement()->isPaired())->toBeFalse()
            ->and($sections[2]->asElement()->isPaired())->toBeFalse()
            ->and($sections[3]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses nested list with definition list', function (): void {
        $input = '<ul><li>Item<dl><dt>Term<dd>Def</dl></ul>';
        $doc = $this->parse($input);

        $ul = $doc->firstChild()->asElement();
        $li = $ul->firstChildOfType(ElementNode::class)->asElement();
        $dl = $li->firstChildOfType(ElementNode::class)->asElement();

        expect($dl->tagNameText())->toBe('dl');

        $dlChildren = $dl->getChildrenOfType(ElementNode::class);
        expect($dlChildren)->toHaveCount(2)
            ->and($dlChildren[0]->isPaired())->toBeFalse()
            ->and($dlChildren[1]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('parses paragraphs with lists', function (): void {
        $input = '<div><p>Text<ul><li>Item</ul><p>More<div>Block</div></div>';
        $doc = $this->parse($input);

        $div = $doc->firstChild()->asElement();
        $elements = $div->getChildrenOfType(ElementNode::class);

        expect($elements)->toHaveCount(4)
            ->and($elements[0]->asElement()->tagNameText())->toBe('p')
            ->and($elements[0]->asElement()->isPaired())->toBeFalse()
            ->and($elements[2]->asElement()->tagNameText())->toBe('p')
            ->and($elements[2]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });
});
