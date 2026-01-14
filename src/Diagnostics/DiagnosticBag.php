<?php

declare(strict_types=1);

namespace Forte\Diagnostics;

use Countable;
use Forte\Extensions\ForteExtension;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Diagnostic>
 */
class DiagnosticBag implements Countable, IteratorAggregate
{
    /** @var Collection<int, Diagnostic> */
    private Collection $items;

    public function __construct()
    {
        $this->items = new Collection;
    }

    /**
     * Add a diagnostic.
     */
    public function add(Diagnostic $diagnostic): self
    {
        $this->items->push($diagnostic);

        return $this;
    }

    /**
     * Add multiple diagnostics.
     *
     * @param  iterable<Diagnostic>  $diagnostics
     */
    public function addMany(iterable $diagnostics): self
    {
        foreach ($diagnostics as $diagnostic) {
            $this->items->push($diagnostic);
        }

        return $this;
    }

    /**
     * Collect diagnostics from all extensions.
     *
     * @param  iterable<ForteExtension>  $extensions
     */
    public function collectFrom(iterable $extensions): self
    {
        foreach ($extensions as $extension) {
            if (method_exists($extension, 'getDiagnostics')) {
                /** @var iterable<Diagnostic> $diagnostics */
                $diagnostics = $extension->getDiagnostics();
                $this->addMany($diagnostics);
            }
        }

        return $this;
    }

    /**
     * Check if there are any diagnostics.
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Check if there are any diagnostics.
     */
    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return $this->items->contains(fn (Diagnostic $d) => $d->isError());
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return $this->items->contains(fn (Diagnostic $d) => $d->isWarning());
    }

    /**
     * Get all diagnostics as array.
     *
     * @return array<int, Diagnostic>
     */
    public function all(): array
    {
        return $this->items->all();
    }

    /**
     * Get the first diagnostic.
     */
    public function first(): ?Diagnostic
    {
        return $this->items->first();
    }

    /**
     * Get the last diagnostic.
     */
    public function last(): ?Diagnostic
    {
        return $this->items->last();
    }

    /**
     * Get errors only.
     *
     * @return Collection<int, Diagnostic>
     */
    public function errors(): Collection
    {
        return $this->items->filter(fn (Diagnostic $d) => $d->isError())->values();
    }

    /**
     * Get warnings only.
     *
     * @return Collection<int, Diagnostic>
     */
    public function warnings(): Collection
    {
        return $this->items->filter(fn (Diagnostic $d) => $d->isWarning())->values();
    }

    /**
     * Get info diagnostics only.
     *
     * @return Collection<int, Diagnostic>
     */
    public function info(): Collection
    {
        return $this->items->filter(fn (Diagnostic $d) => $d->isInfo())->values();
    }

    /**
     * Get hints only.
     *
     * @return Collection<int, Diagnostic>
     */
    public function hints(): Collection
    {
        return $this->items->filter(fn (Diagnostic $d) => $d->isHint())->values();
    }

    /**
     * Filter diagnostics by severity (minimum level).
     *
     * @return Collection<int, Diagnostic>
     */
    public function atLeast(DiagnosticSeverity $severity): Collection
    {
        return $this->items->filter(fn (Diagnostic $d) => $d->severity->isAtLeast($severity))->values();
    }

    /**
     * Filter diagnostics by source.
     *
     * @return Collection<int, Diagnostic>
     */
    public function fromSource(string $source): Collection
    {
        return $this->items->filter(fn (Diagnostic $d) => $d->source === $source)->values();
    }

    /**
     * Filter diagnostics by position range.
     *
     * @return Collection<int, Diagnostic>
     */
    public function inRange(int $start, int $end): Collection
    {
        return $this->items->filter(fn (Diagnostic $d) => $d->start >= $start && $d->end <= $end)->values();
    }

    /**
     * Filter diagnostics using a callback.
     *
     * @param  callable(Diagnostic): bool  $callback
     * @return Collection<int, Diagnostic>
     */
    public function filter(callable $callback): Collection
    {
        return $this->items->filter($callback)->values();
    }

    /**
     * Map diagnostics to a new collection.
     *
     * @template TMapValue
     *
     * @param  callable(Diagnostic): TMapValue  $callback
     * @return Collection<int, TMapValue>
     */
    public function map(callable $callback): Collection
    {
        return $this->items->map($callback);
    }

    /**
     * Execute a callback for each diagnostic.
     *
     * @param  callable(Diagnostic): void  $callback
     */
    public function each(callable $callback): self
    {
        $this->items->each($callback);

        return $this;
    }

    public function sortByPosition(): self
    {
        $this->items = $this->items->sortBy('start')->values();

        return $this;
    }

    public function sortBySeverity(): self
    {
        $this->items = $this->items->sortBy(fn (Diagnostic $d) => $d->severity->value)->values();

        return $this;
    }

    /**
     * Get diagnostics, grouped by source.
     *
     * @return Collection<string, Collection<int, Diagnostic>>
     */
    public function groupBySource(): Collection
    {
        return $this->items->groupBy('source');
    }

    /**
     * Clear all diagnostics.
     */
    public function clear(): self
    {
        $this->items = new Collection;

        return $this;
    }

    /**
     * Get count of diagnostics.
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Get iterator for diagnostics.
     *
     * @return Traversable<int, Diagnostic>
     */
    public function getIterator(): Traversable
    {
        return $this->items->getIterator();
    }

    public function format(string $source = ''): string
    {
        if ($this->isEmpty()) {
            return 'No diagnostics.';
        }

        return $this->items
            ->sortBy('start')
            ->sortBy(fn (Diagnostic $d) => $d->severity->value)
            ->map(function (Diagnostic $diagnostic) use ($source) {
                $prefix = '';
                if ($source !== '') {
                    $lineNum = substr_count(substr($source, 0, $diagnostic->start), "\n") + 1;
                    $lastNewline = strrpos(substr($source, 0, $diagnostic->start), "\n");
                    $column = $lastNewline === false ? $diagnostic->start + 1 : $diagnostic->start - $lastNewline;
                    $prefix = "{$lineNum}:{$column} ";
                }

                return $prefix.$diagnostic;
            })
            ->implode("\n");
    }

    /**
     * @return Collection<int, Diagnostic>
     */
    public function toCollection(): Collection
    {
        return $this->items;
    }
}
