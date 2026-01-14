<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\EchoNode;

class ModifierSyntaxParser
{
    public function process(Document $doc): void
    {
        $echoNodes = $doc->findAll(fn ($n) => $n instanceof EchoNode);

        foreach ($echoNodes as $echoNode) {
            $content = $echoNode->expression();

            if (! str_contains((string) $content, '|')) {
                continue;
            }

            $parsed = $this->parseModifierSyntax($content);
            if ($parsed !== null) {
                $echoNode->setData('modifier_syntax', $parsed);
            }
        }
    }

    /**
     * @return array{expression: string, modifiers: array}|null
     */
    public function parseModifierSyntax(string $content): ?array
    {
        $content = trim($content);

        $parts = $this->splitByPipe($content);

        if (count($parts) < 2) {
            return null;
        }

        $expression = trim((string) array_shift($parts));

        $modifiers = [];
        foreach ($parts as $modifierString) {
            $modifier = $this->parseModifier(trim((string) $modifierString));
            if ($modifier !== null) {
                $modifiers[] = $modifier;
            }
        }

        return [
            'expression' => $expression,
            'modifiers' => $modifiers,
        ];
    }

    private function splitByPipe(string $content): array
    {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $depth = 0;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $content[$i - 1] !== '\\')) {
                if (! $inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
                $current .= $char;

                continue;
            }

            if (! $inQuotes) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
            }

            if ($char === '|' && ! $inQuotes && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * @return array{name: string, arguments: array}|null
     */
    private function parseModifier(string $modifierString): ?array
    {
        if (! str_contains($modifierString, '(')) {
            return [
                'name' => $modifierString,
                'arguments' => [],
            ];
        }

        if (! preg_match('/^(\w+)\((.*)\)$/', $modifierString, $matches)) {
            return null; // Invalid syntax
        }

        $name = $matches[1];
        $argsString = $matches[2];

        $arguments = $this->parseArguments($argsString);

        return [
            'name' => $name,
            'arguments' => $arguments,
        ];
    }

    private function parseArguments(string $argsString): array
    {
        if (trim($argsString) === '') {
            return [];
        }

        $arguments = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $length = strlen($argsString);

        for ($i = 0; $i < $length; $i++) {
            $char = $argsString[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                if (! $inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
                $current .= $char;

                continue;
            }

            if ($char === ',' && ! $inQuotes) {
                $arguments[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $arguments[] = trim($current);
        }

        return $arguments;
    }
}

function parseWithModifiers(string $template): Document
{
    $doc = Document::parse($template);
    $parser = new ModifierSyntaxParser;
    $parser->process($doc);

    return $doc;
}

describe('Modifier Syntax Parser', function (): void {
    it('parses simple modifier syntax', function (): void {
        $doc = parseWithModifiers('{{ $variable | upper }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();

        expect($echoNode)->toBeInstanceOf(EchoNode::class)
            ->and($echoNode->hasData('modifier_syntax'))->toBeTrue();

        $data = $echoNode->getData('modifier_syntax');

        expect($data)->toBeArray()
            ->and($data['expression'])->toBe('$variable')
            ->and($data['modifiers'])->toHaveCount(1)
            ->and($data['modifiers'][0]['name'])->toBe('upper')
            ->and($data['modifiers'][0]['arguments'])->toBe([]);
    });

    it('parses chained modifiers', function (): void {
        $doc = parseWithModifiers('{{ $name | lower | ucfirst }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$name')
            ->and($data['modifiers'])->toHaveCount(2)
            ->and($data['modifiers'][0]['name'])->toBe('lower')
            ->and($data['modifiers'][0]['arguments'])->toBe([])
            ->and($data['modifiers'][1]['name'])->toBe('ucfirst')
            ->and($data['modifiers'][1]['arguments'])->toBe([]);
    });

    it('parses modifiers with single argument', function (): void {
        $doc = parseWithModifiers('{{ $text | slice(0, 10) }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$text')
            ->and($data['modifiers'])->toHaveCount(1)
            ->and($data['modifiers'][0]['name'])->toBe('slice')
            ->and($data['modifiers'][0]['arguments'])->toHaveCount(2)
            ->and($data['modifiers'][0]['arguments'][0])->toBe('0')
            ->and($data['modifiers'][0]['arguments'][1])->toBe('10');
    });

    it('parses modifiers with string arguments', function (): void {
        $doc = parseWithModifiers('{{ $value | default("N/A") }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$value')
            ->and($data['modifiers'])->toHaveCount(1)
            ->and($data['modifiers'][0]['name'])->toBe('default')
            ->and($data['modifiers'][0]['arguments'])->toHaveCount(1)
            ->and($data['modifiers'][0]['arguments'][0])->toBe('"N/A"');
    });

    it('handles mixed simple and complex modifiers', function (): void {
        $doc = parseWithModifiers('{{ $data | default("None") | upper | limit(50) }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$data')
            ->and($data['modifiers'])->toHaveCount(3)
            ->and($data['modifiers'][0]['name'])->toBe('default')
            ->and($data['modifiers'][0]['arguments'])->toBe(['"None"'])
            ->and($data['modifiers'][1]['name'])->toBe('upper')
            ->and($data['modifiers'][1]['arguments'])->toBe([])
            ->and($data['modifiers'][2]['name'])->toBe('limit')
            ->and($data['modifiers'][2]['arguments'])->toBe(['50']);
    });

    it('handles modifiers with multiple arguments', function (): void {
        $doc = parseWithModifiers('{{ $text | replace("old", "new") }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$text')
            ->and($data['modifiers'])->toHaveCount(1)
            ->and($data['modifiers'][0]['name'])->toBe('replace')
            ->and($data['modifiers'][0]['arguments'])->toHaveCount(2)
            ->and($data['modifiers'][0]['arguments'][0])->toBe('"old"')
            ->and($data['modifiers'][0]['arguments'][1])->toBe('"new"');
    });

    it('handles whitespace variations', function (): void {
        $doc = parseWithModifiers('{{  $var  |  upper  |  trim  }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$var')
            ->and($data['modifiers'])->toHaveCount(2)
            ->and($data['modifiers'][0]['name'])->toBe('upper')
            ->and($data['modifiers'][1]['name'])->toBe('trim');
    });

    it('does not modify echo nodes without modifiers', function (): void {
        $doc = parseWithModifiers('{{ $variable }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();

        expect($echoNode->hasData('modifier_syntax'))->toBeFalse();
    });

    it('handles complex expressions with modifiers', function (): void {
        $doc = parseWithModifiers('{{ $user->name ?? "Guest" | upper }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$user->name ?? "Guest"')
            ->and($data['modifiers'])->toHaveCount(1)
            ->and($data['modifiers'][0]['name'])->toBe('upper');
    });

    it('handles single quotes in arguments', function (): void {
        $doc = parseWithModifiers("{{ \$value | default('Unknown') }}");
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();
        $data = $echoNode->getData('modifier_syntax');

        expect($data['expression'])->toBe('$value')
            ->and($data['modifiers'][0]['arguments'][0])->toBe("'Unknown'");
    });

    it('processes multiple echo nodes independently', function (): void {
        $doc = parseWithModifiers('{{ $first | upper }} and {{ $second | lower }}');
        $echoNodes = $doc->getEchoes();

        expect($echoNodes)->toHaveCount(2);

        $firstData = $echoNodes[0]->getData('modifier_syntax');
        $secondData = $echoNodes[1]->getData('modifier_syntax');

        expect($firstData['expression'])->toBe('$first')
            ->and($firstData['modifiers'][0]['name'])->toBe('upper')
            ->and($secondData['expression'])->toBe('$second')
            ->and($secondData['modifiers'][0]['name'])->toBe('lower');
    });

    it('gets and sets node data', function (): void {
        $doc = parseWithModifiers('{{ $var | test }}');
        $echoNode = $doc->firstChildOfType(EchoNode::class)->asEcho();

        expect($echoNode->hasData('modifier_syntax'))->toBeTrue()
            ->and($echoNode->hasData('nonexistent'))->toBeFalse();

        $data = $echoNode->getData('modifier_syntax');
        expect($data)->toBeArray()
            ->and($data)->toHaveKeys(['expression', 'modifiers']);

        $echoNode->setData('custom_key', 'custom_value');
        expect($echoNode->getData('custom_key'))->toBe('custom_value')
            ->and($echoNode->getData('modifier_syntax'))->toBe($data);
    });
});
