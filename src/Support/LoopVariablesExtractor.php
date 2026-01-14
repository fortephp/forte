<?php

declare(strict_types=1);

namespace Forte\Support;

class LoopVariablesExtractor
{
    /**
     * Extracts information about the loop variables in the provided value.
     *
     * @param  string  $value  The loop expression content (e.g., "$items as $item")
     */
    public function extractDetails(string $value): LoopVariables
    {
        $result = new LoopVariables;
        $result->source = $value;

        $value = StringUtilities::unwrapParentheses($value);
        $split = WhitespaceStringSplitter::splitString($value);

        $asKeywordLocation = null;

        for ($i = 0; $i < count($split); $i++) {
            if (mb_strtolower($split[$i]) === 'as') {
                $asKeywordLocation = $i;
                break;
            }
        }

        if ($asKeywordLocation === null) {
            $result->isValid = false;

            return $result;
        }

        $alias = implode(' ', array_slice($split, $asKeywordLocation + 1));
        $variable = implode(' ', array_slice($split, 0, $asKeywordLocation));

        if (mb_strlen($alias) > 0 && mb_strlen($variable) > 0) {
            $result->isValid = true;
            $result->alias = $alias;
            $result->variable = $variable;
        }

        return $result;
    }
}
