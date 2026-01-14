<?php

declare(strict_types=1);

namespace Forte\Lexer\Tokens;

class TokenType
{
    public const Text = 0;

    public const LessThan = 1;

    public const GreaterThan = 2;

    public const Slash = 3;

    public const Equals = 4;

    public const TagName = 5;

    public const AttributeName = 6;

    public const AttributeValue = 7;

    public const Quote = 8;

    public const Whitespace = 9;

    public const CommentStart = 10;

    public const CommentEnd = 11;

    public const BogusComment = 30;

    public const ConditionalCommentStart = 31;

    public const ConditionalCommentEnd = 32;

    public const BladeCommentStart = 39;

    public const BladeCommentEnd = 40;

    public const DoctypeStart = 12;

    public const Doctype = 13;

    public const DoctypeEnd = 14;

    public const AtSign = 15;

    public const EchoStart = 16;

    public const EchoEnd = 17;

    public const EchoContent = 18;

    public const RawEchoStart = 19;

    public const RawEchoEnd = 20;

    public const TripleEchoStart = 21;

    public const TripleEchoEnd = 22;

    public const Directive = 23;

    public const DirectiveArgs = 24;

    public const VerbatimStart = 25;

    public const VerbatimEnd = 26;

    public const JsxAttributeValue = 27;

    public const JsxShorthandAttribute = 28;

    public const TsxGenericType = 29;

    public const PhpBlockStart = 33;

    public const PhpBlockEnd = 34;

    public const PhpBlock = 35;

    public const PhpTagStart = 36;

    public const PhpTagEnd = 37;

    public const PhpContent = 38;

    public const SyntheticClose = 41;

    public const EscapedAttribute = 42;   // ::name - double colon prefix

    public const BoundAttribute = 43;     // :name - single colon prefix

    public const ShorthandAttribute = 44; // :$name - colon with $ prefix

    public const CdataStart = 45;

    public const CdataEnd = 46;

    public const DeclStart = 47;

    public const DeclEnd = 48;

    public const PIStart = 49;

    public const PIEnd = 50;

    public const EXTENSION_BASE = 128;

    public const EXTENSION_MAX = 255;

    private const LABELS = [
        self::Text => 'Text',
        self::LessThan => 'LessThan',
        self::GreaterThan => 'GreaterThan',
        self::Slash => 'Slash',
        self::Equals => 'Equals',
        self::TagName => 'TagName',
        self::AttributeName => 'AttributeName',
        self::AttributeValue => 'AttributeValue',
        self::Quote => 'Quote',
        self::Whitespace => 'Whitespace',
        self::CommentStart => 'CommentStart',
        self::CommentEnd => 'CommentEnd',
        self::BogusComment => 'BogusComment',
        self::ConditionalCommentStart => 'ConditionalCommentStart',
        self::ConditionalCommentEnd => 'ConditionalCommentEnd',
        self::BladeCommentStart => 'BladeCommentStart',
        self::BladeCommentEnd => 'BladeCommentEnd',
        self::DoctypeStart => 'DoctypeStart',
        self::Doctype => 'Doctype',
        self::DoctypeEnd => 'DoctypeEnd',
        self::AtSign => 'AtSign',
        self::EchoStart => 'EchoStart',
        self::EchoEnd => 'EchoEnd',
        self::EchoContent => 'EchoContent',
        self::RawEchoStart => 'RawEchoStart',
        self::RawEchoEnd => 'RawEchoEnd',
        self::TripleEchoStart => 'TripleEchoStart',
        self::TripleEchoEnd => 'TripleEchoEnd',
        self::Directive => 'Directive',
        self::DirectiveArgs => 'DirectiveArgs',
        self::VerbatimStart => 'VerbatimStart',
        self::VerbatimEnd => 'VerbatimEnd',
        self::JsxAttributeValue => 'JsxAttributeValue',
        self::JsxShorthandAttribute => 'JsxShorthandAttribute',
        self::TsxGenericType => 'TsxGenericType',
        self::PhpBlockStart => 'PhpBlockStart',
        self::PhpBlockEnd => 'PhpBlockEnd',
        self::PhpBlock => 'PhpBlock',
        self::PhpTagStart => 'PhpTagStart',
        self::PhpTagEnd => 'PhpTagEnd',
        self::PhpContent => 'PhpContent',
        self::SyntheticClose => 'SyntheticClose',
        self::EscapedAttribute => 'EscapedAttribute',
        self::BoundAttribute => 'BoundAttribute',
        self::ShorthandAttribute => 'ShorthandAttribute',
        self::CdataStart => 'CdataStart',
        self::CdataEnd => 'CdataEnd',
        self::DeclStart => 'DeclStart',
        self::DeclEnd => 'DeclEnd',
        self::PIStart => 'PIStart',
        self::PIEnd => 'PIEnd',
    ];

    /**
     * String aliases for built-in token types.
     * Format: "core:TypeName" => integer constant
     */
    private const ALIASES = [
        'core:Text' => self::Text,
        'core:LessThan' => self::LessThan,
        'core:GreaterThan' => self::GreaterThan,
        'core:Slash' => self::Slash,
        'core:Equals' => self::Equals,
        'core:TagName' => self::TagName,
        'core:AttributeName' => self::AttributeName,
        'core:AttributeValue' => self::AttributeValue,
        'core:Quote' => self::Quote,
        'core:Whitespace' => self::Whitespace,
        'core:CommentStart' => self::CommentStart,
        'core:CommentEnd' => self::CommentEnd,
        'core:BogusComment' => self::BogusComment,
        'core:ConditionalCommentStart' => self::ConditionalCommentStart,
        'core:ConditionalCommentEnd' => self::ConditionalCommentEnd,
        'core:BladeCommentStart' => self::BladeCommentStart,
        'core:BladeCommentEnd' => self::BladeCommentEnd,
        'core:DoctypeStart' => self::DoctypeStart,
        'core:Doctype' => self::Doctype,
        'core:DoctypeEnd' => self::DoctypeEnd,
        'core:AtSign' => self::AtSign,
        'core:EchoStart' => self::EchoStart,
        'core:EchoEnd' => self::EchoEnd,
        'core:EchoContent' => self::EchoContent,
        'core:RawEchoStart' => self::RawEchoStart,
        'core:RawEchoEnd' => self::RawEchoEnd,
        'core:TripleEchoStart' => self::TripleEchoStart,
        'core:TripleEchoEnd' => self::TripleEchoEnd,
        'core:Directive' => self::Directive,
        'core:DirectiveArgs' => self::DirectiveArgs,
        'core:VerbatimStart' => self::VerbatimStart,
        'core:VerbatimEnd' => self::VerbatimEnd,
        'core:JsxAttributeValue' => self::JsxAttributeValue,
        'core:JsxShorthandAttribute' => self::JsxShorthandAttribute,
        'core:TsxGenericType' => self::TsxGenericType,
        'core:PhpBlockStart' => self::PhpBlockStart,
        'core:PhpBlockEnd' => self::PhpBlockEnd,
        'core:PhpBlock' => self::PhpBlock,
        'core:PhpTagStart' => self::PhpTagStart,
        'core:PhpTagEnd' => self::PhpTagEnd,
        'core:PhpContent' => self::PhpContent,
        'core:SyntheticClose' => self::SyntheticClose,
        'core:EscapedAttribute' => self::EscapedAttribute,
        'core:BoundAttribute' => self::BoundAttribute,
        'core:ShorthandAttribute' => self::ShorthandAttribute,
        'core:CdataStart' => self::CdataStart,
        'core:CdataEnd' => self::CdataEnd,
        'core:DeclStart' => self::DeclStart,
        'core:DeclEnd' => self::DeclEnd,
        'core:PIStart' => self::PIStart,
        'core:PIEnd' => self::PIEnd,
    ];

    public static function label(int $type): string
    {
        if (isset(self::LABELS[$type])) {
            return self::LABELS[$type];
        }

        if (self::isExtension($type)) {
            return app(TokenTypeRegistry::class)->label($type);
        }

        return "Unknown({$type})";
    }

    /**
     * Check if a token matches a type identifier.
     *
     * @param  array{type: int, start: int, end: int}  $token  The token
     * @param  string|int  $type  Type ID or string key
     */
    public static function is(array $token, string|int $type): bool
    {
        if (is_int($type)) {
            return $token['type'] === $type;
        }

        if (isset(self::ALIASES[$type])) {
            return $token['type'] === self::ALIASES[$type];
        }

        return app(TokenTypeRegistry::class)->matches($token, $type);
    }

    /**
     * Check if a type ID is a built-in type.
     */
    public static function isBuiltin(int $type): bool
    {
        return $type >= 0 && $type < self::EXTENSION_BASE;
    }

    /**
     * Check if a type ID is an extension type.
     */
    public static function isExtension(int $type): bool
    {
        return $type >= self::EXTENSION_BASE && $type <= self::EXTENSION_MAX;
    }

    /**
     * Get all built-in token types.
     *
     * @return array<int, string>
     */
    public static function builtinTypes(): array
    {
        return self::LABELS;
    }

    private function __construct() {}
}
