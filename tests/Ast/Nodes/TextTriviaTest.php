<?php

declare(strict_types=1);

use Forte\Ast\TextNode;
use Forte\Ast\Trivia\Trivia;
use Forte\Ast\Trivia\TriviaKind;
use Forte\Ast\Trivia\TriviaParser;

describe('Text Trivia', function (): void {
    it('parses leading whitespace, content, and trailing whitespace', function (): void {
        $trivia = TriviaParser::parse("  \n  Hello World  \n  ");

        expect($trivia)->toHaveCount(5)
            ->and($trivia[0])->toBeInstanceOf(Trivia::class)
            ->and($trivia[0]->kind)->toBe(TriviaKind::LeadingWhitespace)
            ->and($trivia[0]->content)->toBe("  \n  ")
            ->and($trivia[1]->kind)->toBe(TriviaKind::Word)
            ->and($trivia[1]->content)->toBe('Hello')
            ->and($trivia[2]->kind)->toBe(TriviaKind::InnerWhitespace)
            ->and($trivia[2]->content)->toBe(' ')
            ->and($trivia[3]->kind)->toBe(TriviaKind::Word)
            ->and($trivia[3]->content)->toBe('World')
            ->and($trivia[4]->kind)->toBe(TriviaKind::TrailingWhitespace)
            ->and($trivia[4]->content)->toBe("  \n  ");
    });

    it('parses text with only leading whitespace', function (): void {
        $trivia = TriviaParser::parse("  \nHello");

        expect($trivia)->toHaveCount(2)
            ->and($trivia[0]->kind)->toBe(TriviaKind::LeadingWhitespace)
            ->and($trivia[1]->kind)->toBe(TriviaKind::Word);
    });

    it('parses text with only trailing whitespace', function (): void {
        $trivia = TriviaParser::parse("Hello  \n");

        expect($trivia)->toHaveCount(2)
            ->and($trivia[0]->kind)->toBe(TriviaKind::Word)
            ->and($trivia[1]->kind)->toBe(TriviaKind::TrailingWhitespace);
    });

    it('parses text with no whitespace', function (): void {
        $trivia = TriviaParser::parse('Hello');

        expect($trivia)->toHaveCount(1)
            ->and($trivia[0]->kind)->toBe(TriviaKind::Word)
            ->and($trivia[0]->content)->toBe('Hello');
    });

    it('parses whitespace-only text', function (): void {
        $trivia = TriviaParser::parse("  \n  ");

        expect($trivia)->toHaveCount(1)
            ->and($trivia[0]->kind)->toBe(TriviaKind::LeadingWhitespace);
    });

    it('counts newlines in trivia', function (): void {
        $trivia = TriviaParser::parse("\n\nHello\n\n\n");

        expect($trivia[0]->getNewlineCount())->toBe(2)
            ->and($trivia[2]->getNewlineCount())->toBe(3);
    });

    it('detects multiple newlines in trivia', function (): void {
        $trivia = TriviaParser::parse("\nHello\n\n");

        expect($trivia[0]->hasMultipleNewlines())->toBeFalse()
            ->and($trivia[2]->hasMultipleNewlines())->toBeTrue();
    });

    it('detects whitespace-only text', function (): void {
        $doc = $this->parse("  \n  ");
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->isWhitespace())->toBeTrue();
    });

    it('detects significant content', function (): void {
        $doc = $this->parse('  Hello  ');
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->hasSignificantContent())->toBeTrue()
            ->and($nodes[0]->asText()->isWhitespace())->toBeFalse();
    });

    it('counts leading newlines', function (): void {
        $doc = $this->parse("\n\nHello");
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asText())->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->countLeadingNewlines())->toBe(2);
    });

    it('counts trailing newlines', function (): void {
        $doc = $this->parse("Hello\n\n\n");
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asText())->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->countTrailingNewlines())->toBe(3);
    });

    it('returns zero for leading newlines when none exist', function (): void {
        $doc = $this->parse("Hello\n\n");
        $nodes = $doc->getChildren();

        expect($nodes[0]->asText()->countLeadingNewlines())->toBe(0);
    });

    it('returns zero for trailing newlines when none exist', function (): void {
        $doc = $this->parse("\n\nHello");
        $nodes = $doc->getChildren();

        expect($nodes[0]->asText()->countTrailingNewlines())->toBe(0);
    });

    it('gets trimmed content', function (): void {
        $doc = $this->parse("  \n  Hello World  \n  ");
        $nodes = $doc->getChildren();

        expect($nodes[0]->asText()->getTrimmedContent())->toBe('Hello World');
    });

    it('handles empty string', function (): void {
        $doc = $this->parse('');
        $nodes = $doc->getChildren();

        expect($nodes)->toBeEmpty();
    });

    it('caches trivia after first parse', function (): void {
        $doc = $this->parse('  Hello  ');
        $nodes = $doc->getChildren();
        $text = $nodes[0]->asText();

        $trivia1 = $text->getTrivia();
        $trivia2 = $text->getTrivia();

        expect($trivia1)->toBe($trivia2);
    });

    it('counts Unix newlines (\\n)', function (): void {
        $trivia = TriviaParser::parse("\n\nHello");
        expect($trivia[0]->getNewlineCount())->toBe(2);
    });

    it('counts Windows newlines (\\r\\n)', function (): void {
        $trivia = TriviaParser::parse("\r\n\r\nHello");
        expect($trivia[0]->getNewlineCount())->toBe(2);

        $trivia2 = TriviaParser::parse("Hello\r\n\r\n");
        expect($trivia2[1]->getNewlineCount())->toBe(2);
    });

    it('counts old Mac newlines (\\r)', function (): void {
        $trivia = TriviaParser::parse("\r\rHello");
        expect($trivia[0]->getNewlineCount())->toBe(2);

        $trivia2 = TriviaParser::parse("Hello\r\r");
        expect($trivia2[1]->getNewlineCount())->toBe(2);
    });

    it('counts mixed newline styles', function (): void {
        $trivia = TriviaParser::parse("\r\n\rHello\n");
        expect($trivia[0]->getNewlineCount())->toBe(2)
            ->and($trivia[2]->getNewlineCount())->toBe(1);
    });

    it('treats \\r\\n as single newline not two', function (): void {
        $trivia = TriviaParser::parse("\r\nHello");

        expect($trivia[0]->getNewlineCount())->toBe(1);
    });

    it('calculates trivia length', function (): void {
        $trivia = TriviaParser::parse('  Hello  ');

        expect($trivia[0]->getLength())->toBe(2)
            ->and($trivia[1]->getLength())->toBe(5)
            ->and($trivia[2]->getLength())->toBe(2);
    });

    it('calculates end offset', function (): void {
        $trivia = TriviaParser::parse('  Hello  ');

        expect($trivia[0]->getEndOffset())->toBe(2)
            ->and($trivia[1]->getEndOffset())->toBe(7)
            ->and($trivia[2]->getEndOffset())->toBe(9);
    });

    it('detects empty trivia', function (): void {
        $trivia = new Trivia(TriviaKind::Word, '', 0);
        expect($trivia->isEmpty())->toBeTrue();

        $trivia2 = new Trivia(TriviaKind::Word, 'x', 0);
        expect($trivia2->isEmpty())->toBeFalse();
    });

    it('detects newline-only trivia', function (): void {
        $trivia = new Trivia(TriviaKind::LeadingWhitespace, "\n\n", 0);
        expect($trivia->isNewlineOnly())->toBeTrue();

        $trivia2 = new Trivia(TriviaKind::LeadingWhitespace, "\r\n", 0);
        expect($trivia2->isNewlineOnly())->toBeTrue();

        $trivia3 = new Trivia(TriviaKind::LeadingWhitespace, " \n ", 0);
        expect($trivia3->isNewlineOnly())->toBeFalse();
    });

    it('detects space-only trivia', function (): void {
        $trivia = new Trivia(TriviaKind::LeadingWhitespace, '   ', 0);
        expect($trivia->isSpaceOnly())->toBeTrue();

        $trivia2 = new Trivia(TriviaKind::LeadingWhitespace, " \t ", 0);
        expect($trivia2->isSpaceOnly())->toBeFalse();
    });

    it('detects tab-only trivia', function (): void {
        $trivia = new Trivia(TriviaKind::LeadingWhitespace, "\t\t", 0);
        expect($trivia->isTabOnly())->toBeTrue();

        $trivia2 = new Trivia(TriviaKind::LeadingWhitespace, "\t \t", 0);
        expect($trivia2->isTabOnly())->toBeFalse();
    });

    it('detects mixed whitespace', function (): void {
        $trivia = new Trivia(TriviaKind::LeadingWhitespace, " \t", 0);
        expect($trivia->isMixedWhitespace())->toBeTrue();

        $trivia2 = new Trivia(TriviaKind::LeadingWhitespace, '   ', 0);
        expect($trivia2->isMixedWhitespace())->toBeFalse();

        $trivia3 = new Trivia(TriviaKind::Word, " \t", 0);
        expect($trivia3->isMixedWhitespace())->toBeFalse();
    });

    it('checks if trivia contains substring', function (): void {
        $trivia = new Trivia(TriviaKind::Word, 'Hello', 0);
        expect($trivia->contains('Hell'))->toBeTrue()
            ->and($trivia->contains('Foo'))->toBeFalse();
    });
});
