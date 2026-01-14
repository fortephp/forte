<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

beforeEach(function (): void {
    $this->directives = new Directives;
});

describe('Processing Instruction Tokenization', function (): void {
    it('tokenizes basic processing instruction', function (): void {
        $source = '<?xml-stylesheet href="style.xsl" type="text/xsl"?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[2]['type'])->toBe(TokenType::PIEnd);

        $piStart = substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']);
        expect($piStart)->toBe('<?xml-stylesheet');

        $content = substr($source, $result->tokens[1]['start'], $result->tokens[1]['end'] - $result->tokens[1]['start']);
        expect($content)->toBe(' href="style.xsl" type="text/xsl"')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('tokenizes PI with no data', function (): void {
        $source = '<?target?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::PIEnd);
        // PIStart, PIEnd (no Text)

        $piStart = substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']);
        expect($piStart)->toBe('<?target')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('tokenizes PI with whitespace before closing', function (): void {
        $source = '<?target data ?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[2]['type'])->toBe(TokenType::PIEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('distinguishes PI from bogus comment with space', function (): void {
        $source = '<? invalid>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::BogusComment);
    });

    it('distinguishes PI from PHP tag', function (): void {
        $source = '<?php echo "test"; ?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PhpTagStart);
    });

    it('distinguishes PI from short echo tag', function (): void {
        $source = '<?= $var ?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PhpTagStart);
    });

    it('distinguishes PI from XML declaration', function (): void {
        $source = '<?xml version="1.0"?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::DeclStart);
    });

    it('handles PI target with hyphen', function (): void {
        $source = '<?my-processor data?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PIStart);
        $piStart = substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']);
        expect($piStart)->toBe('<?my-processor');
    });

    it('handles PI target with underscore', function (): void {
        $source = '<?my_processor data?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PIStart);
        $piStart = substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']);
        expect($piStart)->toBe('<?my_processor');
    });

    it('handles PI target with colon (namespaced)', function (): void {
        $source = '<?ns:processor data?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PIStart);
        $piStart = substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']);
        expect($piStart)->toBe('<?ns:processor');
    });

    it('handles PI target with digits', function (): void {
        $source = '<?processor123 data?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PIStart);
        $piStart = substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']);
        expect($piStart)->toBe('<?processor123');
    });

    it('rejects PI target starting with digit', function (): void {
        $source = '<?123invalid?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::BogusComment);
    });

    it('handles unclosed PI at EOF', function (): void {
        $source = '<?target data';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->errors)->toHaveCount(1);
    });

    it('tokenizes multiple PIs', function (): void {
        $source = '<?pi1 data?><?pi2 data?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(6)
            ->and($result->tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[3]['type'])->toBe(TokenType::PIStart)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('tokenizes PI with surrounding content', function (): void {
        $source = 'text<?pi data?>more';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[0], $source))->toBe('text')
            ->and($result->tokens[1]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[4]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[4], $source))->toBe('more')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('handles PI with multiline content', function (): void {
        $source = "<?pi\nmultiline\ndata?>";
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[2]['type'])->toBe(TokenType::PIEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('handles xml-stylesheet PI', function (): void {
        $source = '<?xml-stylesheet type="text/xsl" href="transform.xsl"?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::PIStart);

        $piStart = substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']);

        expect($piStart)->toBe('<?xml-stylesheet')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('handles PI with special characters in data', function (): void {
        $source = '<?pi data="value" attr=\'single\' special!@#$%^&*()?>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($result->tokens[2]['type'])->toBe(TokenType::PIEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
