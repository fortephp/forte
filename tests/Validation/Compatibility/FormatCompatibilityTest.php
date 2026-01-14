<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;

describe('Format Compatibility', function (): void {
    test('YAML document is parsed as text with Blade constructs', function (): void {
        $template = <<<'YAML'
name: app
version: 1.2.3
items:
  - a
  - b
nested:
  key: value
  code: |
    line 1
    line 2 with {{ blade }} and @directive
YAML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(4)
            ->and($doc->render())->toBe($template)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3])->toBeInstanceOf(DirectiveNode::class);
    });

    test('TOML document is preserved as text', function (): void {
        $template = <<<'TOML'
[package]
name = "app"
version = "0.1.0"
[settings]
path = "C:/tmp"
TOML;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class);
    });

    test('INI file content is parsed as text', function (): void {
        $template = <<<'INI'
[section]
key=value
flag=true
path=C:\\temp\\dir
INI;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class);
    });

    test('CSV content is parsed as text', function (): void {
        $template = <<<'CSV'
name,age
Alice,30
Bob,28
CSV;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class);
    });

    test('GraphQL SDL is parsed as text', function (): void {
        $template = <<<'GQL'
# GraphQL schema
type Querying {
  hello(name: String!): String
}
GQL;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class);
    });

    test('SQL and Blade', function (): void {
        $template = <<<'SQL_WRAP'
        -- SQL snippet
        SELECT * FROM users WHERE name = '{{ $name }}' AND active = 1;
        SQL_WRAP;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class);
    });

    it('parses a pure Markdown document as text', function (): void {
        $template = <<<'MD'
# Heading 1

Some paragraph with _emphasis_, **strong**, and `inline code`.

- Item 1
- Item 2
  - Nested item

[link text](https://example.com) and ![alt](img.png)

> A blockquote line
>
> Another line

---

A table:

| col1 | col2 |
| ---- | ---- |
|  a   |  b   |

MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getContent())->toBe($template);
    });

    test('fenced code blocks with Blade/PHP-like content do not crash parser', function (): void {
        $template = <<<'MD'
# Example

```php
// Inside fenced code; these should not crash the parser
{{ $var }}
@foreach($items as $i)
@endforeach
<?php echo "hi"; ?>
```

Some text after.

~~~
Another fence with @endif and {!! $raw !!} and <<<EOT heredoc markers
EOT;
~~~
MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(12)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[4])->toBeInstanceOf(TextNode::class)
            ->and($nodes[5])->toBeInstanceOf(PhpTagNode::class)
            ->and($nodes[6])->toBeInstanceOf(TextNode::class)
            ->and($nodes[7])->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[8])->toBeInstanceOf(TextNode::class)
            ->and($nodes[9])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[10])->toBeInstanceOf(TextNode::class)
            ->and($nodes[11])->toBeInstanceOf(ElementNode::class);
    });

    test('mixed Markdown with inline HTML is tolerated and HTML is parsed', function (): void {
        $template = <<<'MD'
Paragraph before HTML.

<div class="note"><strong>Note:</strong> inline HTML inside markdown.</div>

Paragraph after HTML

MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(ElementNode::class)
            ->and((string) $nodes[1]->asElement()->tagName())->toBe('div')
            ->and($nodes[1]->isPaired())->toBeTrue()
            ->and($nodes[2])->toBeInstanceOf(TextNode::class);
    });

    it('Markdown does not crash with fuzzing', function (): void {
        $template = <<<'MD'
# Title

- a
- b
- c

```js
console.log("{ not blade }", 1 < 2 && 3 > 2);
```

> quote

| a | b |
|---|---|
| 1 | 2 |

Some trailing text with <span>inline HTML</span> and @if(false) @endif and {{ echo('x') }}.
MD;

        $len = strlen($template);
        for ($i = 0; $i <= $len; $i += 1) {
            $snippet = substr($template, 0, $i);

            expect($this->parse($snippet)->render())
                ->toBe($snippet);
        }
    });

    test('backtick and tilde fences with and without language tags', function (): void {
        $template = <<<'MD'
# Title

```php
// php fence
{{ $var }}
@foreach($items as $i)
@endforeach
<?php echo "hi"; ?>
```

~~~
// plain tilde fence
{!! $raw !!}
@if(false) @endif
~~~

```blade
{{-- even blade comment-like inside fence --}}
```

MD;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(13)
            ->and($doc->render())->toBe($template)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[4])->toBeInstanceOf(TextNode::class)
            ->and($nodes[5])->toBeInstanceOf(PhpTagNode::class)
            ->and($nodes[6])->toBeInstanceOf(TextNode::class)
            ->and($nodes[7])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[8])->toBeInstanceOf(TextNode::class)
            ->and($nodes[9])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[10])->toBeInstanceOf(TextNode::class)
            ->and($nodes[11])->toBeInstanceOf(BladeCommentNode::class)
            ->and($nodes[12])->toBeInstanceOf(TextNode::class);
    });

    test('variable-length fences with inner backticks', function (): void {
        $template = <<<'MD'
Some text before.

````
Here is a code block that contains ``` triple backticks inside.
Nothing should explode.
````

Text after.
MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getContent())->toBe($template);
    });

    it('multiple consecutive fences and boundary fences', function (): void {
        $template = <<<'MD'
```js
console.log('A');
```
```json
{"a":1}
```
~~~txt
A third fence
~~~
MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getContent())->toBe($template);
    });

    test('unterminated (open) fence does not crash', function (): void {
        $template = <<<'MD'
Intro
```python
def foo():
    return 1
// no closing fence on purpose
MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getContent())->toBe($template);
    });

    test('indented code blocks are preserved as text', function (): void {
        $template = <<<'MD'
Paragraph

    indented code line 1
    indented code line 2 with {{ value }} and <tag>that looks like HTML</tag>

Trailing
MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(5)
            ->and($doc->render())->toBe($template)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3])->toBeInstanceOf(ElementNode::class)
            ->and($nodes[4])->toBeInstanceOf(TextNode::class);
    });

    test('fences inside list items', function (): void {
        $template = <<<'MD'
- item 1
- item 2

  ```
  code in list
  @if(true) @endif
  ```
- item 3
MD;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($doc->render())->toBe($template)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class);
    });

    it('parses a pure JSON document as text and one embedded element', function (): void {
        $template = <<<'JSON'
{
  "name": "Alice",
  "age": 30,
  "tags": ["user", "admin"],
  "nested": { "x": 1, "y": 2 },
  "html": "<span>not html</span>",
  "weird": "braces { and } and brackets [ ] and parens ( ) inside strings"
}
JSON;

        $leadingText = <<<'TEXT'
{
  "name": "Alice",
  "age": 30,
  "tags": ["user", "admin"],
  "nested": { "x": 1, "y": 2 },
  "html": "
TEXT;

        $trailingText = <<<'TEXT'
",
  "weird": "braces { and } and brackets [ ] and parens ( ) inside strings"
}
TEXT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getContent())->toBe($leadingText)
            ->and($nodes[1])->toBeInstanceOf(ElementNode::class)
            ->and((string) $nodes[1]->asElement()->tagName())->toBe('span')
            ->and($nodes[1]->isPaired())->toBeTrue()
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getContent())->toBe($trailingText);
    });

    test('JSON inside application/json script is preserved', function (): void {
        $template = <<<'HTML'
<script type="application/json">
{
  "a": 1,
  "html": "<div>ok</div>",
  "arr": [1,2,3]
}
</script>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $expectedInnerContent = <<<'SCRIPT'

{
  "a": 1,
  "html": "<div>ok</div>",
  "arr": [1,2,3]
}

SCRIPT;

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class)
            ->and((string) $nodes[0]->asElement()->tagName())->toBe('script')
            ->and($nodes[0]->isPaired())->toBeTrue();

        $script = $nodes[0]->asElement();
        $children = $script->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->asText()->getContent())->toBe($expectedInnerContent);
    });

    test('Blade echo generating JSON inside script application/json', function (): void {
        $template = <<<'HTML'
<script type="application/json">{{ Js::from($array) }}</script>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class)
            ->and((string) $nodes[0]->asElement()->tagName())->toBe('script');

        /** @var ElementNode $script */
        $script = $nodes[0];
        $attributes = $script->getAttributes();

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->render())->toBe('type="application/json"');

        $children = $script->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(EchoNode::class)
            ->and($children[0]->asEcho()->content())->toBe(' Js::from($array) ');
    });

    test('dynamic JSON-like content with Blade echos is tolerated', function (): void {
        $template = <<<'TPL'
{
  "user": "{{ $name }}",
  "count": {!! $count !!},
  "note": "@endif inside some literal text"
}
TPL;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(7)
            ->and($nodes[0]->asText())->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getContent())->toContain('"user": "')
            ->and($nodes[1]->asEcho())->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->asEcho()->content())->toContain(' $name ')
            ->and($nodes[2]->asText())->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getContent())->toContain('"count":')
            ->and($nodes[3]->asEcho())->toBeInstanceOf(EchoNode::class)
            ->and($nodes[3]->asEcho()->content())->toContain(' $count ')
            ->and($nodes[4]->asText())->toBeInstanceOf(TextNode::class)
            ->and($nodes[4]->asText()->getContent())->toContain('"note": "')
            ->and($nodes[5]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[5]->asDirective()->nameText())->toBe('endif')
            ->and($nodes[6]->asText())->toBeInstanceOf(TextNode::class)
            ->and($nodes[6]->asText()->getContent())->toContain(' inside some literal text');

    });

    test('JSON fuzzing does not crash', function (): void {
        $json = <<<'JSON'
{
  "a": 1,
  "b": [2, 3, { "c": 4 }],
  "s": "string with <tags> and @blade and {{ echos }} and {!! raw !!}"
}
JSON;

        $len = strlen($json);

        for ($i = 0; $i <= $len; $i += 1) {
            $snippet = substr($json, 0, $i);

            expect($this->parse($snippet)->render())
                ->toBe($snippet);
        }
    });
});
