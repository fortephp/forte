<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Parser\NodeKind;

class ElementBuilder extends NodeBuilder implements WrapperBuilder
{
    /** @var array<string, string|true> */
    private array $attributes = [];

    /** @var array<string, string> */
    private array $bladeAttributes = [];

    /** @var array<NodeBuilder> */
    private array $children = [];

    private bool $selfClosing = false;

    private bool $void = false;

    public function __construct(private readonly string $tagName) {}

    /**
     * Set an attribute value.
     */
    public function attr(string $name, string $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Set a boolean attribute.
     */
    public function boolAttr(string $name): self
    {
        $this->attributes[$name] = true;

        return $this;
    }

    /**
     * Set a Blade dynamic attribute: :attr="$expr"
     */
    public function bladeAttr(string $name, string $expression): self
    {
        $this->bladeAttributes[$name] = $expression;

        return $this;
    }

    /**
     * Set the "id" attribute.
     */
    public function id(string $value): self
    {
        return $this->attr('id', $value);
    }

    /**
     * Set the "class" attribute.
     */
    public function class(string $value): self
    {
        return $this->attr('class', $value);
    }

    /**
     * Add a class to existing classes.
     */
    public function addClass(string $class): self
    {
        $existing = $this->attributes['class'] ?? '';
        if ($existing === true) {
            $existing = '';
        }

        $classes = array_filter(explode(' ', (string) $existing));
        if (! in_array($class, $classes, true)) {
            $classes[] = $class;
        }

        return $this->attr('class', implode(' ', $classes));
    }

    /**
     * Set child nodes.
     *
     * @param  NodeBuilder|string  ...$children  Specs or raw strings
     */
    public function children(NodeBuilder|string ...$children): self
    {
        $this->children = array_map(
            fn ($c) => $c instanceof NodeBuilder ? $c : new TextBuilder($c),
            $children
        );

        return $this;
    }

    public function text(string $content): self
    {
        return $this->children(new TextBuilder($content));
    }

    /**
     * Append additional children.
     */
    public function append(NodeBuilder|string ...$children): self
    {
        foreach ($children as $child) {
            $this->children[] = $child instanceof NodeBuilder ? $child : new TextBuilder($child);
        }

        return $this;
    }

    /**
     * Mark as self-closing.
     */
    public function selfClosing(): self
    {
        $this->selfClosing = true;

        return $this;
    }

    /**
     * Mark as a void element.
     */
    public function void(): self
    {
        $this->void = true;

        return $this;
    }

    public function kind(): int
    {
        return NodeKind::Element;
    }

    public function toSource(): string
    {
        $output = '<'.$this->tagName;

        foreach ($this->attributes as $name => $value) {
            if ($value === true) {
                $output .= ' '.$name;
            } else {
                $output .= ' '.$name.'="'.$value.'"';
            }
        }

        foreach ($this->bladeAttributes as $name => $expression) {
            $output .= ' :'.$name.'="'.$expression.'"';
        }

        if ($this->selfClosing) {
            return $output.' />';
        }

        if ($this->void) {
            return $output.'>';
        }

        $output .= '>';

        foreach ($this->children as $child) {
            $output .= $child->toSource();
        }

        return $output.('</'.$this->tagName.'>');
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    /**
     * @return array<string, string|true>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<NodeBuilder>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Check if this element is marked as self-closing.
     */
    public function isSelfClosing(): bool
    {
        return $this->selfClosing;
    }

    /**
     * Check if this element is marked as void.
     */
    public function isVoid(): bool
    {
        return $this->void;
    }

    /**
     * Get the opening tag source for this element.
     */
    public function getOpeningSource(): string
    {
        $output = '<'.$this->tagName;

        // Standard attributes
        foreach ($this->attributes as $name => $value) {
            if ($value === true) {
                $output .= ' '.$name;
            } else {
                $output .= ' '.$name.'="'.$value.'"';
            }
        }

        // Blade dynamic attributes
        foreach ($this->bladeAttributes as $name => $expression) {
            $output .= ' :'.$name.'="'.$expression.'"';
        }

        return $output.'>';
    }

    /**
     * Get the closing tag source for this element.
     */
    public function getClosingSource(): string
    {
        if ($this->selfClosing || $this->void) {
            return '';
        }

        return '</'.$this->tagName.'>';
    }
}
