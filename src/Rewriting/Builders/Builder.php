<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Ast\DirectiveNode;
use Forte\Traversal\Specs\or;

class Builder
{
    private function __construct() {}

    /**
     * Create an element spec.
     */
    public static function element(string $tagName): ElementBuilder
    {
        return new ElementBuilder($tagName);
    }

    /**
     * Create a text spec.
     */
    public static function text(string $content): TextBuilder
    {
        return new TextBuilder($content);
    }

    /**
     * Create an escaped echo spec: {{ $expr }}
     */
    public static function echo(string $expression): EchoBuilder
    {
        return new EchoBuilder($expression, EchoBuilder::TYPE_ESCAPED);
    }

    /**
     * Create a raw echo spec: {!! $expr !!}
     */
    public static function rawEcho(string $expression): EchoBuilder
    {
        return new EchoBuilder($expression, EchoBuilder::TYPE_RAW);
    }

    /**
     * Create a triple echo spec: {{{ $expr }}}
     */
    public static function tripleEcho(string $expression): EchoBuilder
    {
        return new EchoBuilder($expression, EchoBuilder::TYPE_TRIPLE);
    }

    /**
     * Create a directive spec: "@name" or "@name($args)"
     */
    public static function directive(string $name, ?string $arguments = null): DirectiveBuilder
    {
        return new DirectiveBuilder($name, $arguments);
    }

    /**
     * Create a directive spec with safe spacing.
     */
    public static function safeDirective(string $name, ?string $arguments = null): DirectiveBuilder
    {
        return DirectiveBuilder::safe($name, $arguments);
    }

    /**
     * Create a directive spec from an existing directive with modified arguments.
     */
    public static function directiveFrom(DirectiveNode $directive, ?string $newArguments = null): DirectiveBuilder
    {
        return DirectiveBuilder::fromDirective($directive, $newArguments);
    }

    /**
     * Create a PHP tag spec: <?php $code ?>
     */
    public static function phpTag(string $code, bool $hasClose = true): PhpTagBuilder
    {
        return PhpTagBuilder::php($code, $hasClose);
    }

    /**
     * Create an echo PHP tag spec: <?= $code ?>
     */
    public static function phpEchoTag(string $code, bool $hasClose = true): PhpTagBuilder
    {
        return PhpTagBuilder::echo($code, $hasClose);
    }

    /**
     * Create an HTML comment spec: <!-- content -->
     */
    public static function comment(string $content): CommentBuilder
    {
        return new CommentBuilder($content);
    }

    /**
     * Create a Blade comment spec: {{-- content --}}
     */
    public static function bladeComment(string $content): BladeCommentBuilder
    {
        return new BladeCommentBuilder($content);
    }

    /**
     * Create a raw source spec.
     */
    public static function raw(string $source): RawBuilder
    {
        return new RawBuilder($source);
    }

    /**
     * Normalize a mixed input to a NodeBuilder.
     */
    public static function normalize(NodeBuilder|string $input): NodeBuilder
    {
        if ($input instanceof NodeBuilder) {
            return $input;
        }

        return new RawBuilder($input);
    }

    /**
     * Normalize an array of mixed inputs to NodeBuilder array.
     *
     * @param  array<NodeBuilder|string>  $inputs
     * @return array<NodeBuilder>
     */
    public static function normalizeAll(array $inputs): array
    {
        return array_map(self::normalize(...), $inputs);
    }
}
