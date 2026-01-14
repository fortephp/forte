<?php

declare(strict_types=1);

namespace Forte\Querying;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use Forte\Ast\BladeCommentNode;
use Forte\Ast\Components\ComponentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\DoctypeNode;
use Forte\Ast\Document\Document;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\CdataNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\GenericNode;
use Forte\Ast\Node;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;
use Forte\Ast\VerbatimNode;
use Forte\Parser\NodeKind;
use Forte\Parser\NodeKindRegistry;

class DomMapper
{
    public const FORTE_NAMESPACE = 'https://fortephp.com/ns/blade';

    public const FORTE_PREFIX = 'forte';

    public const INDEX_ATTR = 'data-forte-idx';

    public const COMPONENT_ATTR = 'data-forte-component';

    public const DYNAMIC_TAG_ATTR = 'data-forte-dynamic-tag';

    public const DYNAMIC_ATTR_ATTR = 'data-forte-dynamic-attrs';

    public const SLOT_ATTR = 'data-forte-slot';

    public const SLOT_NAME_ATTR = 'data-forte-slot-name';

    public const COMPONENT_TYPE_ATTR = 'data-forte-component-type';

    public const HAS_SPREAD_ATTR = 'data-forte-has-spread';

    public const INTERMEDIATE_ATTR = 'data-forte-intermediate';

    private DOMDocument $dom;

    /** @var array<int, ExtensionDomMapper> */
    private static array $extensionMappers = [];

    public function __construct(private readonly Document $document) {}

    /**
     * Register a DOM mapper for an extension node kind.
     */
    public static function registerExtensionMapper(int $kind, ExtensionDomMapper $mapper): void
    {
        self::$extensionMappers[$kind] = $mapper;
    }

    public static function clearExtensionMappers(): void
    {
        self::$extensionMappers = [];
    }

    /**
     * @return array{dom: DOMDocument, document: Document}
     *
     * @throws DOMException
     */
    public function build(): array
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = false;

        $root = $this->dom->createElementNS(self::FORTE_NAMESPACE, self::FORTE_PREFIX.':root');
        $this->dom->appendChild($root);

        foreach ($this->document->children() as $child) {
            $domNode = $this->convertNode($child);
            if ($domNode !== null) {
                $root->appendChild($domNode);
            }
        }

        return [
            'dom' => $this->dom,
            'document' => $this->document,
        ];
    }

    /**
     * @throws DOMException
     */
    private function convertNode(Node $node): ?DOMNode
    {
        return match (true) {
            $node instanceof ComponentNode => $this->convertComponent($node),
            $node instanceof ElementNode => $this->buildDomElement($node),
            $node instanceof DirectiveBlockNode => $this->convertDirectiveBlock($node),
            $node instanceof DirectiveNode => $this->convertDirective($node),
            $node instanceof EchoNode => $this->convertEcho($node),
            $node instanceof TextNode => $this->convertText($node),
            $node instanceof CommentNode => $this->convertComment($node),
            $node instanceof BladeCommentNode => $this->convertBladeComment($node),
            $node instanceof PhpBlockNode => $this->convertPhpBlock($node),
            $node instanceof PhpTagNode => $this->convertPhpTag($node),
            $node instanceof VerbatimNode => $this->convertVerbatim($node),
            $node instanceof DoctypeNode => $this->convertDoctype($node),
            $node instanceof CdataNode => $this->convertCdata($node),
            $node instanceof GenericNode => $this->convertGeneric($node),
            default => $this->convertUnknown($node),
        };
    }

    /**
     * @throws DOMException
     */
    private function convertComponent(ComponentNode $node): DOMElement
    {
        $element = $this->buildDomElement($node, true);

        $componentType = $node->getType();
        $element->setAttribute(self::COMPONENT_TYPE_ATTR, $componentType);

        if ($node->isSlot()) {
            $element->setAttribute(self::SLOT_ATTR, 'true');

            if ($slotName = $node->getSlotName()) {
                $element->setAttribute(self::SLOT_NAME_ATTR, $slotName);
            }
        }

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertDirectiveBlock(DirectiveBlockNode $node): DOMElement
    {
        $name = $this->sanitizeDirectiveName($node->nameText());
        $element = $this->createForteElement($name);

        $this->setArgsIfPresent($element, $node->arguments());
        $this->setNodeIndex($element, $node);
        $this->addDirectiveBlockChildren($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function addDirectiveBlockChildren(DOMElement $domElement, DirectiveBlockNode $node): void
    {
        foreach ($node->children() as $child) {
            if ($child instanceof DirectiveNode) {
                if ($child->isOpening()) {
                    $this->appendDirectiveBody($domElement, $child);

                    continue;
                }

                if ($child->isClosing()) {
                    continue;
                }

                if ($child->isIntermediate()) {
                    $domElement->appendChild(
                        $this->buildIntermediateDirective($child)
                    );

                    continue;
                }
            }

            if ($domChild = $this->convertNode($child)) {
                $domElement->appendChild($domChild);
            }
        }
    }

    /**
     * @throws DOMException
     */
    private function convertDirective(DirectiveNode $node): DOMElement
    {
        $name = $this->sanitizeDirectiveName($node->nameText());
        $element = $this->createForteElement($name);

        $this->setArgsIfPresent($element, $node->arguments());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertEcho(EchoNode $node): DOMElement
    {
        $elementName = match ($node->kind()) {
            NodeKind::RawEcho => 'raw-echo',
            NodeKind::TripleEcho => 'triple-echo',
            default => 'echo',
        };

        $element = $this->createForteElement($elementName);
        $element->setAttribute('expression', $node->expression());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    private function convertText(TextNode $node): ?DOMNode
    {
        $content = $node->getDocumentContent();

        if ($content === '') {
            return null;
        }

        return $this->dom->createTextNode($content);
    }

    private function convertComment(CommentNode $node): DOMNode
    {
        return $this->dom->createComment($node->content());
    }

    /**
     * @throws DOMException
     */
    private function convertBladeComment(BladeCommentNode $node): DOMElement
    {
        $element = $this->createForteElement('comment');
        $element->setAttribute('content', $node->content());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertPhpBlock(PhpBlockNode $node): DOMElement
    {
        $element = $this->createForteElement('php');
        $element->setAttribute('code', $node->code());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertPhpTag(PhpTagNode $node): DOMElement
    {
        $element = $this->createForteElement('php-tag');
        $element->setAttribute('code', $node->code());
        $element->setAttribute('type', $node->isShortEcho() ? 'short' : 'full');
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertVerbatim(VerbatimNode $node): DOMElement
    {
        $element = $this->createForteElement('verbatim');
        $element->setAttribute('content', $node->content());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertDoctype(DoctypeNode $node): DOMElement
    {
        $element = $this->createForteElement('doctype');
        $element->setAttribute('type', $node->type());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertCdata(CdataNode $node): DOMElement
    {
        $element = $this->createForteElement('cdata');
        $element->setAttribute('content', $node->content());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertGeneric(GenericNode $node): DOMElement
    {
        $kind = $node->kind();

        if (isset(self::$extensionMappers[$kind])) {
            return self::$extensionMappers[$kind]->toDOM($node, $this->dom, $this);
        }

        $domElement = app(NodeKindRegistry::class)->getDomElement($kind);
        if ($domElement !== null) {
            $element = $this->createForteElement($domElement);
            $this->setNodeIndex($element, $node);
            $this->addChildren($element, $node);

            return $element;
        }

        $element = $this->createForteElement('extension');
        $element->setAttribute('kind', (string) $kind);
        $element->setAttribute('name', $node->kindName());
        $this->setNodeIndex($element, $node);
        $this->addChildren($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function convertUnknown(Node $node): DOMElement
    {
        $element = $this->createForteElement('unknown');
        $element->setAttribute('kind', (string) $node->kind());
        $this->setNodeIndex($element, $node);

        return $element;
    }

    /**
     * @throws DOMException
     */
    private function createForteElement(string $localName): DOMElement
    {
        return $this->dom->createElementNS(self::FORTE_NAMESPACE, self::FORTE_PREFIX.':'.$localName);
    }

    /**
     * @template T of ElementNode
     *
     * @param  T  $node
     *
     * @throws DOMException
     */
    private function buildDomElement(ElementNode $node, bool $isComponent = false): DOMElement
    {
        $originalTagName = $node->tagNameText();
        $element = $this->dom->createElement($this->sanitizeTagName($originalTagName));

        $this->applyDynamicTagAttribute($element, $originalTagName);

        if ($isComponent) {
            $element->setAttribute(self::COMPONENT_ATTR, 'true');
        }

        $this->addAttributes($element, $node);
        $this->setNodeIndex($element, $node);
        $this->addChildren($element, $node);

        return $element;
    }

    private function applyDynamicTagAttribute(DOMElement $element, string $originalTagName): void
    {
        if (! $this->containsBladeExpression($originalTagName)) {
            return;
        }

        $element->setAttribute(self::DYNAMIC_TAG_ATTR, $originalTagName);
    }

    /**
     * @throws DOMException
     */
    private function appendDirectiveBody(DOMElement $domElement, DirectiveNode $openingDirective): void
    {
        foreach ($openingDirective->children() as $bodyChild) {
            if ($bodyChild instanceof DirectiveNode && $bodyChild->isIntermediate()) {
                $domElement->appendChild(
                    $this->buildIntermediateDirective($bodyChild)
                );

                continue;
            }

            if ($bodyChild instanceof Node && $domChild = $this->convertNode($bodyChild)) {
                $domElement->appendChild($domChild);
            }
        }
    }

    /**
     * @throws DOMException
     */
    private function buildIntermediateDirective(DirectiveNode $child): DOMElement
    {
        $intermediateElement = $this->createForteElement($this->sanitizeDirectiveName($child->nameText()));
        $intermediateElement->setAttribute(self::INTERMEDIATE_ATTR, 'true');

        $this->setArgsIfPresent($intermediateElement, $child->arguments());
        $this->setNodeIndex($intermediateElement, $child);

        foreach ($child->children() as $intermediateChild) {
            if ($intermediateChild instanceof Node && $domChild = $this->convertNode($intermediateChild)) {
                $intermediateElement->appendChild($domChild);
            }
        }

        return $intermediateElement;
    }

    private function addAttributes(DOMElement $domElement, ElementNode $node): void
    {
        $dynamicAttrNames = [];
        $hasSpread = false;

        /** @var Attribute $attr */
        foreach ($node->attributes() as $attr) {
            if ($attr->isBladeConstruct()) {
                $hasSpread = true;

                continue;
            }

            $name = $attr->nameText();
            $value = $attr->valueText() ?? '';
            $type = $attr->type();

            if ($this->containsBladeExpression($name)) {
                $dynamicAttrNames[] = $name;
            }

            if ($type === 'bound' || $type === 'escaped' || $type === 'dynamic') {
                $localName = match ($type) {
                    'bound' => 'bind-'.$this->sanitizeAttrName($name),
                    'escaped' => 'escape-'.$this->sanitizeAttrName($name),
                    'dynamic' => 'dynamic-'.$this->sanitizeAttrName($name),
                };
                $domElement->setAttributeNS(
                    self::FORTE_NAMESPACE,
                    self::FORTE_PREFIX.':'.$localName,
                    $value
                );
            } else {
                $domElement->setAttribute($this->sanitizeAttrName($name), $value);
            }
        }

        if ($hasSpread) {
            $domElement->setAttribute(self::HAS_SPREAD_ATTR, 'true');
        }

        $this->addDynamicAttrMetadata($domElement, $dynamicAttrNames);
    }

    /**
     * @param  list<string>  $dynamicAttrNames
     */
    private function addDynamicAttrMetadata(DOMElement $domElement, array $dynamicAttrNames): void
    {
        if ($dynamicAttrNames === []) {
            return;
        }

        $domElement->setAttribute(self::DYNAMIC_ATTR_ATTR, implode('|', $dynamicAttrNames));

        foreach ($dynamicAttrNames as $dynName) {
            $sanitized = $this->sanitizeAttrName($dynName);

            if ($sanitized !== '' && preg_match('/^[a-z][a-z0-9_-]*$/i', $sanitized)) {
                $domElement->setAttribute('data-forte-has-dynamic-'.$sanitized, 'true');
            }
        }
    }

    /**
     * @throws DOMException
     */
    private function addChildren(DOMElement $domElement, Node $node): void
    {
        foreach ($node->children() as $child) {
            if ($domChild = $this->convertNode($child)) {
                $domElement->appendChild($domChild);
            }
        }
    }

    private function setNodeIndex(DOMElement $element, Node $node): void
    {
        $element->setAttribute(self::INDEX_ATTR, (string) $node->index());
    }

    private function sanitizeName(string $name, string $fallback): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', $name) ?? $name;

        if ($name !== '' && ! preg_match('/^[a-zA-Z_]/', $name)) {
            $name = '_'.$name;
        }

        return $name ?: $fallback;
    }

    private function sanitizeTagName(string $name): string
    {
        return $this->sanitizeName($name, '_unknown');
    }

    private function sanitizeDirectiveName(string $name): string
    {
        return $this->sanitizeTagName($name);
    }

    private function sanitizeAttrName(string $name): string
    {
        return $this->sanitizeName($name, '_attr');
    }

    /**
     * @internal
     */
    public function getDOMDocument(): DOMDocument
    {
        return $this->dom;
    }

    private function containsBladeExpression(string $value): bool
    {
        return str_contains($value, '{{')
            || str_contains($value, '{!!')
            || str_contains($value, '<?')
            || str_contains($value, '@');
    }

    private function setArgsIfPresent(DOMElement $element, ?string $args): void
    {
        if ($args === null) {
            return;
        }

        $element->setAttribute('args', $args);
    }
}
