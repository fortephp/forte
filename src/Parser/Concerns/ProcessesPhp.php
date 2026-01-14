<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;

trait ProcessesPhp
{
    protected function createPhpBlock(int $startPos): void
    {
        $this->createBlockNode($startPos, TokenType::PhpBlockEnd, NodeKind::PhpBlock);
    }

    protected function createPhpTag(int $startPos): void
    {
        $this->createBlockNode($startPos, TokenType::PhpTagEnd, NodeKind::PhpTag);
    }

    protected function processOrphanPhpBlockEnd(): void
    {
        $startPos = $this->pos;
        $this->pos++;

        $node = $this->createNode(
            kind: NodeKind::Directive,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: 1
        );
        $node['name'] = 'endphp';
        $node['args'] = null;

        $this->addChild($node);
    }
}
