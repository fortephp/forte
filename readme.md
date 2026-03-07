# Forte

> the ... strongest part of the blade
>
> https://www.merriam-webster.com/dictionary/forte

Forte is a Laravel Blade parser and AST manipulation library.

## Quick Start

```php
use Forte\Facades\Forte;
use Forte\Rewriting\NodePath;

// Parse a Blade template into a document
$doc = Forte::parse('<div class="mt-4">Hello, {{ $name }}!</div>');

// Query with XPath
$divs = $doc->xpath('//div[@class]')->get();

// Rewrite (returns a new document, original is unchanged)
$newDoc = $doc->rewriteWith(function (NodePath $path) {
    if ($path->isTag('div')) {
        $path->removeClass('mt-4');
        $path->addClass('container');
    }
});

echo $newDoc->render(); // <div class="container">Hello, {{ $name }}!</div>
```

## Core Features

### Parsing

Forte's three-phase pipeline (lexer, tree builder, document) handles valid and malformed input alike, recovering gracefully with diagnostics.

```php
$doc = Forte::parse($bladeTemplate);
$doc = Forte::parseFile('resources/views/welcome.blade.php');
```

### Immutable Documents

Every mutation returns a new `Document` instance. Safe to hold references, chain transformations, and compare before/after states.

### Tree Traversal and Querying

Walk the tree, find nodes by predicate, or query with XPath 1.0. Blade constructs map to namespaced elements (`forte:if`, `forte:echo`) for XPath queries.

```php
// Walk all nodes
$doc->walk(fn ($node) => /* ... */);

// Find first match
$echo = $doc->find(fn ($n) => $n instanceof EchoNode);

// XPath
$doc->xpath('//forte:if')->get();
```

### Rewriting

The visitor pattern with queued operations prevents excessive intermediate documents. Use `NodePath` for all mutations, including CSS class helpers, attribute manipulation, and structural changes.

```php
$doc->rewriteWith(function (NodePath $path) {
    if ($path->isTag('center')) {
        $path->renameTag('div');
        $path->addClass('text-center');
    }
});
```

### Components and Directives

First-class support for Blade components (`<x-alert>`), slots (`<x-slot:header>`), and all directive types (standalone `@csrf`, blocks `@if...@endif`).

```php
$component = $doc->findComponentByName('x-alert');
$directive = $doc->find(fn ($n) => $n->isDirectiveNamed('foreach'));
```

### Node Builders

Construct synthetic nodes for replacements and insertions with a fluent API.

```php
use Forte\Rewriting\Builders\Builder;

Builder::element('div')->class('wrapper')->text('Hello');
Builder::directive('if', '($show)');
Builder::echo('$name');
```

To learn more, visit [https://fortephp.com](https://fortephp.com).

## License

Forte is free software, released under the MIT license.
