<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

use Forte\Ast\BladeCommentNode;
use Forte\Ast\Components\ComponentNode;
use Forte\Ast\Components\SlotNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\DoctypeNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\BogusCommentNode;
use Forte\Ast\Elements\CdataNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ConditionalCommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Elements\StrayClosingTagNode;
use Forte\Ast\EscapeNode;
use Forte\Ast\GenericNode;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;
use Forte\Ast\VerbatimNode;
use Forte\Ast\XmlDeclarationNode;
use Forte\Parser\NodeKind;

trait CastsTypes
{
    public function isText(): bool
    {
        return $this->kind() === NodeKind::Text;
    }

    public function isElement(): bool
    {
        return $this->kind() === NodeKind::Element;
    }

    public function isDirective(): bool
    {
        return $this->kind() === NodeKind::Directive;
    }

    public function isDirectiveBlock(): bool
    {
        return $this->kind() === NodeKind::DirectiveBlock;
    }

    public function isEcho(): bool
    {
        $kind = $this->kind();

        return $kind === NodeKind::Echo
            || $kind === NodeKind::RawEcho
            || $kind === NodeKind::TripleEcho;
    }

    public function isComment(): bool
    {
        return $this->kind() === NodeKind::Comment;
    }

    public function isBladeComment(): bool
    {
        return $this->kind() === NodeKind::BladeComment;
    }

    public function isBogusComment(): bool
    {
        return $this->kind() === NodeKind::BogusComment;
    }

    public function isConditionalComment(): bool
    {
        return $this->kind() === NodeKind::ConditionalComment;
    }

    public function isProcessingInstruction(): bool
    {
        return $this->kind() === NodeKind::ProcessingInstruction;
    }

    public function isXmlDeclaration(): bool
    {
        return $this->kind() === NodeKind::Decl;
    }

    public function isVerbatim(): bool
    {
        return $this->kind() === NodeKind::Verbatim;
    }

    public function isPhpBlock(): bool
    {
        return $this->kind() === NodeKind::PhpBlock;
    }

    public function isPhpTag(): bool
    {
        return $this->kind() === NodeKind::PhpTag;
    }

    public function asComponent(): ?ComponentNode
    {
        return $this instanceof ComponentNode ? $this : null;
    }

    public function asSlot(): ?SlotNode
    {
        return $this instanceof SlotNode ? $this : null;
    }

    public function asElement(): ?ElementNode
    {
        return $this instanceof ElementNode ? $this : null;
    }

    public function asDoctype(): ?DoctypeNode
    {
        return $this instanceof DoctypeNode ? $this : null;
    }

    public function asStrayClosingTag(): ?StrayClosingTagNode
    {
        return $this instanceof StrayClosingTagNode ? $this : null;
    }

    public function asGeneric(): ?GenericNode
    {
        return $this instanceof GenericNode ? $this : null;
    }

    public function asXmlDeclaration(): ?XmlDeclarationNode
    {
        return $this instanceof XmlDeclarationNode ? $this : null;
    }

    public function asDirective(): ?DirectiveNode
    {
        return $this instanceof DirectiveNode ? $this : null;
    }

    public function asDirectiveBlock(): ?DirectiveBlockNode
    {
        return $this instanceof DirectiveBlockNode ? $this : null;
    }

    public function asEcho(): ?EchoNode
    {
        return $this instanceof EchoNode ? $this : null;
    }

    public function asText(): ?TextNode
    {
        return $this instanceof TextNode ? $this : null;
    }

    public function asCdata(): ?CdataNode
    {
        return $this instanceof CdataNode ? $this : null;
    }

    public function asPhpTag(): ?PhpTagNode
    {
        return $this instanceof PhpTagNode ? $this : null;
    }

    public function asPhpBlock(): ?PhpBlockNode
    {
        return $this instanceof PhpBlockNode ? $this : null;
    }

    public function asBladeComment(): ?BladeCommentNode
    {
        return $this instanceof BladeCommentNode ? $this : null;
    }

    public function asComment(): ?CommentNode
    {
        return $this instanceof CommentNode ? $this : null;
    }

    public function asBogusComment(): ?BogusCommentNode
    {
        return $this instanceof BogusCommentNode ? $this : null;
    }

    public function asConditionalComment(): ?ConditionalCommentNode
    {
        return $this instanceof ConditionalCommentNode ? $this : null;
    }

    public function asEscape(): ?EscapeNode
    {
        return $this instanceof EscapeNode ? $this : null;
    }

    public function asVerbatim(): ?VerbatimNode
    {
        return $this instanceof VerbatimNode ? $this : null;
    }
}
