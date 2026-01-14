<?php

declare(strict_types=1);

namespace Forte\Ast\Trivia;

class TriviaParser
{
    /**
     * @return array<int, Trivia>
     */
    public static function parse(string $content): array
    {
        $trivia = [];
        $offset = 0;

        if (preg_match('/^(\s+)/', $content, $matches)) {
            $trivia[] = new Trivia(
                TriviaKind::LeadingWhitespace,
                $matches[1],
                $offset
            );
            $offset += strlen($matches[1]);
        }

        $trimmed = trim($content);
        if ($trimmed !== '') {
            $contentStart = strpos($content, $trimmed[0]);
            if ($contentStart === false) {
                $contentStart = 0;
            }
            $contentEnd = strrpos($content, $trimmed[strlen($trimmed) - 1]) + 1;
            $contentPart = substr($content, $contentStart, $contentEnd - $contentStart);

            $parts = preg_split('/(\s+)/', $contentPart, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if ($parts === false) {
                $parts = [];
            }

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                if (preg_match('/^\s+$/', $part)) {
                    $trivia[] = new Trivia(
                        TriviaKind::InnerWhitespace,
                        $part,
                        $contentStart + $offset - $contentStart
                    );
                } else {
                    $trivia[] = new Trivia(
                        TriviaKind::Word,
                        $part,
                        $contentStart + $offset - $contentStart
                    );
                }
                $offset += strlen($part);
            }
        }

        if ($offset < strlen($content) && preg_match('/(\s+)$/', $content, $matches)) {
            $trivia[] = new Trivia(
                TriviaKind::TrailingWhitespace,
                $matches[1],
                $offset
            );
        }

        return $trivia;
    }
}
