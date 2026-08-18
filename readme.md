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
use Forte\Ast\TraversalOptions;

// Lazy, fluent collections with optional name filters
$forms = $doc->queryElements('form');
$conditionals = $doc->queryBlockDirectives(['if', 'unless']);
$components = $doc->queryComponents(['x-alert', 'livewire:*']);

// Fast exact lookups and convenient existence checks
$navigation = $doc->elementById('primary-navigation');
$hasHead = $doc->hasElement('head');

// Attribute semantics live on Attribute; ElementNode provides null-safe proxies
$form = $doc->firstElement('form');
$method = $form?->attribute('method');
$isDynamic = $method?->isDynamic() ?? false;
$staticMethod = $form?->staticAttributeValueLower('method');
$classes = $form?->attributeTokens('class') ?? [];

// Navigate relationships without rebuilding ancestor/descendant loops
$owner = $navigation?->closestElement('header');
$links = $navigation?->descendantElements('a');

// Include attribute/tag-name internals when a deep semantic scan is needed
$allEchoes = $doc->allEchoes(TraversalOptions::deep());

// XPath
$doc->xpath('//forte:if')->get();
```

The original `getElements()`, `findElementsByName()`, magic collection properties,
and other released query methods remain available. The `query*` methods add
Laravel-style lazy collections without changing those existing contracts.

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
