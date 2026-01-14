<?php

declare(strict_types=1);

namespace Forte\Tests;

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;
use Forte\Extensions\ExtensionRegistry;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Directives\Directives;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\ParserOptions;
use Forte\ServiceProvider;
use Orchestra\Testbench\TestCase;

class ForteTestCase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $cacheDir = sys_get_temp_dir().'/forte_testbench_'.getmypid().'/views';
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $app['config']->set('view.compiled', $cacheDir);
    }

    public static function applicationBasePath(): string
    {
        $basePath = sys_get_temp_dir().'/forte_testbench_'.getmypid();

        $cacheDir = $basePath.'/bootstrap/cache';

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $storageDirs = [
            $basePath.'/storage/app',
            $basePath.'/storage/framework/cache',
            $basePath.'/storage/framework/sessions',
            $basePath.'/storage/framework/views',
            $basePath.'/storage/logs',
        ];
        foreach ($storageDirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        return $basePath;
    }

    public function tokenize(string $template, ?Directives $directives = null)
    {
        return (new Lexer($template, $directives))->tokenize();
    }

    public function parse(string $template, ?ParserOptions $options = null): Document
    {
        $options ??= ParserOptions::defaults()->withAllDirectives();

        return Document::parse($template, $options);
    }

    public function parseElement(string $template): ElementNode
    {
        return Document::parse($template)->elements->first();
    }

    /**
     * @return ElementNode[]
     */
    public function parseElements(string $template): array
    {
        return Document::parse($template)->elements->toArray();
    }

    protected function freshRegistries(): void
    {
        $this->app->singleton(TokenTypeRegistry::class, fn () => new TokenTypeRegistry);
        $this->app->singleton(NodeKindRegistry::class, fn () => new NodeKindRegistry);
        $this->app->singleton(ExtensionRegistry::class, fn ($app) => new ExtensionRegistry(
            $app->make(TokenTypeRegistry::class),
            $app->make(NodeKindRegistry::class)
        ));
    }

    public function assertIncrementalParsing(
        string $content,
        float $timeout = 2.0,
        ?Directives $directives = null
    ): void {
        $length = strlen($content);
        $directives ??= Directives::acceptAll();

        if ($length > 1000) {
            $positions = array_map(
                fn ($i) => max(1, min($length, (int) (($i * $length) / 100))),
                range(0, 99)
            );
        } else {
            $positions = range(1, $length);
        }

        foreach ($positions as $position) {
            if ($position > $length) {
                continue;
            }

            $prefix = substr($content, 0, $position);

            $start = microtime(true);
            $lexer = new Lexer($prefix, $directives);
            $result = $lexer->tokenize();
            $elapsed = microtime(true) - $start;

            expect($elapsed)->toBeLessThanOrEqual(
                $timeout,
                "Timeout at position {$position}/{$length}"
            );

            $reconstructed = Token::reconstructFromTokens($result->tokens, $prefix);

            expect($reconstructed)->toBe(
                $prefix,
                "Reconstruction mismatch at byte position {$position}/{$length}"
            );
        }
    }
}
