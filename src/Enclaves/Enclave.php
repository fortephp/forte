<?php

declare(strict_types=1);

namespace Forte\Enclaves;

use Forte\Ast\Document\Document;
use Forte\Enclaves\Rewriters\ConditionalAttributesRewriter;
use Forte\Enclaves\Rewriters\ForeachAttributeRewriter;
use Forte\Enclaves\Rewriters\ForelseAttributeRewriter;
use Forte\Enclaves\Rewriters\HoistDirectiveArgumentsRewriter;
use Forte\Enclaves\Rewriters\MixedPhpDirectivesRewriter;
use Forte\Extensions\ForteExtension;
use Forte\Parser\ParserOptions;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\RewriteVisitor;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class Enclave
{
    /** @var list<string> */
    private array $includes = [];

    /** @var list<string> */
    private array $excludes = [];

    /**
     * @var array<class-string<RewriteVisitor>, array{priority:int, instance:RewriteVisitor|null, sequence:int}>
     */
    private array $rewriters = [];

    /**
     * Callback-based transformers (order-based priority).
     *
     * @var array<string, array{callback:callable, sequence:int}>
     */
    private array $callbackRewriters = [];

    /**
     * AstRewriter-based transformers (order-based priority).
     *
     * @var array<string, array{rewriter:AstRewriter, sequence:int}>
     */
    private array $rewriterTransformers = [];

    /**
     * Extensions for parser configuration.
     *
     * @var array<class-string<ForteExtension>, ForteExtension|null>
     */
    private array $extensions = [];

    /**
     * Sequence counter for order-based priority.
     */
    private int $sequenceCounter = 0;

    /**
     * Include one or more paths using glob-like patterns in the enclave.
     *
     * @param  string|array<string>  ...$patterns
     */
    public function include(string|array ...$patterns): self
    {
        $flattenedPatterns = $this->flattenPatterns($patterns);

        foreach ($flattenedPatterns as $pattern) {
            $this->includes[] = $pattern;
        }

        return $this;
    }

    /**
     * Exclude one or more paths using glob-like patterns in the enclave.
     *
     * @param  string|array<string>  ...$patterns
     */
    public function exclude(string|array ...$patterns): self
    {
        $flattenedPatterns = $this->flattenPatterns($patterns);

        foreach ($flattenedPatterns as $pattern) {
            $this->excludes[] = $pattern;
        }

        return $this;
    }

    /**
     * Check if a given path belongs to this Enclave.
     *
     * @param  string  $path  Absolute or relative file path to test
     */
    public function matches(string $path): bool
    {
        $bestIncludeScore = $this->getBestMatchingScore($this->includes, $path);

        if ($bestIncludeScore === null) {
            return false;
        }

        $bestExcludeScore = $this->getBestMatchingScore($this->excludes, $path);

        if ($bestExcludeScore === null) {
            return true;
        }

        return $bestIncludeScore >= $bestExcludeScore;
    }

    /**
     * Get all configured include patterns.
     *
     * @return list<string>
     */
    public function getIncludes(): array
    {
        return $this->includes;
    }

    /**
     * Get all configured exclude patterns.
     *
     * @return list<string>
     */
    public function getExcludes(): array
    {
        return $this->excludes;
    }

    /**
     * Register one or many transformers or extensions to run for this Enclave.
     *
     * @param  RewriteVisitor|ForteExtension|class-string<RewriteVisitor>|class-string<ForteExtension>|array<RewriteVisitor|ForteExtension|class-string<RewriteVisitor>|class-string<ForteExtension>>  $item
     * @param  int  $priority  Higher priority transformers run earlier relative to others (ignored for extensions)
     */
    public function use(RewriteVisitor|ForteExtension|string|array $item, int $priority = 0): self
    {
        $items = Arr::wrap($item);

        foreach ($items as $singleItem) {
            if ($this->isExtension($singleItem)) {
                /** @var ForteExtension|class-string<ForteExtension> $singleItem */
                $this->addExtension($singleItem);
            } elseif ($singleItem instanceof RewriteVisitor) {
                $this->addRewriter($singleItem, $priority);
            } elseif (is_string($singleItem)) {
                /** @var class-string<RewriteVisitor> $singleItem */
                $this->addRewriter($singleItem, $priority);
            }
        }

        return $this;
    }

    /**
     * Register a callback transformer with order-based priority.
     *
     * @param  callable(NodePath): void  $callback
     */
    public function transform(callable $callback): self
    {
        $key = 'callback:'.$this->sequenceCounter;

        $this->callbackRewriters[$key] = [
            'callback' => $callback,
            'sequence' => $this->sequenceCounter++,
        ];

        return $this;
    }

    /**
     * Register one or more AST rewriters with order-based priority.
     */
    public function apply(AstRewriter ...$rewriters): self
    {
        foreach ($rewriters as $rewriter) {
            $key = 'rewriter:'.$this->sequenceCounter;

            $this->rewriterTransformers[$key] = [
                'rewriter' => $rewriter,
                'sequence' => $this->sequenceCounter++,
            ];
        }

        return $this;
    }

    /**
     * Register multiple rewriters at once.
     *
     * @param  array<RewriteVisitor|class-string<RewriteVisitor>>  $rewriters
     * @param  int  $priority  Higher priority rewriters run earlier relatively to others
     */
    public function useMany(array $rewriters, int $priority = 0): self
    {
        foreach ($rewriters as $transformer) {
            $this->addRewriter($transformer, $priority);
        }

        return $this;
    }

    /**
     * Enable the foreach attribute on HTML elements.
     *
     * <div #foreach="$users as $user">...</div>
     *
     * @param  string  $prefix  The prefix to use
     */
    public function elementForeachAttributes(string $prefix = '#'): self
    {
        return $this->use(new ForeachAttributeRewriter($prefix));
    }

    /**
     * Enable the forelse attribute on HTML elements.
     *
     * <div #forelse="$users as $user">...</div>
     * <div #empty>There are no users.</div>
     *
     * @param  string  $prefix  The prefix to use
     */
    public function elementForelseAttributes(string $prefix = '#'): self
    {
        return $this->use(new ForelseAttributeRewriter($prefix));
    }

    /**
     * Enable conditional attributes on HTML attributes.
     *
     * <div #if="! empty($users)">...</div>
     *
     * @param  string  $prefix  The prefix to use
     */
    public function elementConditionalAttributes(string $prefix = '#'): self
    {
        return $this->use(new ConditionalAttributesRewriter($prefix));
    }

    /**
     * Allow mixed Laravel Blade PHP-block styles.
     *
     * The rewriter will safely rewrite PHP blocks to
     * allow for use of @php() and @php ... @endphp
     */
    public function allowMixedPhpDirectives(): self
    {
        return $this->use(new MixedPhpDirectivesRewriter);
    }

    /**
     * Hoists arguments for problematic directives into their own variable initialization.
     *
     * @param  array<string>|null  $directives  Directive names to protect
     */
    public function hoistDirectiveArguments(?array $directives = null): self
    {
        return $this->use(new HoistDirectiveArgumentsRewriter($directives));
    }

    /**
     * Configure the enclave to target a specific package's views.
     *
     * @param  string  $packageName  vendor/package name
     * @param  string|null  $packagePath  Absolute path to the package root
     */
    public function forPackage(string $packageName, ?string $packagePath = null): self
    {
        $vendorPath = resource_path("views/vendor/{$packageName}/**");
        $this->include($vendorPath);

        if ($packagePath) {
            $this->include(rtrim($packagePath, '/').'/resources/views/**');
        }

        return $this;
    }

    /**
     * Include all published views for the given vendor packages.
     */
    public function includeVendorPackage(string ...$packageNames): self
    {
        foreach ($packageNames as $packageName) {
            $this->include(resource_path("views/vendor/{$packageName}/**"));
        }

        return $this;
    }

    /**
     * Include all published vendor views, regardless of package.
     */
    public function includeAllVendorViews(): self
    {
        return $this->include(resource_path('views/vendor/**'));
    }

    /**
     * Exclude all published views for the given vendor packages.
     */
    public function excludeVendorPackage(string ...$packageNames): self
    {
        foreach ($packageNames as $packageName) {
            $this->exclude(resource_path("views/vendor/{$packageName}/**"));
        }

        return $this;
    }

    /**
     * Add a rewriter with an optional priority.
     *
     * @param  RewriteVisitor|class-string<RewriteVisitor>  $rewriter
     */
    public function addRewriter(RewriteVisitor|string $rewriter, int $priority = 0): self
    {
        if (! $rewriter instanceof RewriteVisitor) {
            $this->validateRewriterClass($rewriter);

            $key = $rewriter;
            $instance = null;
        } else {
            $key = $rewriter::class;
            $instance = $rewriter;
        }

        $this->rewriters[$key] = [
            'priority' => $priority,
            'instance' => $instance,
            'sequence' => $this->sequenceCounter++,
        ];

        return $this;
    }

    /**
     * Add an extension for parser configuration.
     *
     * @param  ForteExtension|class-string<ForteExtension>  $extension
     */
    private function addExtension(ForteExtension|string $extension): void
    {
        if ($extension instanceof ForteExtension) {
            $key = $extension::class;
            $instance = $extension;
        } else {
            $key = $extension;
            $instance = null;
        }

        $this->extensions[$key] = $instance;
    }

    /**
     * Check if an item is an extension.
     */
    private function isExtension(mixed $item): bool
    {
        if ($item instanceof ForteExtension) {
            return true;
        }

        if (is_string($item) && class_exists($item)) {
            return is_subclass_of($item, ForteExtension::class);
        }

        return false;
    }

    /**
     * Remove a previously registered rewriter.
     *
     * @param  RewriteVisitor|AstRewriter|callable|class-string<RewriteVisitor>|string  $transformer
     */
    public function removeRewriter(RewriteVisitor|AstRewriter|callable|string $transformer): self
    {
        if ($this->isVisitorTransformer($transformer)) {
            unset($this->rewriters[$this->getVisitorKey($transformer)]);
        }

        if ($transformer instanceof AstRewriter) {
            $this->removeFromRewriterTransformers($transformer);
        }

        if ($this->isCallbackTransformer($transformer)) {
            $this->removeFromCallbackRewriters($transformer);
        }

        if (is_string($transformer)) {
            unset($this->callbackRewriters[$transformer]);
            unset($this->rewriterTransformers[$transformer]);
        }

        return $this;
    }

    /**
     * Remove all configured rewriters.
     */
    public function clearRewriters(): self
    {
        $this->rewriters = [];
        $this->callbackRewriters = [];
        $this->rewriterTransformers = [];

        return $this;
    }

    /**
     * Check if any rewriters are configured.
     */
    public function hasRewriters(): bool
    {
        return ! empty($this->rewriters)
            || ! empty($this->callbackRewriters)
            || ! empty($this->rewriterTransformers);
    }

    /**
     * Check if any extensions are configured.
     */
    public function hasExtensions(): bool
    {
        return ! empty($this->extensions);
    }

    /**
     * Check whether a specific rewriter is configured.
     *
     * @param  RewriteVisitor|AstRewriter|callable|class-string<RewriteVisitor>|string  $transformer
     */
    public function hasRewriter(RewriteVisitor|AstRewriter|callable|string $transformer): bool
    {
        if ($this->isVisitorTransformer($transformer)) {
            if (isset($this->rewriters[$this->getVisitorKey($transformer)])) {
                return true;
            }
        }

        if ($transformer instanceof AstRewriter) {
            if ($this->findInRewriterTransformers($transformer) !== null) {
                return true;
            }
        }

        if ($this->isCallbackTransformer($transformer)) {
            if ($this->findInCallbackRewriters($transformer) !== null) {
                return true;
            }
        }

        if (is_string($transformer)) {
            if (isset($this->callbackRewriters[$transformer]) || isset($this->rewriterTransformers[$transformer])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the number of configured rewriters.
     */
    public function rewriterCount(): int
    {
        return count($this->rewriters)
            + count($this->callbackRewriters)
            + count($this->rewriterTransformers);
    }

    /**
     * Get rewriter class names, ordered by priority.
     *
     * @return list<class-string<RewriteVisitor>>
     */
    public function getRewritersInPriorityOrder(): array
    {
        $priorities = array_map(fn ($data) => $data['priority'], $this->rewriters);

        return RewriterPrioritizer::orderByPriority($priorities);
    }

    /**
     * Resolve and return rewriters in execution order.
     *
     * @param  null|callable(class-string<RewriteVisitor>):RewriteVisitor  $instantiator
     * @return list<RewriteVisitor>
     */
    public function getRewriters(?callable $instantiator = null): array
    {
        $orderedClasses = $this->getRewritersInPriorityOrder();
        $instantiator ??= static fn (string $class) => new $class;

        $rewriters = [];

        foreach ($orderedClasses as $class) {
            $stored = $this->rewriters[$class] ?? null;

            if ($stored && $stored['instance'] instanceof RewriteVisitor) {
                $rewriters[] = $stored['instance'];

                continue;
            }

            $instance = $instantiator($class);

            if (! $instance instanceof RewriteVisitor) {
                throw new InvalidArgumentException('Instantiator must return instance of '.RewriteVisitor::class);
            }

            $rewriters[] = $instance;
        }

        return $rewriters;
    }

    /**
     * Get the rewriter priorities.
     *
     * @return array<string, array{type: string, priority: int, sequence: int, item: RewriteVisitor|callable|AstRewriter|class-string<RewriteVisitor>}>
     */
    public function getRewriterPriorities(): array
    {
        $all = [];

        foreach ($this->rewriters as $class => $data) {
            $all[$class] = [
                'type' => 'visitor',
                'priority' => $data['priority'],
                'sequence' => $data['sequence'],
                'instance' => $data['instance'],
                'item' => $data['instance'] ?? $class,
            ];
        }

        foreach ($this->callbackRewriters as $key => $data) {
            $all[$key] = [
                'type' => 'callback',
                'priority' => PHP_INT_MIN,
                'sequence' => $data['sequence'],
                'item' => $data['callback'],
            ];
        }

        foreach ($this->rewriterTransformers as $key => $data) {
            $all[$key] = [
                'type' => 'rewriter',
                'priority' => PHP_INT_MIN,
                'sequence' => $data['sequence'],
                'item' => $data['rewriter'],
            ];
        }

        return $all;
    }

    /**
     * @phpstan-assert-if-true RewriteVisitor|string $transformer
     */
    private function isVisitorTransformer(mixed $transformer): bool
    {
        if ($transformer instanceof RewriteVisitor) {
            return true;
        }

        return is_string($transformer)
            && ! str_starts_with($transformer, 'callback:')
            && ! str_starts_with($transformer, 'rewriter:');
    }

    /**
     * @phpstan-assert-if-true callable $transformer
     */
    private function isCallbackTransformer(mixed $transformer): bool
    {
        return is_callable($transformer)
            && ! $transformer instanceof RewriteVisitor
            && ! $transformer instanceof AstRewriter;
    }

    private function getVisitorKey(RewriteVisitor|string $transformer): string
    {
        return $transformer instanceof RewriteVisitor ? $transformer::class : $transformer;
    }

    private function findInCallbackRewriters(callable $callback): ?string
    {
        foreach ($this->callbackRewriters as $key => $data) {
            if ($data['callback'] === $callback) {
                return $key;
            }
        }

        return null;
    }

    private function removeFromCallbackRewriters(callable $callback): void
    {
        $key = $this->findInCallbackRewriters($callback);
        if ($key !== null) {
            unset($this->callbackRewriters[$key]);
        }
    }

    private function findInRewriterTransformers(AstRewriter $rewriter): ?string
    {
        foreach ($this->rewriterTransformers as $key => $data) {
            if ($data['rewriter'] === $rewriter) {
                return $key;
            }
        }

        return null;
    }

    private function removeFromRewriterTransformers(AstRewriter $rewriter): void
    {
        $key = $this->findInRewriterTransformers($rewriter);
        if ($key !== null) {
            unset($this->rewriterTransformers[$key]);
        }
    }

    /**
     * Compute the highest specificity score among matching patterns for a path.
     *
     * Returns null when none of the patterns match the given `$path`.
     *
     * @param  list<string>  $patterns  Glob patterns to evaluate
     * @param  string  $path  Path to test
     */
    private function getBestMatchingScore(array $patterns, string $path): ?int
    {
        $bestScore = null;

        foreach ($patterns as $pattern) {
            if (PathMatcher::match($pattern, $path)) {
                $score = PathMatcher::specificityScore($pattern);
                $bestScore = max($bestScore ?? $score, $score);
            }
        }

        return $bestScore;
    }

    /**
     * @param  class-string<RewriteVisitor>  $class
     *
     * @throws InvalidArgumentException
     */
    private function validateRewriterClass(string $class): void
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Transformer class '{$class}' does not exist.");
        }

        if (! is_subclass_of($class, RewriteVisitor::class)) {
            throw new InvalidArgumentException("Transformer class '{$class}' must implement ".RewriteVisitor::class);
        }
    }

    /**
     * @param  array<int|string, string|array<string>>  $patterns
     * @return list<string>
     */
    private function flattenPatterns(array $patterns): array
    {
        return array_values(
            array_filter(
                Arr::flatten($patterns),
                fn ($p) => is_string($p) && $p !== ''
            )
        );
    }

    /**
     * Get ParserOptions built from this Enclave's configuration.
     */
    public function getParserOptions(): ParserOptions
    {
        $options = ParserOptions::make();

        foreach ($this->extensions as $class => $instance) {
            $ext = $instance ?? new $class;
            $options->extension($ext);
        }

        return $options;
    }

    /**
     * Get all rewriters in execution order.
     *
     * @param  null|callable(class-string<RewriteVisitor>):RewriteVisitor  $instantiator
     * @return list<RewriteVisitor|callable|AstRewriter>
     */
    public function getOrderedTransformItems(?callable $instantiator = null): array
    {
        $instantiator ??= static fn (string $class) => new $class;

        $items = [];

        foreach ($this->rewriters as $class => $data) {
            $items[] = [
                'type' => 'visitor',
                'priority' => $data['priority'],
                'sequence' => $data['sequence'],
                'class' => $class,
                'instance' => $data['instance'],
            ];
        }

        foreach ($this->callbackRewriters as $data) {
            $items[] = [
                'type' => 'callback',
                'priority' => PHP_INT_MIN,
                'sequence' => $data['sequence'],
                'callback' => $data['callback'],
            ];
        }

        foreach ($this->rewriterTransformers as $data) {
            $items[] = [
                'type' => 'rewriter',
                'priority' => PHP_INT_MIN,
                'sequence' => $data['sequence'],
                'rewriter' => $data['rewriter'],
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            return $a['sequence'] <=> $b['sequence'];
        });

        /** @var list<RewriteVisitor|callable|AstRewriter> $result */
        $result = [];
        foreach ($items as $item) {
            switch ($item['type']) {
                case 'visitor':
                    if ($item['instance'] instanceof RewriteVisitor) {
                        $result[] = $item['instance'];
                    } else {
                        /** @var class-string<RewriteVisitor> $class */
                        $class = $item['class'];
                        /** @var RewriteVisitor $visitor */
                        $visitor = $instantiator($class);
                        $result[] = $visitor;
                    }
                    break;
                case 'callback':
                    /** @var callable(): mixed $callback */
                    $callback = $item['callback'];
                    $result[] = $callback;
                    break;
                case 'rewriter':
                    /** @var AstRewriter $rewriter */
                    $rewriter = $item['rewriter'];
                    $result[] = $rewriter;
                    break;
            }
        }

        return $result;
    }

    /**
     * Apply all registered transformers to a document.
     *
     * @param  Document  $doc  The document to rewriteWith
     * @param  null|callable(class-string<RewriteVisitor>):RewriteVisitor  $instantiator
     * @return Document The transformed document
     */
    public function transformDocument(Document $doc, ?callable $instantiator = null): Document
    {
        $items = $this->getOrderedTransformItems($instantiator);

        foreach ($items as $item) {
            if ($item instanceof AstRewriter) {
                $doc = $item->rewrite($doc);
            } elseif ($item instanceof RewriteVisitor) {
                $rewriter = new Rewriter;
                $rewriter->addVisitor($item);
                $doc = $rewriter->rewrite($doc);
            } elseif (is_callable($item)) {
                $doc = $doc->rewriteWith($item);
            }
        }

        return $doc;
    }
}
