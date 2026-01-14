<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Parser\NodeKind;

class EchoNode extends Node
{
    private ?string $cachedExpression = null;

    /**
     * Get the content inside the echo.
     */
    public function content(): string
    {
        $raw = $this->getDocumentContent();

        return match ($this->kind()) {
            NodeKind::Echo => $this->stripBraces($raw, '{{', '}}'),
            NodeKind::RawEcho => $this->stripBraces($raw, '{!!', '!!}'),
            NodeKind::TripleEcho => $this->stripBraces($raw, '{{{', '}}}'),
            default => $raw,
        };
    }

    /**
     * Get the expression inside the echo (without braces, trimmed).
     */
    public function expression(): string
    {
        if ($this->cachedExpression !== null) {
            return $this->cachedExpression;
        }

        $this->cachedExpression = trim($this->content());

        return $this->cachedExpression;
    }

    /**
     * Check if this is an escaped echo ({{ }}).
     */
    public function isEscaped(): bool
    {
        return $this->kind() === NodeKind::Echo;
    }

    /**
     * Check if this is a raw echo ({!! !!}).
     */
    public function isRaw(): bool
    {
        return $this->kind() === NodeKind::RawEcho;
    }

    /**
     * Check if this is a triple echo ({{{ }}}).
     */
    public function isTriple(): bool
    {
        return $this->kind() === NodeKind::TripleEcho;
    }

    public function echoType(): string
    {
        return match ($this->kind()) {
            NodeKind::Echo => EchoType::ESCAPED,
            NodeKind::RawEcho => EchoType::RAW,
            NodeKind::TripleEcho => EchoType::TRIPLE,
            default => EchoType::UNKNOWN,
        };
    }

    private function stripBraces(string $content, string $open, string $close): string
    {
        if (str_starts_with($content, $open)) {
            $content = substr($content, strlen($open));
        }
        if (str_ends_with($content, $close)) {
            $content = substr($content, 0, -strlen($close));
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'echo';
        $data['echo_type'] = $this->echoType();
        $data['expression'] = $this->expression();
        $data['inner_content'] = $this->content();
        $data['is_escaped'] = $this->isEscaped();
        $data['is_raw'] = $this->isRaw();
        $data['is_triple'] = $this->isTriple();

        return $data;
    }
}
