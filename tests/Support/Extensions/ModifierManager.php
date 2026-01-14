<?php

declare(strict_types=1);

namespace Forte\Tests\Support\Extensions;

class ModifierManager
{
    /**
     * @var array<string, string|callable>
     */
    private array $modifiers = [];

    /**
     * @param  string  $name  The modifier name
     * @param  string|callable  $template  Template string or callback
     * @return $this
     */
    public function register(string $name, string|callable $template): static
    {
        $this->modifiers[$name] = $template;

        return $this;
    }

    /**
     * @param  array<string, string|callable>  $modifiers
     * @return $this
     */
    public function registerMany(array $modifiers): static
    {
        foreach ($modifiers as $name => $template) {
            $this->register($name, $template);
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->modifiers[$name]);
    }

    /**
     * @return array<string>
     */
    public function all(): array
    {
        return array_keys($this->modifiers);
    }

    /**
     * @param  string  $expression  The base expression
     * @param  array  $modifiers  Array of modifier data [{name: string, arguments: array}, ...]
     */
    public function compile(string $expression, array $modifiers): string
    {
        $result = $expression;

        foreach ($modifiers as $modifier) {
            $name = $modifier['name'];
            $arguments = $modifier['arguments'] ?? [];

            if (! $this->has($name)) {
                continue;
            }

            $result = $this->applyModifier($result, $name, $arguments);
        }

        return $result;
    }

    private function applyModifier(string $expression, string $name, array $arguments): string
    {
        $template = $this->modifiers[$name];

        if (is_callable($template)) {
            return $template($expression, ...$arguments);
        }

        return $this->compileTemplate($template, $expression, $arguments);
    }

    private function compileTemplate(string $template, string $expression, array $arguments): string
    {
        $wrappedExpression = $this->shouldWrapExpression($expression)
            ? "({$expression})"
            : $expression;

        // First replace {0}, {1}, etc. with arguments
        $result = $template;
        foreach ($arguments as $index => $value) {
            $result = str_replace("{{$index}}", $value, $result);
        }

        return preg_replace('/(?<!\?)\?(?!\?)/', $wrappedExpression, $result);
    }

    private function shouldWrapExpression(string $expression): bool
    {
        $expression = trim($expression);

        // Simple variable ($var)
        if (preg_match('/^\$\w+$/', $expression)) {
            return false;
        }

        // Property access ($var->prop)
        if (preg_match('/^\$\w+(->\w+|\[\w+\])*$/', $expression)) {
            return false;
        }

        // Function call (foo(...))
        if (preg_match('/^\w+\(.*\)$/', $expression)) {
            return false;
        }

        // Contains operators or complex expressions
        if (preg_match('/(\?\?|\|\||&&|[+\-*\/%<>=!])/', $expression)) {
            return true;
        }

        return false;
    }

    public static function withDefaults(): static
    {
        $manager = new static;

        return $manager->registerMany([
            'upper' => 'strtoupper(?)',
            'lower' => 'strtolower(?)',
            'ucfirst' => 'ucfirst(?)',
            'title' => 'ucwords(?)',
            'trim' => 'trim(?)',
            'rtrim' => 'rtrim(?)',
            'ltrim' => 'ltrim(?)',

            'substr' => 'substr(?, {0}, {1})',
            'limit' => 'substr(?, 0, {0})',
            'replace' => 'str_replace({0}, {1}, ?)',

            'default' => '? ?? {0}',
            'escape' => 'htmlspecialchars(?, ENT_QUOTES)',
            'json' => 'json_encode(?)',

            'when' => '{0} ? ? : {1}',
        ]);
    }
}
