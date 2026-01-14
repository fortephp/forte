<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

beforeEach(function (): void {
    $this->directives = new Directives;
});

describe('XML Declaration Tokenization', function (): void {
    it('tokenizes basic XML declaration', function (): void {
        $source = '<?xml version="1.0"?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(8)
            ->and($result->tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Whitespace)
            ->and($result->tokens[2]['type'])->toBe(TokenType::AttributeName)
            ->and($result->tokens[3]['type'])->toBe(TokenType::Equals)
            ->and($result->tokens[4]['type'])->toBe(TokenType::Quote)
            ->and($result->tokens[5]['type'])->toBe(TokenType::AttributeValue)
            ->and($result->tokens[6]['type'])->toBe(TokenType::Quote)
            ->and($result->tokens[7]['type'])->toBe(TokenType::DeclEnd)
            ->and(substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']))
            ->toBe('<?xml')
            ->and(substr($source, $result->tokens[2]['start'], $result->tokens[2]['end'] - $result->tokens[2]['start']))
            ->toBe('version')
            ->and(substr($source, $result->tokens[5]['start'], $result->tokens[5]['end'] - $result->tokens[5]['start']))
            ->toBe('1.0')
            ->and(substr($source, $result->tokens[7]['start'], $result->tokens[7]['end'] - $result->tokens[7]['start']))
            ->toBe('?>');

    });

    it('tokenizes XML declaration with encoding', function (): void {
        $source = '<?xml version="1.0" encoding="UTF-8"?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(14)
            ->and($result->tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($result->tokens[13]['type'])->toBe(TokenType::DeclEnd)
            ->and(substr($source, $result->tokens[2]['start'], $result->tokens[2]['end'] - $result->tokens[2]['start']))
            ->toBe('version')
            ->and(substr($source, $result->tokens[8]['start'], $result->tokens[8]['end'] - $result->tokens[8]['start']))
            ->toBe('encoding');
    });

    it('tokenizes XML declaration with standalone attribute', function (): void {
        $source = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::DeclEnd);
    });

    it('tokenizes empty XML declaration', function (): void {
        $source = '<?xml?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::DeclEnd);
    });

    it('handles unclosed XML declaration', function (): void {
        $source = '<?xml version="1.0"';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(8)
            ->and($result->tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::SyntheticClose);
    });

    it('tokenizes XML declaration case-insensitively', function (): void {
        $source = '<?XML version="1.0"?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(8)
            ->and($result->tokens[0]['type'])->toBe(TokenType::DeclStart);
    });

    it('does not confuse PHP tag with XML declaration', function (): void {
        $source = '<?php echo "test"; ?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PhpTagStart);
    });

    it('does not match xmlfoo as XML declaration', function (): void {
        $source = '<?xmlfoo?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PIStart);
    });

    it('tokenizes XML declaration followed by content', function (): void {
        $source = '<?xml version="1.0"?><root>content</root>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($result->tokens[7]['type'])->toBe(TokenType::DeclEnd)
            ->and($result->tokens[8]['type'])->toBe(TokenType::LessThan);
    });

    it('tokenizes XML declaration with newlines', function (): void {
        $source = "<?xml\nversion=\"1.0\"\nencoding=\"UTF-8\"?>";
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(14)
            ->and($result->tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($result->tokens[13]['type'])->toBe(TokenType::DeclEnd);
    });
});
