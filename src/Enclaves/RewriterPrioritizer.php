<?php

declare(strict_types=1);

namespace Forte\Enclaves;

use Forte\Rewriting\RewriteVisitor;
use InvalidArgumentException;

class RewriterPrioritizer
{
    /**
     * Orders transformer classes by numeric priority, highest first.
     *
     * @param  array<class-string<RewriteVisitor>, int>  $priorities  Map of transformer class to its priority
     * @return list<class-string<RewriteVisitor>>
     *
     * @throws InvalidArgumentException
     */
    public static function orderByPriority(array $priorities): array
    {
        self::validatePriorities($priorities);

        $groupedByPriority = self::groupByPriority($priorities);

        return self::flattenGroups($groupedByPriority);
    }

    /**
     * Orders transformers by priority.
     *
     * @param  array<class-string<RewriteVisitor>, array{priority:int, instance:RewriteVisitor|null}>  $combinedData  Map of class to priority and optional instance
     * @return list<RewriteVisitor|class-string<RewriteVisitor>>
     *
     * @throws InvalidArgumentException
     */
    public static function orderWithInstances(array $combinedData): array
    {
        self::validateCombinedData($combinedData);

        $priorities = self::extractPriorities($combinedData);
        $groupedByPriority = self::groupByPriority($priorities);

        return self::flattenGroupsWithInstances($groupedByPriority, $combinedData);
    }

    /**
     * Extracts the numeric priority for each transformer class from the provided data.
     *
     * @param  array<class-string<RewriteVisitor>, array{priority:int, instance:RewriteVisitor|null}>  $combinedData
     * @return array<class-string<RewriteVisitor>, int>
     */
    private static function extractPriorities(array $combinedData): array
    {
        return array_map(fn ($data) => $data['priority'], $combinedData);
    }

    /**
     * Groups class names by their priority and sorts groups in descending priority.
     *
     * @param  array<class-string<RewriteVisitor>, int>  $priorities
     * @return array<int, list<class-string<RewriteVisitor>>>
     */
    private static function groupByPriority(array $priorities): array
    {
        $grouped = [];

        foreach ($priorities as $class => $priority) {
            $grouped[$priority][] = $class;
        }

        krsort($grouped);

        return $grouped;
    }

    /**
     * Flattens grouped class names into a single ordered list.
     *
     * @param  array<int, list<class-string<RewriteVisitor>>>  $groupedByPriority
     * @return list<class-string<RewriteVisitor>>
     */
    private static function flattenGroups(array $groupedByPriority): array
    {
        $orderedClasses = [];

        foreach ($groupedByPriority as $classes) {
            sort($classes, SORT_STRING);

            foreach ($classes as $class) {
                $orderedClasses[] = $class;
            }
        }

        return $orderedClasses;
    }

    /**
     * Flattens grouped classes to an ordered list, substituting provided instances where available.
     *
     * Ties are resolved by sorting class names alphabetically within each priority group.
     *
     * @param  array<int, list<class-string<RewriteVisitor>>>  $groupedByPriority
     * @param  array<class-string<RewriteVisitor>, array{priority:int, instance:RewriteVisitor|null}>  $combinedData
     * @return list<RewriteVisitor|class-string<RewriteVisitor>>
     */
    private static function flattenGroupsWithInstances(array $groupedByPriority, array $combinedData): array
    {
        $orderedTransformers = [];

        foreach ($groupedByPriority as $classes) {
            sort($classes, SORT_STRING);

            foreach ($classes as $class) {
                $data = $combinedData[$class];
                $orderedTransformers[] = $data['instance'] instanceof RewriteVisitor
                    ? $data['instance']
                    : $class;
            }
        }

        return $orderedTransformers;
    }

    /**
     * Validates that the priorities map uses class-string keys and integer values.
     *
     * @param  array<class-string<RewriteVisitor>, int>  $priorities
     *
     * @throws InvalidArgumentException
     */
    private static function validatePriorities(array $priorities): void
    {
        foreach ($priorities as $class => $priority) {
            if (! is_string($class)) {
                throw new InvalidArgumentException(
                    'Priorities array keys must be class-string values, got '.gettype($class)
                );
            }

            if (! is_int($priority)) {
                throw new InvalidArgumentException(
                    "Priority for class '{$class}' must be an integer, got ".gettype($priority)
                );
            }
        }
    }

    /**
     * @param  array<class-string<RewriteVisitor>, array{priority:int, instance:RewriteVisitor|null}>  $combinedData
     *
     * @throws InvalidArgumentException
     */
    private static function validateCombinedData(array $combinedData): void
    {
        foreach ($combinedData as $class => $data) {
            if (! is_string($class)) {
                throw new InvalidArgumentException(
                    'Combined data array keys must be class-string values, got '.gettype($class)
                );
            }

            if (! is_array($data)) {
                throw new InvalidArgumentException(
                    "Combined data for class '{$class}' must be an array, got ".gettype($data)
                );
            }

            if (! array_key_exists('priority', $data)) {
                throw new InvalidArgumentException(
                    "Combined data for class '{$class}' must have a 'priority' key"
                );
            }

            if (! is_int($data['priority'])) {
                throw new InvalidArgumentException(
                    "Priority for class '{$class}' must be an integer, got ".gettype($data['priority'])
                );
            }

            if (! array_key_exists('instance', $data)) {
                throw new InvalidArgumentException(
                    "Combined data for class '{$class}' must have an 'instance' key"
                );
            }

            if ($data['instance'] !== null && ! $data['instance'] instanceof RewriteVisitor) {
                throw new InvalidArgumentException(
                    "Instance for class '{$class}' must be null or an instance of ".RewriteVisitor::class
                );
            }
        }
    }
}
