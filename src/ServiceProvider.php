<?php

declare(strict_types=1);

namespace Forte;

use Forte\Components\ComponentManager;
use Forte\Enclaves\EnclavesManager;
use Forte\Enclaves\Precompiler;
use Forte\Extensions\ExtensionRegistry;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Directives\Directives;
use Forte\Parser\NodeKindRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnclavesManager::class);

        $this->app->singleton(Precompiler::class);

        $this->app->singleton(ComponentManager::class, fn ($app) => new ComponentManager);

        $this->app->singleton(Directives::class, fn () => (new Directives)
            ->loadDirectory(__DIR__.'/../resources/directives/')
            ->syncLaravelDirectives());

        $this->app->singleton(TokenTypeRegistry::class, fn () => new TokenTypeRegistry);

        $this->app->singleton(NodeKindRegistry::class, fn () => new NodeKindRegistry);

        $this->app->singleton(ExtensionRegistry::class, fn () => new ExtensionRegistry(
            $this->app->make(TokenTypeRegistry::class),
            $this->app->make(NodeKindRegistry::class)
        ));
    }

    public function boot(): void
    {
        $precompiler = $this->app->make(Precompiler::class);

        Blade::prepareStringsForCompilationUsing(function (string $template) use ($precompiler): string {
            $path = Blade::getPath();

            if (! $path) {
                return $template;
            }

            return $precompiler->compile($template, $path);
        });
    }
}
