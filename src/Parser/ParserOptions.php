<?php

declare(strict_types=1);

namespace Forte\Parser;

use Forte\Components\ComponentManager;
use Forte\Extensions\ExtensionRegistry;
use Forte\Extensions\ForteExtension;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Directives\Directives;

class ParserOptions
{
    private ?Directives $directives = null;

    private ?ComponentManager $componentManager = null;

    private ?ExtensionRegistry $extensionRegistry = null;

    private bool $acceptAllDirectives = false;

    private bool $syncLaravelDirectives = false;

    /**
     * Create a new ParserOptions instance.
     */
    public static function make(): self
    {
        return new self;
    }

    public static function defaults(): self
    {
        return (new self)
            ->directives(Directives::withDefaults())
            ->components(new ComponentManager);
    }

    /**
     * @param  ForteExtension|class-string<ForteExtension>  ...$extensions
     */
    public static function withExtensions(ForteExtension|string ...$extensions): self
    {
        $instance = new self;

        foreach ($extensions as $ext) {
            $instance->extension($ext);
        }

        return $instance;
    }

    public function withAllDirectives(): self
    {
        if (! $this->directives) {
            $this->directives = Directives::withDefaults();
        }

        $this->directives->setAcceptAll(true);

        return $this;
    }

    public function directives(Directives $directives): self
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * Set the component manager.
     */
    public function components(ComponentManager $manager): self
    {
        $this->componentManager = $manager;

        return $this;
    }

    /**
     * Register an additional component prefix.
     */
    public function withComponentPrefix(string $prefix): self
    {
        $this->getComponentManager()->register($prefix);

        return $this;
    }

    /**
     * Enable accept-all directives mode.
     *
     * When enabled, any @word pattern is treated as a directive.
     */
    public function acceptAllDirectives(bool $accept = true): self
    {
        $this->acceptAllDirectives = $accept;

        return $this;
    }

    /**
     * Syncs directives from Laravel's Blade compiler.
     */
    public function syncLaravelDirectives(bool $sync = true): self
    {
        $this->syncLaravelDirectives = $sync;

        return $this;
    }

    /**
     * Register an extension.
     *
     * @param  ForteExtension|class-string<ForteExtension>  $extension
     */
    public function extension(ForteExtension|string $extension): self
    {
        $instance = is_string($extension) ? new $extension : $extension;
        $this->getExtensionRegistry()->register($instance);

        return $this;
    }

    /**
     * Register multiple extensions.
     *
     * @param  array<ForteExtension|class-string<ForteExtension>>  $extensions
     */
    public function extensions(array $extensions): self
    {
        foreach ($extensions as $ext) {
            $this->extension($ext);
        }

        return $this;
    }

    /**
     * Get the directives registry (resolves defaults lazily).
     */
    public function getDirectives(): Directives
    {
        if ($this->directives === null) {
            $this->directives = Directives::withDefaults();
        }

        if ($this->acceptAllDirectives) {
            $this->directives->setAcceptAll(true);
        }

        if ($this->syncLaravelDirectives && function_exists('app')) {
            $this->directives->syncLaravelDirectives();
        }

        return $this->directives;
    }

    /**
     * Get the component manager (resolves defaults lazily).
     */
    public function getComponentManager(): ComponentManager
    {
        return $this->componentManager ??= new ComponentManager;
    }

    /**
     * Get the extension registry (resolves from container or creates new one).
     */
    public function getExtensionRegistry(): ExtensionRegistry
    {
        if ($this->extensionRegistry === null) {
            $this->extensionRegistry = function_exists('app')
                ? app(ExtensionRegistry::class)
                : new ExtensionRegistry(new TokenTypeRegistry, new NodeKindRegistry);
        }

        return $this->extensionRegistry;
    }

    /**
     * Check if any extensions are registered.
     */
    public function hasExtensions(): bool
    {
        return $this->extensionRegistry !== null
            && count($this->extensionRegistry->all()) > 0;
    }

    public function merge(self $other): self
    {
        if ($other->extensionRegistry !== null) {
            foreach ($other->extensionRegistry->all() as $ext) {
                if (! $this->getExtensionRegistry()->has($ext->id())) {
                    $this->getExtensionRegistry()->register($ext);
                }
            }
        }

        if ($other->componentManager !== null) {
            foreach ($other->componentManager->getPrefixes() as $prefix) {
                $this->getComponentManager()->register($prefix);
            }
        }

        if ($other->acceptAllDirectives) {
            $this->acceptAllDirectives = true;
        }

        if ($other->syncLaravelDirectives) {
            $this->syncLaravelDirectives = true;
        }

        return $this;
    }
}
