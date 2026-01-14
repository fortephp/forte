<?php

declare(strict_types=1);

use Forte\Parser\Directives\Directives;
use Forte\Tests\ForteTestCase;

uses(ForteTestCase::class)
    ->in(
        'Ast',
        'Document',
        'Elements',
        'HtmlParser',
        'Documents',
        'Diagnostics',
        'Lexer',
        'Parser',
        'Traversal',
        'Nodes',
        'Enclaves',
        'Validation',
        'Query',
        'Template',
        'Facades',
        'Extensions',
        'Rewriting',
    );

dataset('simple directives', function () {
    $directives = (new Directives)->loadDirectory(__DIR__.'/../resources/directives');

    return collect($directives->getUnpairedDirectiveNames())
        ->reject(fn ($name) => $name == 'php')
        ->map(fn ($name) => [$name])
        ->all();
});

dataset('blade docs', fn () => glob(__DIR__.'/Fixtures/Validation/laravel-documentation/*.blade.php'));
dataset('filament samples', fn () => glob(__DIR__.'/Fixtures/Validation/filament/*.blade.php'));

dataset('wpt html', function () {
    $dir = __DIR__.'/Fixtures/Validation/wpt-parsing';

    if (! is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'html') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
});

dataset('void elements', [
    ['area'],
    ['base'],
    ['br'],
    ['col'],
    ['embed'],
    ['hr'],
    ['img'],
    ['input'],
    ['link'],
    ['meta'],
    ['param'],
    ['source'],
    ['track'],
    ['wbr'],
]);

dataset('basic elements', [
    ['a', 'a'],
    ['abbr', 'abbr'],
    ['address', 'address'],
    ['article', 'article'],
    ['aside', 'aside'],
    ['audio', 'audio'],
    ['b', 'b'],
    ['bdi', 'bdi'],
    ['bdo', 'bdo'],
    ['blockquote', 'blockquote'],
    ['body', 'body'],
    ['button', 'button'],
    ['canvas', 'canvas'],
    ['caption', 'caption'],
    ['cite', 'cite'],
    ['code', 'code'],
    ['colgroup', 'colgroup'],
    ['data', 'data'],
    ['datalist', 'datalist'],
    ['dd', 'dd'],
    ['del', 'del'],
    ['details', 'details'],
    ['dfn', 'dfn'],
    ['dialog', 'dialog'],
    ['div', 'div'],
    ['dl', 'dl'],
    ['dt', 'dt'],
    ['em', 'em'],
    ['fieldset', 'fieldset'],
    ['figcaption', 'figcaption'],
    ['figure', 'figure'],
    ['footer', 'footer'],
    ['form', 'form'],
    ['h1', 'h1'],
    ['h2', 'h2'],
    ['h3', 'h3'],
    ['h4', 'h4'],
    ['h5', 'h5'],
    ['h6', 'h6'],
    ['head', 'head'],
    ['header', 'header'],
    ['hgroup', 'hgroup'],
    ['html', 'html'],
    ['i', 'i'],
    ['iframe', 'iframe'],
    ['ins', 'ins'],
    ['kbd', 'kbd'],
    ['label', 'label'],
    ['legend', 'legend'],
    ['li', 'li'],
    ['main', 'main'],
    ['map', 'map'],
    ['mark', 'mark'],
    ['meter', 'meter'],
    ['nav', 'nav'],
    ['noscript', 'noscript'],
    ['object', 'object'],
    ['ol', 'ol'],
    ['optgroup', 'optgroup'],
    ['option', 'option'],
    ['output', 'output'],
    ['p', 'p'],
    ['picture', 'picture'],
    ['pre', 'pre'],
    ['progress', 'progress'],
    ['q', 'q'],
    ['rp', 'rp'],
    ['rt', 'rt'],
    ['ruby', 'ruby'],
    ['s', 's'],
    ['samp', 'samp'],
    ['section', 'section'],
    ['select', 'select'],
    ['small', 'small'],
    ['span', 'span'],
    ['strong', 'strong'],
    ['sub', 'sub'],
    ['summary', 'summary'],
    ['sup', 'sup'],
    ['table', 'table'],
    ['tbody', 'tbody'],
    ['td', 'td'],
    ['template', 'template'],
    ['textarea', 'textarea'],
    ['tfoot', 'tfoot'],
    ['th', 'th'],
    ['thead', 'thead'],
    ['time', 'time'],
    ['title', 'title'],
    ['tr', 'tr'],
    ['u', 'u'],
    ['ul', 'ul'],
    ['var', 'var'],
    ['video', 'video'],

    ['DIV', 'DIV'],
    ['SpAn', 'SpAn'],
    ['hTmL', 'hTmL'],

    ['x1', 'x1'],
    ['a1b2', 'a1b2'],
    ['my-element', 'my-element'],
    ['data_element', 'data_element'],
    ['ns:tag', 'ns:tag'],
    ['weird_TAG', 'weird_TAG'],
    ['Custom123', 'Custom123'],
    ['TeSt-CaSe', 'TeSt-CaSe'],
    ['my:custom-element', 'my:custom-element'],
]);
