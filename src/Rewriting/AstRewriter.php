<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\Document\Document;

interface AstRewriter
{
    public function rewrite(Document $doc): Document;
}
