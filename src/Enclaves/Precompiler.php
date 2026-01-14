<?php

declare(strict_types=1);

namespace Forte\Enclaves;

use Forte\Ast\Document\Document;
use Forte\Rewriting\Rewriter;

readonly class Precompiler
{
    public function __construct(private EnclavesManager $enclavesRegistry) {}

    public function compile(string $source, string $path): string
    {
        $rewriters = $this->enclavesRegistry->getRewritersForPath($path, fn ($class) => app($class));

        if (! $rewriters) {
            return $source;
        }

        $doc = Document::parse(
            $source,
            $this->enclavesRegistry->getParserOptionsForPath($path)
        );

        $rewriter = new Rewriter;
        foreach ($rewriters as $pass) {
            $rewriter->addVisitor($pass);
        }

        return $rewriter->rewrite($doc)->render();
    }
}
