<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\CommentNode;

describe('Document Comments', function (): void {
    it('can get all comments', function (): void {
        $doc = $this->parse('{{-- blade --}} <!-- html --> {{-- another --}}');

        $comments = $doc->getComments();

        expect($comments)->toHaveCount(3)
            ->and($comments)->toBeInstanceOf(NodeCollection::class);
    });

    it('can get blade comments only', function (): void {
        $doc = $this->parse('{{-- blade --}} <!-- html --> {{-- another --}}');

        $bladeComments = $doc->getBladeComments();

        expect($bladeComments)->toHaveCount(2)
            ->and($bladeComments->first())->toBeInstanceOf(BladeCommentNode::class);
    });

    it('can get html comments only', function (): void {
        $doc = $this->parse('{{-- blade --}} <!-- html --> {{-- another --}}');

        $htmlComments = $doc->getHtmlComments();

        expect($htmlComments)->toHaveCount(1)
            ->and($htmlComments->first())->toBeInstanceOf(CommentNode::class);
    });

    it('returns empty collection when no comments', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $comments = $doc->getComments();

        expect($comments)->toHaveCount(0);
    });
});
