<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\DirectiveHelper;

trait ProcessesConditionalPairingDirectives
{
    protected function shouldPairDirective(string $directiveName, int $startPos, int $tokenCount): bool
    {
        $strategy = $this->directives->getPairingStrategy($directiveName);

        if ($strategy === null) {
            return false;
        }

        return match ($strategy) {
            'lang_style' => $this->shouldPairLangStyle($directiveName, $startPos, $tokenCount),
            'section_style' => $this->shouldPairSectionStyle($directiveName, $startPos, $tokenCount),
            default => false,
        };
    }

    protected function shouldPairLangStyle(string $directiveName, int $startPos, int $tokenCount): bool
    {
        $argsInfo = DirectiveHelper::checkDirectiveArgs(
            $this->tokens,
            $this->source,
            $startPos + 1,
            $startPos + $tokenCount
        );

        // If no args or array args (second char is '['), check for terminator
        if (! $argsInfo['hasArgs'] || DirectiveHelper::argsStartWithArray($argsInfo['argsContent'] ?? '')) {
            // Look ahead for @endlang
            return DirectiveHelper::findMatchingTerminator(
                $directiveName,
                $this->tokens,
                $this->source,
                $startPos + $tokenCount,
                count($this->tokens),
                $this->directives,
                PHP_INT_MAX,
                $this->directiveIndex()
            ) !== null;
        }

        return false;
    }

    protected function shouldPairSectionStyle(string $directiveName, int $startPos, int $tokenCount): bool
    {
        $argCount = 0;

        for ($i = $startPos; $i < $startPos + $tokenCount && $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];

            if ($token['type'] === TokenType::DirectiveArgs) {
                $content = substr($this->source, $token['start'], $token['end'] - $token['start']);
                $argCount = DirectiveHelper::countDirectiveArgs($content);
                break;
            }
        }

        if ($argCount >= 2) {
            return false;
        }

        return DirectiveHelper::findMatchingTerminator(
            $directiveName,
            $this->tokens,
            $this->source,
            $startPos + $tokenCount,
            count($this->tokens),
            $this->directives,
            PHP_INT_MAX,
            $this->directiveIndex()
        ) !== null;
    }

    protected function processConditionalPairingDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if ($this->shouldPairDirective($directiveName, $startPos, $tokenCount)) {
            $this->openPairedDirective(
                $directiveName,
                $startPos,
                $tokenCount,
                $argsContent
            );

            return;
        }

        $this->createStandaloneDirective(
            $directiveName,
            $startPos,
            $tokenCount,
            $argsContent
        );
    }
}
