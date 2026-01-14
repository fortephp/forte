<?php

declare(strict_types=1);

dataset('operation contexts', function (): iterable {
    yield 'root level' => [
        'context' => [
            'wrap' => ['<div>content</div>', '<div></div>'],
            'sibling_remove' => [
                '<div>first</div><span>second</span><p>third</p>',
                '<div>first</div><p>third</p>',
            ],
        ],
        'remove' => [
            'element' => ['<div>content</div>', ''],
            'text' => ['hello world', ''],
            'echo' => ['{{ $variable }}', ''],
            'comment' => ['<!-- comment -->', ''],
        ],
        'replaceWith' => [
            'element_to_text' => ['<div>content</div>', 'replaced'],
            'element_to_element' => ['<div>content</div>', '<span>new</span>'],
        ],
        'insertBefore' => [
            'element' => ['<div>content</div>', 'prefix<div>content</div>'],
        ],
        'insertAfter' => [
            'element' => ['<div>content</div>', '<div>content</div>suffix'],
        ],
        'wrapWith' => [
            'element' => ['<span>content</span>', '<div class="wrapper"><span>content</span></div>'],
        ],
    ];

    yield 'element nested' => [
        'context' => [
            'wrap' => ['<div><span>content</span></div>', '<div></div>'],
            'sibling_remove' => [
                '<div><span>first</span><p>second</p><em>third</em></div>',
                '<div><span>first</span><em>third</em></div>',
            ],
        ],
        'remove' => [
            'element' => ['<div><span>content</span></div>', '<div></div>'],
            'text' => ['<div>text to remove</div>', '<div></div>'],
            'echo' => ['<div>{{ $variable }}</div>', '<div></div>'],
            'comment' => ['<div><!-- comment --></div>', '<div></div>'],
        ],
        'replaceWith' => [
            'element_to_text' => ['<div><span>old</span></div>', '<div>replaced</div>'],
            'element_to_element' => ['<div><span>old</span></div>', '<div><strong>new</strong></div>'],
        ],
        'insertBefore' => [
            'element' => ['<div><span>content</span></div>', '<div>prefix<span>content</span></div>'],
        ],
        'insertAfter' => [
            'element' => ['<div><span>content</span></div>', '<div><span>content</span>suffix</div>'],
        ],
        'wrapWith' => [
            'element' => ['<div><span>content</span></div>', '<div><em><span>content</span></em></div>'],
        ],
    ];
});

dataset('remove contexts', function (): iterable {
    yield 'root element' => [
        'input' => '<div>content</div>',
        'expected' => '',
        'nodeType' => 'element',
        'targetTag' => 'div',
    ];

    yield 'root text' => [
        'input' => 'hello world',
        'expected' => '',
        'nodeType' => 'text',
        'targetTag' => null,
    ];

    yield 'root echo' => [
        'input' => '{{ $variable }}',
        'expected' => '',
        'nodeType' => 'echo',
        'targetTag' => null,
    ];

    yield 'root comment' => [
        'input' => '<!-- comment -->',
        'expected' => '',
        'nodeType' => 'comment',
        'targetTag' => null,
    ];

    yield 'root sibling removal' => [
        'input' => '<div>first</div><span>second</span><p>third</p>',
        'expected' => '<div>first</div><p>third</p>',
        'nodeType' => 'element',
        'targetTag' => 'span',
    ];

    yield 'nested element' => [
        'input' => '<div><span>content</span></div>',
        'expected' => '<div></div>',
        'nodeType' => 'element',
        'targetTag' => 'span',
    ];

    yield 'nested text' => [
        'input' => '<div>text to remove</div>',
        'expected' => '<div></div>',
        'nodeType' => 'text',
        'targetTag' => null,
        'textMatch' => 'remove',
    ];

    yield 'nested echo' => [
        'input' => '<div>{{ $variable }}</div>',
        'expected' => '<div></div>',
        'nodeType' => 'echo',
        'targetTag' => null,
    ];

    yield 'nested comment' => [
        'input' => '<div><!-- comment --></div>',
        'expected' => '<div></div>',
        'nodeType' => 'comment',
        'targetTag' => null,
    ];

    yield 'nested sibling removal' => [
        'input' => '<div><span>first</span><p>second</p><em>third</em></div>',
        'expected' => '<div><span>first</span><em>third</em></div>',
        'nodeType' => 'element',
        'targetTag' => 'p',
    ];

    yield 'deeply nested element' => [
        'input' => '<div><ul><li><span>deep</span></li></ul></div>',
        'expected' => '<div><ul><li></li></ul></div>',
        'nodeType' => 'element',
        'targetTag' => 'span',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<span>content</span>@endif',
        'expected' => '@if($show)@endif',
        'nodeType' => 'element',
        'targetTag' => 'span',
    ];

    yield 'directive nested echo' => [
        'input' => '@if($show){{ $variable }}@endif',
        'expected' => '@if($show)@endif',
        'nodeType' => 'echo',
        'targetTag' => null,
    ];

    yield 'directive nested comment' => [
        'input' => '@if($show)<!-- comment -->@endif',
        'expected' => '@if($show)@endif',
        'nodeType' => 'comment',
        'targetTag' => null,
    ];

    yield 'element in directive - nested element' => [
        'input' => '@if($show)<div><span>content</span></div>@endif',
        'expected' => '@if($show)<div></div>@endif',
        'nodeType' => 'element',
        'targetTag' => 'span',
    ];

    yield 'element in directive - nested text' => [
        'input' => '@if($show)<div>text to remove</div>@endif',
        'expected' => '@if($show)<div></div>@endif',
        'nodeType' => 'text',
        'targetTag' => null,
        'textMatch' => 'remove',
    ];

    yield 'element in directive - deeply nested' => [
        'input' => '@if($show)<div><ul><li><span>deep</span></li></ul></div>@endif',
        'expected' => '@if($show)<div><ul><li></li></ul></div>@endif',
        'nodeType' => 'element',
        'targetTag' => 'span',
    ];
});

dataset('replaceWith contexts', function (): iterable {
    yield 'root element to text' => [
        'input' => '<div>content</div>',
        'expected' => 'replaced',
        'targetTag' => 'div',
        'replacement' => 'text',
    ];

    yield 'root element to element' => [
        'input' => '<div>content</div>',
        'expected' => '<strong>new</strong>',
        'targetTag' => 'div',
        'replacement' => 'element',
    ];

    yield 'nested element to text' => [
        'input' => '<div><span>old</span></div>',
        'expected' => '<div>replaced</div>',
        'targetTag' => 'span',
        'replacement' => 'text',
    ];

    yield 'nested element to element' => [
        'input' => '<div><span>old</span></div>',
        'expected' => '<div><strong>new</strong></div>',
        'targetTag' => 'span',
        'replacement' => 'element',
    ];

    yield 'directive nested element to text' => [
        'input' => '@if($show)<span>old</span>@endif',
        'expected' => '@if($show)replaced@endif',
        'targetTag' => 'span',
        'replacement' => 'text',
    ];

    yield 'directive nested element to element' => [
        'input' => '@if($show)<span>old</span>@endif',
        'expected' => '@if($show)<strong>new</strong>@endif',
        'targetTag' => 'span',
        'replacement' => 'element',
    ];

    yield 'element in directive to text' => [
        'input' => '@if($show)<div><span>old</span></div>@endif',
        'expected' => '@if($show)<div>replaced</div>@endif',
        'targetTag' => 'span',
        'replacement' => 'text',
    ];

    yield 'element in directive to element' => [
        'input' => '@if($show)<div><span>old</span></div>@endif',
        'expected' => '@if($show)<div><strong>new</strong></div>@endif',
        'targetTag' => 'span',
        'replacement' => 'element',
    ];
});

dataset('insertBefore contexts', function (): iterable {
    yield 'root element' => [
        'input' => '<div>content</div>',
        'expected' => 'prefix<div>content</div>',
        'targetTag' => 'div',
    ];

    yield 'nested element' => [
        'input' => '<div><span>content</span></div>',
        'expected' => '<div>prefix<span>content</span></div>',
        'targetTag' => 'span',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<span>content</span>@endif',
        'expected' => '@if($show)prefix<span>content</span>@endif',
        'targetTag' => 'span',
    ];

    yield 'element in directive' => [
        'input' => '@if($show)<div><span>content</span></div>@endif',
        'expected' => '@if($show)<div>prefix<span>content</span></div>@endif',
        'targetTag' => 'span',
    ];
});

dataset('insertAfter contexts', function (): iterable {
    yield 'root element' => [
        'input' => '<div>content</div>',
        'expected' => '<div>content</div>suffix',
        'targetTag' => 'div',
    ];

    yield 'nested element' => [
        'input' => '<div><span>content</span></div>',
        'expected' => '<div><span>content</span>suffix</div>',
        'targetTag' => 'span',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<span>content</span>@endif',
        'expected' => '@if($show)<span>content</span>suffix@endif',
        'targetTag' => 'span',
    ];

    yield 'element in directive' => [
        'input' => '@if($show)<div><span>content</span></div>@endif',
        'expected' => '@if($show)<div><span>content</span>suffix</div>@endif',
        'targetTag' => 'span',
    ];
});

dataset('wrapWith contexts', function (): iterable {
    yield 'root element' => [
        'input' => '<span>content</span>',
        'expected' => '<div class="wrapper"><span>content</span></div>',
        'targetTag' => 'span',
    ];

    yield 'nested element' => [
        'input' => '<div><span>content</span></div>',
        'expected' => '<div><em><span>content</span></em></div>',
        'targetTag' => 'span',
        'wrapTag' => 'em',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<span>content</span>@endif',
        'expected' => '@if($show)<em><span>content</span></em>@endif',
        'targetTag' => 'span',
        'wrapTag' => 'em',
    ];

    yield 'element in directive' => [
        'input' => '@if($show)<div><span>content</span></div>@endif',
        'expected' => '@if($show)<div><em><span>content</span></em></div>@endif',
        'targetTag' => 'span',
        'wrapTag' => 'em',
    ];
});

dataset('unwrap contexts', function (): iterable {
    yield 'root element' => [
        'input' => '<div><span>a</span><span>b</span></div>',
        'expected' => '<span>a</span><span>b</span>',
        'targetTag' => 'div',
    ];

    yield 'nested element' => [
        'input' => '<div><span><em>a</em><em>b</em></span></div>',
        'expected' => '<div><em>a</em><em>b</em></div>',
        'targetTag' => 'span',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<div><span>a</span><span>b</span></div>@endif',
        'expected' => '@if($show)<span>a</span><span>b</span>@endif',
        'targetTag' => 'div',
    ];

    yield 'element in directive' => [
        'input' => '@if($show)<div><span><em>a</em><em>b</em></span></div>@endif',
        'expected' => '@if($show)<div><em>a</em><em>b</em></div>@endif',
        'targetTag' => 'span',
    ];
});

dataset('setAttribute contexts', function (): iterable {
    yield 'root element new attr' => [
        'input' => '<div>content</div>',
        'expected' => '<div id="main">content</div>',
        'targetTag' => 'div',
        'attr' => 'id',
        'value' => 'main',
    ];

    yield 'root element overwrite' => [
        'input' => '<div class="old">content</div>',
        'expected' => '<div class="new">content</div>',
        'targetTag' => 'div',
        'attr' => 'class',
        'value' => 'new',
    ];

    yield 'nested element' => [
        'input' => '<div><span>content</span></div>',
        'expected' => '<div><span id="nested">content</span></div>',
        'targetTag' => 'span',
        'attr' => 'id',
        'value' => 'nested',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<span>content</span>@endif',
        'expected' => '@if($show)<span id="conditional">content</span>@endif',
        'targetTag' => 'span',
        'attr' => 'id',
        'value' => 'conditional',
    ];

    yield 'element in directive' => [
        'input' => '@if($show)<div><span>content</span></div>@endif',
        'expected' => '@if($show)<div><span id="deep">content</span></div>@endif',
        'targetTag' => 'span',
        'attr' => 'id',
        'value' => 'deep',
    ];
});

dataset('addClass contexts', function (): iterable {
    yield 'root element no class' => [
        'input' => '<div>content</div>',
        'expected' => '<div class="added">content</div>',
        'targetTag' => 'div',
        'class' => 'added',
    ];

    yield 'root element with class' => [
        'input' => '<div class="existing">content</div>',
        'expected' => '<div class="existing added">content</div>',
        'targetTag' => 'div',
        'class' => 'added',
    ];

    yield 'nested element' => [
        'input' => '<div><span>content</span></div>',
        'expected' => '<div><span class="added">content</span></div>',
        'targetTag' => 'span',
        'class' => 'added',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<span>content</span>@endif',
        'expected' => '@if($show)<span class="added">content</span>@endif',
        'targetTag' => 'span',
        'class' => 'added',
    ];

    yield 'element in directive' => [
        'input' => '@if($show)<div><span>content</span></div>@endif',
        'expected' => '@if($show)<div><span class="added">content</span></div>@endif',
        'targetTag' => 'span',
        'class' => 'added',
    ];
});

dataset('renameTag contexts', function (): iterable {
    yield 'root element' => [
        'input' => '<div>content</div>',
        'expected' => '<section>content</section>',
        'targetTag' => 'div',
        'newTag' => 'section',
    ];

    yield 'root element with attrs' => [
        'input' => '<div class="container" id="main">content</div>',
        'expected' => '<article class="container" id="main">content</article>',
        'targetTag' => 'div',
        'newTag' => 'article',
    ];

    yield 'nested element' => [
        'input' => '<div><span>content</span></div>',
        'expected' => '<div><strong>content</strong></div>',
        'targetTag' => 'span',
        'newTag' => 'strong',
    ];

    yield 'directive nested element' => [
        'input' => '@if($show)<span>content</span>@endif',
        'expected' => '@if($show)<em>content</em>@endif',
        'targetTag' => 'span',
        'newTag' => 'em',
    ];

    yield 'element in directive' => [
        'input' => '@if($show)<div><span>content</span></div>@endif',
        'expected' => '@if($show)<div><strong>content</strong></div>@endif',
        'targetTag' => 'span',
        'newTag' => 'strong',
    ];
});
