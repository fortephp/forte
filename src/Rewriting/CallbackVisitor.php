<?php

declare(strict_types=1);

namespace Forte\Rewriting;

class CallbackVisitor extends Visitor
{
    /** @var callable(NodePath): void|null */
    private $enterCallback;

    /** @var callable(NodePath): void|null */
    private $leaveCallback;

    /**
     * @param  callable(NodePath): void|null  $enter  Called when entering a node
     * @param  callable(NodePath): void|null  $leave  Called when leaving a node
     */
    public function __construct(
        ?callable $enter = null,
        ?callable $leave = null
    ) {
        $this->enterCallback = $enter;
        $this->leaveCallback = $leave;
    }

    /**
     * Create a visitor with just an entrance callback.
     *
     * @param  callable(NodePath): void  $callback
     */
    public static function onEnter(callable $callback): self
    {
        return new self(enter: $callback);
    }

    /**
     * Create a visitor with just a leave callback.
     *
     * @param  callable(NodePath): void  $callback
     */
    public static function onLeave(callable $callback): self
    {
        return new self(leave: $callback);
    }

    public function enter(NodePath $path): void
    {
        if ($this->enterCallback !== null) {
            ($this->enterCallback)($path);
        }
    }

    public function leave(NodePath $path): void
    {
        if ($this->leaveCallback !== null) {
            ($this->leaveCallback)($path);
        }
    }
}
