<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\DirectiveNode;
use Forte\Parser\ArgsParser;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Illuminate\Support\Str;

class HoistDirectiveArgumentsRewriter extends Visitor
{
    /** @var array<string> */
    protected array $directives;

    /**
     * @param  array<string>|null  $directives  Directive names to process
     */
    public function __construct(?array $directives = null)
    {
        $directives ??= ['json'];
        $this->directives = array_map(strtolower(...), $directives);
    }

    public function enter(NodePath $path): void
    {
        $node = $path->node();

        if (! $node instanceof DirectiveNode) {
            return;
        }

        $name = $node->nameText();

        if (! in_array($name, $this->directives, true)) {
            return;
        }

        $this->hoistFirstArgument($node, $path);
    }

    protected function hoistFirstArgument(DirectiveNode $directive, NodePath $path): void
    {
        $argString = $directive->arguments();
        if ($argString === null) {
            return;
        }

        $args = ArgsParser::parseArgs($argString);
        if (empty($args)) {
            return;
        }

        $jsonContent = array_shift($args);
        $tmpVar = '$__tmpVar'.Str::random();

        $setTmpVariable = Builder::phpTag(" {$tmpVar} = {$jsonContent};");
        $unsetTmpVariable = Builder::phpTag(" unset({$tmpVar});");

        array_unshift($args, $tmpVar);
        $newArgsString = '('.implode(', ', $args).')';

        $path->surroundWith(
            $setTmpVariable,
            Builder::directiveFrom($directive, $newArgsString),
            $unsetTmpVariable
        );
    }
}
