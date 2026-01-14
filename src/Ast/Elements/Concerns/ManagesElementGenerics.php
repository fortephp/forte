<?php

declare(strict_types=1);

namespace Forte\Ast\Elements\Concerns;

use Forte\Lexer\Tokens\TokenType;

trait ManagesElementGenerics
{
    /**
     * Get the generic type arguments.
     */
    public function genericTypeArguments(): ?string
    {
        $flat = $this->flat();
        $genericOffset = $flat['genericOffset'] ?? 0;

        if ($genericOffset <= 0) {
            return null;
        }

        $tokens = $this->document->getTokens();
        $source = $this->document->source();

        $genericTokenIdx = $flat['tokenStart'] + $genericOffset - 1;
        if ($genericTokenIdx >= count($tokens)) {
            return null;
        }

        $genericToken = $tokens[$genericTokenIdx];
        if ($genericToken['type'] !== TokenType::TsxGenericType) {
            return null;
        }

        return substr(
            (string) $source,
            $genericToken['start'],
            $genericToken['end'] - $genericToken['start']
        );
    }
}
