<?php

declare(strict_types=1);

namespace Forte\Enclaves;

use Forte\Ast\Document\Document;
use Forte\Parser\ParserOptions;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\RewriteVisitor;
use InvalidArgumentException;

class EnclavesManager
{
    /** @var array<string, Enclave> */
    private array $enclaves = [];

    private readonly Enclave $appEnclave;

    public function __construct()
    {
        $this->appEnclave = $this->createAppEnclave();
        $this->enclaves['{app}'] = $this->appEnclave;
    }

    /**
     * Get the default application Enclave.
     */
    public function defaultEnclave(): Enclave
    {
        return $this->appEnclave;
    }

    /**
     * Register a named Enclave.
     *
     * @param  string  $name  Unique identifier for the enclave
     * @param  Enclave  $enclave  The enclave instance to register
     *
     * @throws InvalidArgumentException
     */
    public function add(string $name, Enclave $enclave): self
    {
        $this->validateEnclaveName($name);
        $this->enclaves[$name] = $enclave;

        return $this;
    }

    /**
     * Create a new empty Enclave and register it under the given name.
     *
     * @param  string  $name  Enclave name
     */
    public function create(string $name): Enclave
    {
        $enclave = new Enclave;
        $this->add($name, $enclave);

        return $enclave;
    }

    /**
     * Retrieve a registered Enclave by name.
     *
     * @param  string  $name  The enclave name
     */
    public function get(string $name): ?Enclave
    {
        return $this->enclaves[$name] ?? null;
    }

    /**
     * Check if an Enclave with the given name is registered.
     *
     * @param  string  $name  The enclave name to check
     */
    public function has(string $name): bool
    {
        return isset($this->enclaves[$name]);
    }

    /**
     * Get the names of all registered Enclaves.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->enclaves);
    }

    /**
     * Get the total number of registered Enclaves.
     */
    public function count(): int
    {
        return count($this->enclaves);
    }

    /**
     * Get all registered Enclaves keyed by name.
     *
     * @return array<string, Enclave>
     */
    public function all(): array
    {
        return $this->enclaves;
    }

    /**
     * Create and register an Enclave for a package.
     *
     * The enclave is named `vendor:{package}` and preconfigured
     * to target that package's Blade views and assets.
     *
     * @param  string  $packageName  vendor/package
     * @param  string|null  $packagePath  Optional absolute path override to the package root
     */
    public function createForPackage(string $packageName, ?string $packagePath = null): Enclave
    {
        return $this->create("vendor:{$packageName}")
            ->forPackage($packageName, $packagePath);
    }

    /**
     * Include specific vendor packages in the default app Enclave.
     *
     * @param  string  ...$packageNames  One or more vendor/package names to include
     */
    public function includeVendorPackages(string ...$packageNames): self
    {
        $this->defaultEnclave()
            ->includeVendorPackage(...$packageNames);

        return $this;
    }

    /**
     * Include all vendor package views in the default app Enclave.
     */
    public function includeVendor(): self
    {
        $this->defaultEnclave()
            ->includeAllVendorViews();

        return $this;
    }

    /**
     * Exclude specific vendor packages from the default app Enclave.
     *
     * @param  string  ...$packageNames  One or more vendor/package names to exclude
     */
    public function excludeVendorPackages(string ...$packageNames): self
    {
        $this->defaultEnclave()
            ->excludeVendorPackage(...$packageNames);

        return $this;
    }

    /**
     * Check if a given path is covered by the specified Enclave's include/exclude rules.
     *
     * @param  string  $name  Enclave name to test against
     * @param  string  $path  Absolute file path to check
     */
    public function isPathInEnclave(string $name, string $path): bool
    {
        $enclave = $this->get($name);

        if (! $enclave) {
            return false;
        }

        return $enclave->matches($path);
    }

    /**
     * Check if there are any rewriters that apply to the given path.
     *
     * @param  string  $path  Absolute file path
     */
    public function hasRewritersForPath(string $path): bool
    {
        return ! empty($this->getMatchingEnclaves($path));
    }

    /**
     * Get all Enclaves whose path rules match the given file path.
     *
     * @param  string  $path  Absolute file path
     * @return list<Enclave>
     */
    public function getMatchingEnclaves(string $path): array
    {
        $matchingEnclaves = [];

        foreach ($this->enclaves as $enclave) {
            if ($enclave->matches($path)) {
                $matchingEnclaves[] = $enclave;
            }
        }

        return $matchingEnclaves;
    }

    /**
     * Resolve the ordered list of rewriters for a given path.
     *
     * @param  string  $path  Absolute file path to get transformers for
     * @return list<RewriteVisitor|class-string<RewriteVisitor>>
     */
    public function getRewriterClassesForPath(string $path): array
    {
        $combined = $this->combineTransformersFromMatchingEnclaves($path);

        /** @var array<class-string<RewriteVisitor>, array{priority: int, instance: RewriteVisitor|null}> $visitorData */
        $visitorData = [];
        foreach ($combined as $key => $data) {
            if ($data['type'] === 'visitor') {
                /** @var class-string<RewriteVisitor> $key */
                $visitorData[$key] = [
                    'priority' => $data['priority'],
                    'instance' => $data['instance'] ?? null,
                ];
            }
        }

        return RewriterPrioritizer::orderWithInstances($visitorData);
    }

    /**
     * Resolve all transformers for a file path in priority order.
     *
     * @param  string  $path  Absolute file path to get transformers for
     * @return list<RewriteVisitor|callable|AstRewriter|class-string<RewriteVisitor>>
     */
    public function getTransformersForPath(string $path): array
    {
        $combined = $this->combineTransformersFromMatchingEnclaves($path);

        uasort($combined, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            return $a['sequence'] <=> $b['sequence'];
        });

        return array_values(array_map(fn ($data) => $data['item'], $combined));
    }

    /**
     * Get instantiated rewriters for a file path in prioritized order.
     *
     * @param  string  $path  Absolute file path to resolve against enclaves
     * @param  null|callable(class-string<RewriteVisitor>):RewriteVisitor  $instantiator  Optional factory to instantiate transformers
     * @return list<RewriteVisitor>
     */
    public function getRewritersForPath(string $path, ?callable $instantiator = null): array
    {
        $orderedItems = $this->getRewriterClassesForPath($path);
        $instantiator ??= static function (string $class): RewriteVisitor {
            $instance = new $class;

            if (! $instance instanceof RewriteVisitor) {
                throw new InvalidArgumentException("Class {$class} must implement RewriteVisitor");
            }

            return $instance;
        };

        /** @var list<RewriteVisitor> $rewriters */
        $rewriters = [];

        foreach ($orderedItems as $item) {
            if ($item instanceof RewriteVisitor) {
                $rewriters[] = $item;

                continue;
            }

            $rewriters[] = $instantiator($item);
        }

        return $rewriters;
    }

    /**
     * Get all rewriteWith items for a path in execution order.
     *
     * @param  string  $path  Absolute file path
     * @param  null|callable(class-string<RewriteVisitor>):RewriteVisitor  $instantiator
     * @return list<RewriteVisitor|callable|AstRewriter>
     */
    public function getRewriteItemsForPath(string $path, ?callable $instantiator = null): array
    {
        $allItems = [];

        foreach ($this->getMatchingEnclaves($path) as $enclave) {
            $items = $enclave->getOrderedTransformItems($instantiator);
            $allItems = array_merge($allItems, $items);
        }

        return $allItems;
    }

    /**
     * Get merged ParserOptions for a file path.
     *
     * @param  string  $path  Absolute file path
     */
    public function getParserOptionsForPath(string $path): ParserOptions
    {
        $options = ParserOptions::make();

        foreach ($this->getMatchingEnclaves($path) as $enclave) {
            if ($enclave->hasExtensions()) {
                $options->merge($enclave->getParserOptions());
            }
        }

        return $options;
    }

    /**
     * Transform a document using all matching enclaves for a path.
     *
     * @param  Document  $doc  The document to rewriteWith
     * @param  string  $path  The file path to match against enclaves
     * @param  null|callable(class-string<RewriteVisitor>):RewriteVisitor  $instantiator
     */
    public function transformDocument(Document $doc, string $path, ?callable $instantiator = null): Document
    {
        $items = $this->getRewriteItemsForPath($path, $instantiator);

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

    private function createAppEnclave(): Enclave
    {
        return (new Enclave)
            ->include(resource_path('views/**'))
            ->exclude(resource_path('views/vendor/**'))
            ->exclude(resource_path('views/mail/**'));
    }

    private function validateEnclaveName(string $name): void
    {
        if (str_starts_with($name, '{')) {
            throw new InvalidArgumentException('Enclave names must not start with {');
        }
    }

    /**
     * @return array<string, array{type: string, priority: int, sequence: int, item: RewriteVisitor|callable|AstRewriter|class-string<RewriteVisitor>}>
     */
    private function combineTransformersFromMatchingEnclaves(string $path): array
    {
        /** @var array<string, array{type: string, priority: int, sequence: int, item: RewriteVisitor|callable|AstRewriter|class-string<RewriteVisitor>}> $combinedData */
        $combinedData = [];

        foreach ($this->getMatchingEnclaves($path) as $enclave) {
            foreach ($enclave->getRewriterPriorities() as $key => $data) {
                $combinedData = $this->mergeTransformerData($combinedData, $key, $data);
            }
        }

        return $combinedData;
    }

    /**
     * @param  array<string, array{type: string, priority: int, sequence: int, item: RewriteVisitor|callable|AstRewriter|class-string<RewriteVisitor>}>  $combinedData
     * @param  array{type: string, priority: int, sequence: int, item: RewriteVisitor|callable|AstRewriter|class-string<RewriteVisitor>}  $data
     * @return array<string, array{type: string, priority: int, sequence: int, item: RewriteVisitor|callable|AstRewriter|class-string<RewriteVisitor>}>
     */
    private function mergeTransformerData(array $combinedData, string $key, array $data): array
    {
        if ($data['type'] === 'callback' || $data['type'] === 'rewriter') {
            $combinedData[$key] = $data;

            return $combinedData;
        }

        if (! isset($combinedData[$key])) {
            $combinedData[$key] = $data;

            return $combinedData;
        }

        $existing = $combinedData[$key];

        if ($data['priority'] > $existing['priority']) {
            $combinedData[$key] = $data;

            return $combinedData;
        }

        if ($data['priority'] === $existing['priority']) {
            $existingItem = $existing['item'];
            $newItem = $data['item'];

            if (is_string($existingItem) && $newItem instanceof RewriteVisitor) {
                $combinedData[$key]['item'] = $newItem;
                if ($data['type'] === 'visitor') {
                    $combinedData[$key]['instance'] = $newItem;
                }
            }
        }

        return $combinedData;
    }
}
