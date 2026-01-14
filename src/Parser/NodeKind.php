<?php

declare(strict_types=1);

namespace Forte\Parser;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class NodeKind
{
    public const EXTENSION_BASE = 256;

    public const Root = 0;

    public const Element = 1;

    public const Text = 2;

    public const Fragment = 3;

    public const Echo = 4;

    public const RawEcho = 5;

    public const TripleEcho = 6;

    public const Directive = 7;

    public const DirectiveBlock = 8;

    public const Verbatim = 9;

    public const PhpBlock = 10;

    public const PhpTag = 11;

    public const Comment = 12;

    public const BogusComment = 13;

    public const ConditionalComment = 14;

    public const BladeComment = 15;

    public const Doctype = 16;

    public const Cdata = 17;

    public const Decl = 18;

    public const Attribute = 19;

    public const JsxAttribute = 20;

    public const AttributeWhitespace = 25;

    public const UnpairedClosingTag = 21;

    public const NonOutput = 22;

    public const ElementName = 23;

    public const ClosingElementName = 24;

    public const AttributeName = 26;

    public const AttributeValue = 27;

    public const ProcessingInstruction = 28;

    /**
     * @var array<int, string>
     */
    private static array $names = [
        self::Root => 'Root',
        self::Element => 'Element',
        self::Text => 'Text',
        self::Fragment => 'Fragment',
        self::Echo => 'Echo',
        self::RawEcho => 'RawEcho',
        self::TripleEcho => 'TripleEcho',
        self::Directive => 'Directive',
        self::DirectiveBlock => 'DirectiveBlock',
        self::Verbatim => 'Verbatim',
        self::PhpBlock => 'PhpBlock',
        self::PhpTag => 'PhpTag',
        self::Comment => 'Comment',
        self::BogusComment => 'BogusComment',
        self::ConditionalComment => 'ConditionalComment',
        self::BladeComment => 'BladeComment',
        self::Doctype => 'Doctype',
        self::Cdata => 'Cdata',
        self::Decl => 'Decl',
        self::Attribute => 'Attribute',
        self::JsxAttribute => 'JsxAttribute',
        self::AttributeWhitespace => 'AttributeWhitespace',
        self::UnpairedClosingTag => 'UnpairedClosingTag',
        self::NonOutput => 'NonOutput',
        self::ElementName => 'ElementName',
        self::ClosingElementName => 'ClosingElementName',
        self::AttributeName => 'AttributeName',
        self::AttributeValue => 'AttributeValue',
        self::ProcessingInstruction => 'ProcessingInstruction',
    ];

    private const ALIASES = [
        'core::Root' => self::Root,
        'core::Element' => self::Element,
        'core::Text' => self::Text,
        'core::Fragment' => self::Fragment,
        'core::Echo' => self::Echo,
        'core::RawEcho' => self::RawEcho,
        'core::TripleEcho' => self::TripleEcho,
        'core::Directive' => self::Directive,
        'core::DirectiveBlock' => self::DirectiveBlock,
        'core::Verbatim' => self::Verbatim,
        'core::PhpBlock' => self::PhpBlock,
        'core::PhpTag' => self::PhpTag,
        'core::Comment' => self::Comment,
        'core::BogusComment' => self::BogusComment,
        'core::ConditionalComment' => self::ConditionalComment,
        'core::BladeComment' => self::BladeComment,
        'core::Doctype' => self::Doctype,
        'core::Cdata' => self::Cdata,
        'core::Decl' => self::Decl,
        'core::Attribute' => self::Attribute,
        'core::JsxAttribute' => self::JsxAttribute,
        'core::AttributeWhitespace' => self::AttributeWhitespace,
        'core::UnpairedClosingTag' => self::UnpairedClosingTag,
        'core::NonOutput' => self::NonOutput,
        'core::ElementName' => self::ElementName,
        'core::ClosingElementName' => self::ClosingElementName,
        'core::AttributeName' => self::AttributeName,
        'core::AttributeValue' => self::AttributeValue,
        'core::ProcessingInstruction' => self::ProcessingInstruction,
    ];

    /**
     * Get the human-readable name for a node kind.
     */
    public static function name(int $kind): string
    {
        if (isset(self::$names[$kind])) {
            return self::$names[$kind];
        }

        if (self::isExtension($kind)) {
            return app(NodeKindRegistry::class)->name($kind);
        }

        return "Unknown({$kind})";
    }

    /**
     * Check if a node matches a kind identifier.
     *
     * @param  string|int  $kind  Kind ID or string key
     *
     * @phpstan-param  FlatNode  $node
     */
    public static function is(array $node, string|int $kind): bool
    {
        if (is_int($kind)) {
            return $node['kind'] === $kind;
        }

        if (isset(self::ALIASES[$kind])) {
            return $node['kind'] === self::ALIASES[$kind];
        }

        return app(NodeKindRegistry::class)->matches($node, $kind);
    }

    /**
     * Check if a kind ID is a built-in kind.
     */
    public static function isBuiltin(int $kind): bool
    {
        return $kind >= 0 && $kind < self::EXTENSION_BASE;
    }

    /**
     * Check if a kind ID is an extension kind.
     */
    public static function isExtension(int $kind): bool
    {
        return $kind >= self::EXTENSION_BASE;
    }

    /**
     * Check if a node kind is a Blade echo node.
     */
    public static function isEcho(int $kind): bool
    {
        return $kind === self::Echo
            || $kind === self::RawEcho
            || $kind === self::TripleEcho;
    }

    /**
     * Check if a node kind is a directive node.
     */
    public static function isDirective(int $kind): bool
    {
        return $kind === self::Directive
            || $kind === self::DirectiveBlock;
    }

    /**
     * Check if a node kind is a comment node.
     */
    public static function isComment(int $kind): bool
    {
        return $kind === self::Comment
            || $kind === self::BogusComment
            || $kind === self::ConditionalComment
            || $kind === self::BladeComment;
    }

    /**
     * Check if a node kind is an attribute node.
     */
    public static function isAttribute(int $kind): bool
    {
        if ($kind === self::Attribute || $kind === self::JsxAttribute) {
            return true;
        }

        if (self::isExtension($kind)) {
            return app(NodeKindRegistry::class)->isAttributeKind($kind);
        }

        return false;
    }

    /**
     * Check if a node kind is a processing instruction.
     */
    public static function isProcessingInstruction(int $kind): bool
    {
        return $kind === self::ProcessingInstruction;
    }

    /**
     * Get all built-in node kinds.
     *
     * @return array<int, string>
     */
    public static function builtinKinds(): array
    {
        return self::$names;
    }
}
