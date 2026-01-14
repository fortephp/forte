<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;

describe('Neighboring Optional Tag Auto Closing', function (): void {
    it('auto-closes tr when followed by another tr', function (): void {
        $input = "<table>\n<tr>\n<td>Cell 1\n<tr>\n<td>Cell 2\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild();
        $elements = $table->nodes()->elements()->all();

        expect($table)->toBeInstanceOf(ElementNode::class)
            ->and($elements)->toHaveCount(2)
            ->and($elements[0]->tagNameText())->toBe('tr')
            ->and($elements[1]->tagNameText())->toBe('tr')
            ->and($elements[0]->isPaired())->toBeFalse()
            ->and($elements[1]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes td when followed by another td', function (): void {
        $input = "<table>\n<tr>\n<td>A\n<td>B\n<td>C\n</table>";

        $doc = $this->parse($input);
        $table = $doc->firstChild();
        $trs = $table->nodes()->whereElementIs('tr')->all();
        $tds = $trs[0]->nodes()->whereElementIs('td')->all();

        expect($trs)->toHaveCount(1)
            ->and($tds)->toHaveCount(3)
            ->and($tds[0]->isPaired())->toBeFalse()
            ->and($tds[1]->isPaired())->toBeFalse()
            ->and($tds[2]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    it('auto-closes li when followed by another li', function (): void {
        $input = "<ul>\n<li>Item 1\n<li>Item 2\n<li>Item 3\n</ul>";

        $doc = $this->parse($input);
        $ul = $doc->firstChild();
        $lis = $ul->nodes()->whereElementIs('li')->all();

        expect($lis)->toHaveCount(3)
            ->and($lis[0]->isPaired())->toBeFalse()
            ->and($lis[1]->isPaired())->toBeFalse()
            ->and($lis[2]->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });
});
