<?php

declare(strict_types=1);

namespace Forte\Querying;

class XPathException extends \RuntimeException
{
    public function __construct(private readonly string $expression, string $message, ?\Throwable $previous = null)
    {
        parent::__construct("XPath error in '{$this->expression}': {$message}", 0, $previous);
    }

    /**
     * Get the XPath expression that caused the error.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }
}
