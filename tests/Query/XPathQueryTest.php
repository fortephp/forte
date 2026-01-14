<?php

declare(strict_types=1);

use Forte\Ast\Components\ComponentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\Document;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\GenericNode;
use Forte\Ast\Node;
use Forte\Components\ComponentManager;
use Forte\Extensions\ExtensionRegistry;
use Forte\Extensions\ForteExtension;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Directives\Directives;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\Extension\TreeExtension;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\ParserOptions;
use Forte\Parser\TreeBuilder;
use Forte\Querying\DomMapper;
use Forte\Querying\ExtensionDomMapper;

class XPathTestMarkerExtension implements ForteExtension, LexerExtension, TreeExtension
{
    private int $markerTokenType;

    private int $markerNodeKind;

    public function id(): string
    {
        return 'marker';
    }

    public function name(): string
    {
        return 'Marker Extension';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function conflicts(): array
    {
        return [];
    }

    public function getOptions(): array
    {
        return [];
    }

    // LexerExtension
    public function priority(): int
    {
        return 100;
    }

    public function triggerCharacters(): string
    {
        return ':';
    }

    public function registerTokenTypes(TokenTypeRegistry $registry): array
    {
        $this->markerTokenType = $registry->register('marker', 'Marker', 'Marker');

        return [$this->markerTokenType];
    }

    public function shouldActivate(LexerContext $ctx): bool
    {
        return $ctx->current() === ':' && $ctx->peek(1) === ':';
    }

    public function tokenize(LexerContext $ctx): bool
    {
        if (! $this->shouldActivate($ctx)) {
            return false;
        }

        $start = $ctx->position();
        $ctx->advance();
        $ctx->advance();

        $name = '';
        while ($ctx->current() !== null && (ctype_alnum($ctx->current()) || $ctx->current() === '_')) {
            $name .= $ctx->current();
            $ctx->advance();
        }

        if ($name === '') {
            return false;
        }

        if ($ctx->current() !== ':' || $ctx->peek(1) !== ':') {
            return false;
        }

        $ctx->advance();
        $ctx->advance();

        $ctx->emit($this->markerTokenType, $start, $ctx->position());

        return true;
    }

    public function registerNodeKinds(NodeKindRegistry $registry): void
    {
        $this->markerNodeKind = $registry->register(
            'marker',
            'Marker',
            XPathTestMarkerNode::class,
            'Marker',
            'marker'
        );
    }

    public function canHandle(TreeContext $ctx): bool
    {
        $token = $ctx->currentToken();

        return $token !== null && $token['type'] === $this->markerTokenType;
    }

    public function handle(TreeContext $ctx): int
    {
        $nodeIdx = $ctx->addNode($this->markerNodeKind, $ctx->position(), 1);
        $ctx->addChild($nodeIdx);

        return 1;
    }

    public function getXPathTestMarkerNodeKind(): int
    {
        return $this->markerNodeKind;
    }
}

class XPathTestMarkerNode extends GenericNode
{
    public function markerName(): string
    {
        $content = $this->getDocumentContent();

        if (preg_match('/^::(\w+)::$/', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }
}

function parseWithMarkers(string $template): Document
{
    test()->freshRegistries();
    DomMapper::clearExtensionMappers();

    $ext = new XPathTestMarkerExtension;
    $registry = app(ExtensionRegistry::class);
    $registry->register($ext);

    $directives = Directives::withDefaults();
    $lexer = new Lexer($template, $directives);
    $registry->configureLexer($lexer);
    $lexerResult = $lexer->tokenize();

    $builder = new TreeBuilder($lexerResult->tokens, $template, $directives);
    $registry->configureTreeBuilder($builder);
    $treeResult = $builder->build();

    return Document::fromParts(
        $treeResult['nodes'],
        $lexerResult->tokens,
        $template,
        $directives,
        new ComponentManager
    );
}

describe('XPath element queries', function (): void {
    it('finds elements by tag name', function (): void {
        $doc = $this->parse('<div><span>Hello</span></div>');

        $results = $doc->xpath('//span');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(ElementNode::class)
            ->and($results->first()->tagNameText())->toBe('span');
    });

    it('finds elements by class attribute', function (): void {
        $doc = $this->parse('<div class="container"><div class="inner">Content</div></div>');

        $results = $doc->xpath('//div[@class="container"]');

        expect($results)->toHaveCount(1)
            ->and($results->first()->getAttribute('class'))->toBe('container');
    });

    it('finds nested elements', function (): void {
        $doc = $this->parse('<div class="outer"><div class="inner"><span>Text</span></div></div>');

        $results = $doc->xpath('//div[@class="inner"]/span');

        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('span');
    });

    it('finds multiple elements', function (): void {
        $doc = $this->parse('<ul><li>One</li><li>Two</li><li>Three</li></ul>');

        $results = $doc->xpath('//li');

        expect($results)->toHaveCount(3);
    });

    it('finds elements with any attribute', function (): void {
        $doc = $this->parse('<input type="text" name="foo"><input name="bar">');

        $results = $doc->xpath('//input[@type]');

        expect($results)->toHaveCount(1);
    });
});

describe('XPath component queries', function (): void {
    it('finds blade components', function (): void {
        $doc = $this->parse('<div><x-button>Click</x-button></div>');

        $results = $doc->xpath('//x-button');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(ComponentNode::class);
    });

    it('finds components with attributes', function (): void {
        $doc = $this->parse('<x-alert type="warning">Alert!</x-alert>');

        $results = $doc->xpath('//x-alert[@type="warning"]');

        expect($results)->toHaveCount(1);
    });
});

describe('XPath directive queries', function (): void {
    it('finds block directives', function (): void {
        $doc = $this->parse('@if($show)<div>Content</div>@endif');

        $results = $doc->xpath('//forte:if');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(DirectiveBlockNode::class);
    });

    it('finds standalone directives', function (): void {
        $doc = $this->parse('@include("partial")');

        $results = $doc->xpath('//forte:include');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(DirectiveNode::class);
    });

    it('finds elements inside directives', function (): void {
        $doc = $this->parse('@if($show)<div class="conditional">Content</div>@endif');

        $results = $doc->xpath('//forte:if//div');

        expect($results)->toHaveCount(1)
            ->and($results->first()->getAttribute('class'))->toBe('conditional');
    });

    it('finds foreach directives', function (): void {
        $doc = $this->parse('@foreach($items as $item)<li>{{ $item }}</li>@endforeach');

        $results = $doc->xpath('//forte:foreach');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(DirectiveBlockNode::class);
    });
});

describe('XPath echo queries', function (): void {
    it('finds escaped getEchoes', function (): void {
        $doc = $this->parse('<div>{{ $name }}</div>');

        $results = $doc->xpath('//forte:echo');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(EchoNode::class);
    });

    it('finds raw getEchoes', function (): void {
        $doc = $this->parse('<div>{!! $html !!}</div>');

        $results = $doc->xpath('//forte:raw-echo');

        expect($results)->toHaveCount(1);
    });

    it('finds all getEchoes with union', function (): void {
        $doc = $this->parse('<div>{{ $name }}{!! $html !!}</div>');

        $results = $doc->xpath('//forte:echo | //forte:raw-echo');

        expect($results)->toHaveCount(2);
    });
});

describe('XPath wrapper methods', function (): void {
    it('first() returns first match', function (): void {
        $doc = $this->parse('<div><span>One</span><span>Two</span></div>');

        $result = $doc->xpath('//span')->first();

        expect($result)->toBeInstanceOf(ElementNode::class)
            ->and($result->asElement()->tagNameText())->toBe('span');
    });

    it('first() returns null for no match', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->xpath('//span')->first();

        expect($result)->toBeNull();
    });

    it('exists() returns true for match', function (): void {
        $doc = $this->parse('<div><span>Text</span></div>');

        expect($doc->xpath('//span')->exists())->toBeTrue()
            ->and($doc->xpath('//p')->exists())->toBeFalse();
    });

    it('count() returns count', function (): void {
        $doc = $this->parse('<ul><li>A</li><li>B</li><li>C</li></ul>');

        expect($doc->xpath('//li')->count())->toBe(3)
            ->and($doc->xpath('//span')->count())->toBe(0);
    });
});

describe('XPath complex queries', function (): void {
    it('finds elements with multiple conditions', function (): void {
        $doc = $this->parse('<input type="text" class="form-control" name="email">');

        $results = $doc->xpath('//input[@type="text"][@class="form-control"]');

        expect($results)->toHaveCount(1);
    });

    it('finds ancestor queries', function (): void {
        $doc = $this->parse('<div class="container"><ul><li>Item</li></ul></div>');

        $results = $doc->xpath('//li/ancestor::div[@class="container"]');

        expect($results)->toHaveCount(1);
    });

    it('finds sibling queries', function (): void {
        $doc = $this->parse('<div><span>First</span><span>Second</span></div>');

        $results = $doc->xpath('//span[1]/following-sibling::span');

        expect($results)->toHaveCount(1);
    });
});

describe('XPath with blade attributes', function (): void {
    it('finds elements with bound attributes', function (): void {
        $doc = $this->parse('<div :class="$classes">Content</div>');

        $results = $doc->xpath('//div[@forte:bind-class]');

        expect($results)->toHaveCount(1);
    });

    it('finds elements with wire model using attribute match', function (): void {
        $doc = $this->parse('<input wire:model="name">');

        $results = $doc->xpath('//input');

        expect($results)->toHaveCount(1)
            ->and($results->first()->getAttribute('wire:model'))->toBe('name');
    });

    it('finds elements with static attributes', function (): void {
        $doc = $this->parse('<div class="container" data-id="123">Content</div>');

        $results = $doc->xpath('//div[@class="container"][@data-id="123"]');

        expect($results)->toHaveCount(1);
    });
});

describe('XPath Livewire patterns', function (): void {
    it('finds foreach without wire:key on first child', function (): void {
        $blade = '
            @foreach($items as $item)
                <li>{{ $item }}</li>
            @endforeach
            @foreach($other as $o)
                <li wire:key="item-{{ $o->id }}">{{ $o->name }}</li>
            @endforeach
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:foreach[*[1][not(@*[name()="wire:key"])]]');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(DirectiveBlockNode::class);
    });

    it('finds foreach with wire:key on first child', function (): void {
        $blade = '
            @foreach($items as $item)
                <li>No key</li>
            @endforeach
            @foreach($other as $o)
                <li wire:key="item-{{ $o->id }}">Has key</li>
            @endforeach
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:foreach[*[1][@*[name()="wire:key"]]]');

        expect($results)->toHaveCount(1);
    });

    it('finds all foreach and checks first child for wire:key', function (): void {
        $blade = '
            @foreach($a as $item)<div>No key</div>@endforeach
            @foreach($b as $item)<div wire:key="k">Has key</div>@endforeach
            @foreach($c as $item)<span>Also no key</span>@endforeach
        ';
        $doc = $this->parse($blade);

        $allForeach = $doc->xpath('//forte:foreach');
        expect($allForeach)->toHaveCount(3);

        $missingKey = $doc->xpath('//forte:foreach[*[1][not(@*[name()="wire:key"])]]');
        expect($missingKey)->toHaveCount(2);

        $hasKey = $doc->xpath('//forte:foreach[*[1][@*[name()="wire:key"]]]');
        expect($hasKey)->toHaveCount(1);
    });

    it('finds elements with wire:click handlers', function (): void {
        $blade = '
            <button wire:click="save">Save</button>
            <button wire:click.prevent="delete">Delete</button>
            <button onclick="oldWay()">Old</button>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@*[starts-with(name(), "wire:click")]]');

        expect($results)->toHaveCount(2);
    });

    it('finds wire:model bindings', function (): void {
        $blade = '
            <input wire:model="name">
            <input wire:model.live="email">
            <input wire:model.blur="phone">
            <select wire:model="country"></select>
            <input type="text" name="old_style">
        ';
        $doc = $this->parse($blade);

        // Find all wire:model bindings (any modifier)
        $results = $doc->xpath('//*[@*[starts-with(name(), "wire:model")]]');

        expect($results)->toHaveCount(4);
    });

    it('finds forms without wire:submit', function (): void {
        $blade = '
            <form wire:submit="save"><input></form>
            <form action="/old"><input></form>
            <form wire:submit.prevent="update"><input></form>
        ';
        $doc = $this->parse($blade);

        // Forms without any wire:submit
        $results = $doc->xpath('//form[not(@*[starts-with(name(), "wire:submit")])]');

        expect($results)->toHaveCount(1);
    });
});

describe('XPath Alpine.js patterns', function (): void {
    it('finds x-data components', function (): void {
        $blade = '
            <div x-data="{ open: false }">
                <button @click="open = true">Open</button>
                <div x-show="open">Content</div>
            </div>
            <div>Not Alpine</div>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@x-data]');

        expect($results)->toHaveCount(1);
    });

    it('finds x-show without x-cloak', function (): void {
        $blade = '
            <div x-show="open">Might flash</div>
            <div x-show="visible" x-cloak>Properly hidden</div>
        ';
        $doc = $this->parse($blade);

        // Elements with x-show but missing x-cloak (potential FOUC)
        $results = $doc->xpath('//*[@x-show][not(@x-cloak)]');

        expect($results)->toHaveCount(1);
    });

    it('finds Alpine x-bind shorthand', function (): void {
        // Alpine x-bind:class is shorthand for :class in Alpine (but :class is Blade bind in this parser)
        // Use explicit x-bind for clarity
        $blade = '<div x-bind:class="{ active: isActive }">Content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@*[starts-with(name(), "x-bind")]]');

        expect($results)->toHaveCount(1);
    });
});

describe('XPath accessibility patterns', function (): void {
    it('finds images without alt attribute', function (): void {
        $blade = '
            <img src="logo.png" alt="Company Logo">
            <img src="hero.jpg">
            <img src="icon.svg" alt="">
        ';
        $doc = $this->parse($blade);

        // Images completely missing alt (alt="" is acceptable for decorative)
        $results = $doc->xpath('//img[not(@alt)]');

        expect($results)->toHaveCount(1);
    });

    it('finds form inputs without associated labels', function (): void {
        $blade = '
            <label for="email">Email</label>
            <input id="email" type="email">

            <input type="text" name="orphan" placeholder="No label">

            <label>
                <input type="checkbox"> Wrapped is OK
            </label>
        ';
        $doc = $this->parse($blade);

        // Inputs not wrapped in label and without id (simplified check)
        $results = $doc->xpath('//input[not(ancestor::label)][not(@id)]');

        expect($results)->toHaveCount(1);
    });

    it('finds buttons without accessible text', function (): void {
        $blade = '
            <button><i class="icon-save"></i></button>
            <button aria-label="Save"><i class="icon-save"></i></button>
            <button>Save Changes</button>
        ';
        $doc = $this->parse($blade);

        // Buttons that only contain elements (no text) and no aria-label
        // This finds buttons where there's no direct text content indication
        $results = $doc->xpath('//button[not(@aria-label)][not(text()[normalize-space()])]');

        expect($results)->toHaveCount(1);
    });

    it('finds links without href', function (): void {
        $blade = '
            <a href="/about">About</a>
            <a onclick="doSomething()">Fake link</a>
            <a href="#">Hash link</a>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//a[not(@href)]');

        expect($results)->toHaveCount(1);
    });
});

describe('XPath security patterns', function (): void {
    it('finds raw echo usage', function (): void {
        $blade = '
            <div>{{ $safe }}</div>
            <div>{!! $dangerous !!}</div>
            <script>{!! $json !!}</script>
        ';
        $doc = $this->parse($blade);

        // All raw (unescaped) echo statements - potential XSS
        $results = $doc->xpath('//forte:raw-echo');

        expect($results)->toHaveCount(2);
    });

    it('finds forms without CSRF token', function (): void {
        $blade = '
            <form method="POST">
                @csrf
                <input name="data">
            </form>
            <form method="POST">
                <input name="data">
            </form>
            <form method="GET">
                <input name="search">
            </form>
        ';
        $doc = $this->parse($blade);

        // POST forms without @csrf directive
        $results = $doc->xpath('//form[@method="POST"][not(.//forte:csrf)]');

        expect($results)->toHaveCount(1);
    });

    it('finds inline event handlers', function (): void {
        $blade = '
            <button onclick="doSomething()">Bad</button>
            <div onmouseover="track()">Tracking</div>
            <button wire:click="save">Good (Livewire)</button>
            <button @click="save">Good (Alpine)</button>
        ';
        $doc = $this->parse($blade);

        // Traditional inline handlers (not wire: or @)
        $results = $doc->xpath('//*[@onclick or @onmouseover or @onsubmit or @onchange or @onkeyup or @onkeydown]');

        expect($results)->toHaveCount(2);
    });
});

describe('XPath Blade directive patterns', function (): void {
    it('finds conditional blocks', function (): void {
        $blade = '
            @if($isAdmin)
                <div>Admin Panel</div>
            @endif
            @unless($banned)
                <div>Content</div>
            @endunless
            @isset($user)
                <div>User info</div>
            @endisset
        ';
        $doc = $this->parse($blade);

        $ifs = $doc->xpath('//forte:if');
        $unless = $doc->xpath('//forte:unless');
        $isset = $doc->xpath('//forte:isset');

        expect($ifs)->toHaveCount(1)
            ->and($unless)->toHaveCount(1)
            ->and($isset)->toHaveCount(1);
    });

    it('finds authorization directives', function (): void {
        $blade = '
            @auth
                <a href="/dashboard">Dashboard</a>
            @endauth
            @guest
                <a href="/login">Login</a>
            @endguest
            @can("edit", $post)
                <button>Edit</button>
            @endcan
        ';
        $doc = $this->parse($blade);

        $auth = $doc->xpath('//forte:auth');
        $guest = $doc->xpath('//forte:guest');
        $can = $doc->xpath('//forte:can');

        expect($auth)->toHaveCount(1)
            ->and($guest)->toHaveCount(1)
            ->and($can)->toHaveCount(1);
    });

    it('finds nested loops', function (): void {
        $blade = '
            @foreach($categories as $category)
                <h2>{{ $category->name }}</h2>
                @foreach($category->items as $item)
                    <li>{{ $item }}</li>
                @endforeach
            @endforeach
        ';
        $doc = $this->parse($blade);

        // Find foreach inside another foreach
        $results = $doc->xpath('//forte:foreach//forte:foreach');

        expect($results)->toHaveCount(1);
    });

    it('finds switch statements', function (): void {
        $blade = '
            @switch($status)
                @case("pending")
                    <span class="yellow">Pending</span>
                    @break
                @case("approved")
                    <span class="green">Approved</span>
                    @break
                @default
                    <span>Unknown</span>
            @endswitch
        ';
        $doc = $this->parse($blade);

        $switches = $doc->xpath('//forte:switch');
        $cases = $doc->xpath('//forte:switch//forte:case');
        $defaults = $doc->xpath('//forte:switch//forte:default');

        expect($switches)->toHaveCount(1)
            ->and($cases)->toHaveCount(2)
            ->and($defaults)->toHaveCount(1);
    });
});

describe('XPath component patterns', function (): void {
    it('finds all Blade components', function (): void {
        $blade = '
            <x-button>Click</x-button>
            <x-card title="Hello">Content</x-card>
            <x-alert type="warning" />
            <div>Regular div</div>
        ';
        $doc = $this->parse($blade);

        // Components are marked with data-forte-component
        $results = $doc->xpath('//*[@data-forte-component]');

        expect($results)->toHaveCount(3);
    });

    it('finds components by name pattern', function (): void {
        $blade = '
            <x-form.input name="email" />
            <x-form.select name="country" />
            <x-form.textarea name="bio" />
            <x-button>Submit</x-button>
        ';
        $doc = $this->parse($blade);

        // Find all x-form.* components
        $results = $doc->xpath('//*[starts-with(name(), "x-form.")]');

        expect($results)->toHaveCount(3);
    });

    it('finds components with specific props', function (): void {
        $blade = '
            <x-modal size="lg" title="Edit">Content</x-modal>
            <x-modal title="View">Content</x-modal>
            <x-modal size="sm">Small</x-modal>
        ';
        $doc = $this->parse($blade);

        // Components with both size and title
        $results = $doc->xpath('//x-modal[@size][@title]');

        expect($results)->toHaveCount(1);
    });

    it('finds components with bound props', function (): void {
        $blade = '
            <x-input :value="$name" />
            <x-input value="static" />
            <x-select :options="$countries" />
        ';
        $doc = $this->parse($blade);

        // Components with any bound attribute
        $results = $doc->xpath('//*[@data-forte-component][@*[starts-with(name(), "forte:bind-")]]');

        expect($results)->toHaveCount(2);
    });
});

describe('XPath layout patterns', function (): void {
    it('finds section definitions', function (): void {
        $blade = '
            @section("content")
                <div>Main content</div>
            @endsection
            @section("sidebar")
                <nav>Sidebar</nav>
            @endsection
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:section');

        expect($results)->toHaveCount(2);
    });

    it('finds yield placeholders', function (): void {
        $blade = '
            <main>
                @yield("content")
            </main>
            <aside>
                @yield("sidebar", "Default sidebar")
            </aside>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:yield');

        expect($results)->toHaveCount(2);
    });

    it('finds push and stack pairs', function (): void {
        $blade = '
            @push("scripts")
                <script src="app.js"></script>
            @endpush
            @push("scripts")
                <script src="extra.js"></script>
            @endpush
            @stack("scripts")
        ';
        $doc = $this->parse($blade);

        $pushes = $doc->xpath('//forte:push');
        $stacks = $doc->xpath('//forte:stack');

        expect($pushes)->toHaveCount(2)
            ->and($stacks)->toHaveCount(1);
    });

    it('finds include directives', function (): void {
        $blade = '
            @include("partials.header")
            <main>Content</main>
            @include("partials.footer")
        ';
        $doc = $this->parse($blade);

        $includes = $doc->xpath('//forte:include');

        expect($includes)->toHaveCount(2);
    });

    it('finds all include variants', function (): void {
        $blade = '
            @include("a")
            @includeIf("b")
            @includeWhen($x, "c")
            @includeUnless($y, "d")
            @includeFirst(["e", "f"])
        ';
        $doc = $this->parse($blade);

        $allIncludes = $doc->xpath('//*[starts-with(local-name(), "include")]');

        expect($allIncludes)->toHaveCount(5);
    });
});

describe('XPath dynamic tag names', function (): void {
    it('finds elements with dynamic tag names via marker', function (): void {
        $blade = '
            <div-{{ $name }}>Dynamic tag</div-{{ $name }}>
            <div>Static tag</div>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');

        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('div-{{ $name }}');
    });

    it('finds elements with dynamic attribute names via marker', function (): void {
        $blade = '
            <div class-{{ $dynamic }}="thing" data-id="{{ $id }}">Dynamic attr name</div>
            <div class="static">Static attr</div>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-attrs]');

        expect($results)->toHaveCount(1);
    });

    it('finds all dynamic elements (tag or attributes)', function (): void {
        $blade = '
            <div-{{ $type }}>Dynamic tag</div-{{ $type }}>
            <span attr-{{ $name }}="value">Dynamic attr</span>
            <p>Completely static</p>
        ';
        $doc = $this->parse($blade);

        $dynamic = $doc->xpath('//*[@data-forte-dynamic-tag or @data-forte-dynamic-attrs]');

        expect($dynamic)->toHaveCount(2);
    });

    it('finds dynamic tags with starts-with pattern', function (): void {
        $blade = '
            <card-{{ $type }}>Card A</card-{{ $type }}>
            <card-{{ $variant }}>Card B</card-{{ $variant }}>
            <button>Static</button>
        ';
        $doc = $this->parse($blade);

        // All "card-*" elements (regardless of dynamic suffix)
        $results = $doc->xpath('//*[starts-with(name(), "card-")]');

        expect($results)->toHaveCount(2);

        $dynamic = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($dynamic)->toHaveCount(2);
    });

    it('handles component with dynamic name', function (): void {
        $blade = '<x-dynamic-component :component="$componentName" />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//x-dynamic-component');

        expect($results)->toHaveCount(1);
        $component = $results->first();
        expect($component)->toBeInstanceOf(ComponentNode::class);

        $withBound = $doc->xpath('//x-dynamic-component[@forte:bind-component]');
        expect($withBound)->toHaveCount(1);
    });

    it('original tag name accessible from AST node', function (): void {
        $blade = '<widget-{{ $id }}>Content</widget-{{ $id }}>';
        $doc = $this->parse($blade);

        $element = $doc->xpath('//*[@data-forte-dynamic-tag]')->first();

        expect($element->tagNameText())->toBe('widget-{{ $id }}')
            ->and(str_contains((string) $element->tagNameText(), '{{'))->toBeTrue();
    });

    it('finds dynamic tags by specific expression content', function (): void {
        $blade = '
            <div-{{ $type }}>Type-based</div-{{ $type }}>
            <div-{{ $name }}>Name-based</div-{{ $name }}>
            <span-{{ $id }}>ID-based</span-{{ $id }}>
            <card-{{ $type }}>Also type</card-{{ $type }}>
        ';
        $doc = $this->parse($blade);

        $typeElements = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{ $type }}")]');
        expect($typeElements)->toHaveCount(2);

        $nameElements = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{ $name }}")]');
        expect($nameElements)->toHaveCount(1);

        $idElements = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{ $id }}")]');
        expect($idElements)->toHaveCount(1);

        $divTypes = $doc->xpath('//*[starts-with(@data-forte-dynamic-tag, "div-")][contains(@data-forte-dynamic-tag, "$type")]');
        expect($divTypes)->toHaveCount(1);
    });

    it('finds dynamic attributes by specific expression content', function (): void {
        $blade = '
            <div class-{{ $theme }}="dark">Theme attr</div>
            <div data-{{ $key }}="value">Key attr</div>
            <span id-{{ $theme }}="main">Also theme</span>
        ';
        $doc = $this->parse($blade);

        $themeAttrs = $doc->xpath('//*[contains(@data-forte-dynamic-attrs, "$theme")]');
        expect($themeAttrs)->toHaveCount(2);

        $keyAttrs = $doc->xpath('//*[contains(@data-forte-dynamic-attrs, "$key")]');
        expect($keyAttrs)->toHaveCount(1);
    });

    it('finds raw echo in dynamic tag names', function (): void {
        $blade = '
            <div-{{ $safe }}>Escaped</div-{{ $safe }}>
            <div-{!! $unsafe !!}>Raw/Unescaped</div-{!! $unsafe !!}>
        ';
        $doc = $this->parse($blade);

        $rawTags = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{!!")]');
        expect($rawTags)->toHaveCount(1);

        $safeTags = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{")][not(contains(@data-forte-dynamic-tag, "{!!"))]');
        expect($safeTags)->toHaveCount(1);
    });
});

describe('XPath all Blade constructs in elements', function (): void {
    test('escaped echo {{ }} in tag name', function (): void {
        $blade = '<div-{{ $name }}>Content</div-{{ $name }}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('div-{{ $name }}');
    });

    test('raw echo {!! !!} in tag name', function (): void {
        $blade = '<div-{!! $name !!}>Content</div-{!! $name !!}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('div-{!! $name !!}');

        $rawTags = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{!!")]');
        expect($rawTags)->toHaveCount(1);
    });

    test('triple echo {{{ }}} in tag name', function (): void {
        $blade = '<div-{{{ $name }}}>Content</div-{{{ $name }}}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('div-{{{ $name }}}');

        $tripleTags = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{{")]');
        expect($tripleTags)->toHaveCount(1);
    });

    test('escaped echo {{ }} in attribute name', function (): void {
        $blade = '<div class-{{ $suffix }}="value">Content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-attrs]');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrNames = array_map(fn ($attr) => $attr->nameText(), $element->getAttributes());
        expect($attrNames)->toContain('class-{{ $suffix }}');
    });

    test('raw echo {!! !!} in attribute name', function (): void {
        $blade = '<div data-{!! $key !!}="value">Content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-attrs]');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrNames = array_map(fn ($attr) => $attr->nameText(), $element->getAttributes());
        expect($attrNames)->toContain('data-{!! $key !!}');
    });

    test('standalone echo in attributes position', function (): void {
        $blade = '<div {{ $attributes }}>Content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->attributes();
        expect(count($attrs))->toBeGreaterThanOrEqual(1);
    });

    test('standalone raw echo in attributes position', function (): void {
        $blade = '<div {!! $rawAttrs !!}>Content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->attributes();
        expect(count($attrs))->toBeGreaterThanOrEqual(1);
    });

    test('multiple constructs in same tag name', function (): void {
        $blade = '<div-{{ $prefix }}-{{ $suffix }}>Content</div-{{ $prefix }}-{{ $suffix }}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('div-{{ $prefix }}-{{ $suffix }}');
    });

    test('mixed constructs in tag name', function (): void {
        $blade = '<div-{{ $safe }}-{!! $raw !!}>Content</div-{{ $safe }}-{!! $raw !!}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1);

        $originalTag = $results->first()->tagNameText();
        expect($originalTag)->toContain('{{ $safe }}')
            ->and($originalTag)->toContain('{!! $raw !!}');
    });

    test('multiple dynamic attribute names on same element', function (): void {
        $blade = '<div class-{{ $a }}="x" data-{{ $b }}="y" id-{{ $c }}="z">Content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-attrs]');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrNames = array_map(fn ($attr) => $attr->nameText(), $element->getAttributes());
        expect($attrNames)->toContain('class-{{ $a }}')
            ->and($attrNames)->toContain('data-{{ $b }}')
            ->and($attrNames)->toContain('id-{{ $c }}');
    });

    test('dynamic tag AND dynamic attributes together', function (): void {
        $blade = '<div-{{ $tag }} class-{{ $attr }}="value">Content</div-{{ $tag }}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag][@data-forte-dynamic-attrs]');
        expect($results)->toHaveCount(1);
    });

    test('echo in attribute value (bound attribute)', function (): void {
        $blade = '<div :class="$classes" :id="$id">Content</div>';
        $doc = $this->parse($blade);

        $boundClass = $doc->xpath('//div[@forte:bind-class]');
        $boundId = $doc->xpath('//div[@forte:bind-id]');

        expect($boundClass)->toHaveCount(1)
            ->and($boundId)->toHaveCount(1);

        $dynamicNames = $doc->xpath('//*[@data-forte-dynamic-attrs]');
        expect($dynamicNames)->toHaveCount(0);
    });

    test('component with dynamic tag name', function (): void {
        $blade = '<x-{{ $componentName }}>Content</x-{{ $componentName }}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag][@data-forte-component]');
        expect($results)->toHaveCount(1);
    });

    test('self-closing element with dynamic tag', function (): void {
        $blade = '<input-{{ $type }} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1);
    });

    it('can differentiate escaped vs raw vs triple echo in tags', function (): void {
        $blade = '
            <a-{{ $escaped }}>Escaped</a-{{ $escaped }}>
            <b-{!! $raw !!}>Raw</b-{!! $raw !!}>
            <c-{{{ $triple }}}>Triple</c-{{{ $triple }}}>
        ';
        $doc = $this->parse($blade);

        $allDynamic = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($allDynamic)->toHaveCount(3);

        $escapedOnly = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{")][not(contains(@data-forte-dynamic-tag, "{!!"))][not(contains(@data-forte-dynamic-tag, "{{{"))]');
        expect($escapedOnly)->toHaveCount(1);

        $rawOnly = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{!!")]');
        expect($rawOnly)->toHaveCount(1);

        $tripleOnly = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{{")]');
        expect($tripleOnly)->toHaveCount(1);
    });
});

describe('XPath PHP and special blocks', function (): void {
    it('finds PHP blocks (@php...@endphp)', function (): void {
        $blade = '
            @php
                $count = count($items);
            @endphp
            <div>{{ $count }} items</div>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:php');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(\Forte\Ast\PhpBlockNode::class);
    });

    it('finds inline PHP tags (<?php ?>)', function (): void {
        $blade = '
            <?php $name = "John"; ?>
            <p>Hello, {{ $name }}</p>
            <?= $greeting ?>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:php-tag');

        expect($results)->toHaveCount(2);
    });

    it('finds verbatim blocks', function (): void {
        $blade = '
            @verbatim
                <div>{{ This is NOT parsed as Blade }}</div>
            @endverbatim
            <div>{{ $thisParsed }}</div>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:verbatim');

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(\Forte\Ast\VerbatimNode::class);
    });

    it('finds Blade comments', function (): void {
        $blade = '
            {{-- This is a Blade comment --}}
            <div>Content</div>
            {{-- Another comment --}}
            <!-- HTML comment -->
        ';
        $doc = $this->parse($blade);

        $bladeComments = $doc->xpath('//forte:comment');
        expect($bladeComments)->toHaveCount(2);
    });

    it('finds all PHP-related nodes', function (): void {
        $blade = '
            @php $a = 1; @endphp
            <?php $b = 2; ?>
            <?= $c ?>
        ';
        $doc = $this->parse($blade);

        $allPhp = $doc->xpath('//forte:php | //forte:php-tag');

        expect($allPhp)->toHaveCount(3);
    });

    it('finds doctype declarations', function (): void {
        $blade = '<!DOCTYPE html><html><body>Content</body></html>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//forte:doctype');

        expect($results)->toHaveCount(1);
    });

    it('differentiates short PHP tags from full tags', function (): void {
        $blade = '
            <?php $full = "full"; ?>
            <?= $short ?>
        ';
        $doc = $this->parse($blade);

        $short = $doc->xpath('//forte:php-tag[@type="short"]');
        $full = $doc->xpath('//forte:php-tag[@type="full"]');

        expect($short)->toHaveCount(1)
            ->and($full)->toHaveCount(1);
    });
});

describe('XPath structural analysis', function (): void {
    it('finds deeply nested elements', function (): void {
        $blade = '
            <div>
                <div>
                    <div>
                        <div>
                            <span>Deep</span>
                        </div>
                    </div>
                </div>
            </div>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div//div//div//div//*');

        expect($results)->toHaveCount(1);
    });

    it('finds elements by position', function (): void {
        $blade = '
            <ul>
                <li>First</li>
                <li>Second</li>
                <li>Third</li>
                <li>Fourth</li>
            </ul>
        ';
        $doc = $this->parse($blade);

        $first = $doc->xpath('//ul/li[1]');
        $last = $doc->xpath('//ul/li[last()]');
        $middle = $doc->xpath('//ul/li[position() > 1][position() < last()]');

        expect($first)->toHaveCount(1)
            ->and($last)->toHaveCount(1)
            ->and($middle)->toHaveCount(2);
    });

    it('finds empty elements', function (): void {
        $blade = '
            <div></div>
            <div>   </div>
            <div>Content</div>
            <span></span>
        ';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[not(node())]');

        expect($results)->toHaveCount(2);
    });

    it('counts elements by type', function (): void {
        $blade = '
            <div>
                <input type="text">
                <input type="email">
                <input type="password">
                <button>Submit</button>
            </div>
        ';
        $doc = $this->parse($blade);

        $inputCount = $doc->xpath('//input')->count();
        $buttonCount = $doc->xpath('//button')->count();
        $totalFormElements = $doc->xpath('//input | //button | //select | //textarea')->count();

        expect($inputCount)->toBe(3)
            ->and($buttonCount)->toBe(1)
            ->and($totalFormElements)->toBe(4);
    });
});

describe('XPath fully dynamic tag names', function (): void {
    it('finds fully dynamic element tag (echo-only name)', function (): void {
        $blade = '<{{ $element }} class="test">content</{{ $element }}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('{{ $element }}');
    });

    it('finds fully dynamic raw echo tag', function (): void {
        $blade = '<{!! $tag !!}>content</{!! $tag !!}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('{!! $tag !!}');

        $rawTags = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{!!")]');
        expect($rawTags)->toHaveCount(1);
    });

    it('finds fully dynamic triple echo tag', function (): void {
        $blade = '<{{{ $escaped }}}></{{{ $escaped }}}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('{{{ $escaped }}}');
    });

    it('finds nested dynamic elements', function (): void {
        $blade = '<{{ $outer }}><{{ $inner }}>text</{{ $inner }}></{{ $outer }}>';
        $doc = $this->parse($blade);

        $allDynamic = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($allDynamic)->toHaveCount(2);

        $nested = $doc->xpath('//*[@data-forte-dynamic-tag]//*[@data-forte-dynamic-tag]');
        expect($nested)->toHaveCount(1)
            ->and($nested->first()->tagNameText())->toBe('{{ $inner }}');
    });

    it('finds self-closing fully dynamic tag', function (): void {
        $blade = '<{{ $component }} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->isSelfClosing())->toBeTrue();
    });

    it('finds dynamic tag with multiple expressions', function (): void {
        $blade = '<{{ $prefix }}-{{ $suffix }}>content</{{ $prefix }}-{{ $suffix }}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('{{ $prefix }}-{{ $suffix }}');
    });

    it('finds mixed static and dynamic parts', closure: function (): void {
        $blade = '<div-{{ $id }}-wrapper>content</div-{{ $id }}-wrapper>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('div-{{ $id }}-wrapper');

        $divPrefix = $doc->xpath('//*[starts-with(name(), "div-")]');
        expect($divPrefix)->toHaveCount(1);
    });
});

describe('XPath PHP tags in element names', function (): void {
    it('finds element with PHP tag in name', function (): void {
        $blade = '<element-<?php echo "hi"; ?> class="mt-4"></element-<?php echo "hi"; ?>>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toContain('<?php');
    });

    it('finds element with short PHP echo tag in name', function (): void {
        $blade = '<el-<?= "x" ?> id="a"></el-<?= "x" ?>>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1)
            ->and($results->first()->tagNameText())->toBe('el-<?= "x" ?>');
    });

    it('distinguishes PHP tags from Blade getEchoes in tag names', function (): void {
        $blade = '
            <div-<?php echo $x; ?>>PHP tag</div-<?php echo $x; ?>>
            <div-{{ $y }}>Blade echo</div-{{ $y }}>
        ';
        $doc = $this->parse($blade);

        $allDynamic = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($allDynamic)->toHaveCount(2);

        $phpTags = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "<?php")]');
        expect($phpTags)->toHaveCount(1);

        $bladeEchoes = $doc->xpath('//*[contains(@data-forte-dynamic-tag, "{{")]');
        expect($bladeEchoes)->toHaveCount(1);
    });
});

describe('XPath generic type arguments', function (): void {
    it('finds component with generic type arguments', function (): void {
        $blade = '<Component<{ id: number, items: Array<Foo<Bar>> }>></Component>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Component');
        expect($results)->toHaveCount(1);

        $component = $results->first();
        expect($component->genericTypeArguments())->toBe('<{ id: number, items: Array<Foo<Bar>> }>');
    });

    it('finds dynamic element with generic type arguments', function (): void {
        $blade = '<Comp-{{ $x }}<T> id="a"></Comp-{{ $x }}>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[@data-forte-dynamic-tag]');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        expect($element->tagNameText())->toBe('Comp-{{ $x }}')
            ->and($element->genericTypeArguments())->toBe('<T>');
    });

    it('finds multiple components with generics including multi-letter types', function (): void {
        // Multi-letter lowercase type names now work for PascalCase components
        $blade = '
            <List<Item>>items</List>
            <Map<string, number>>map</Map>
            <Component>no generics</Component>
        ';
        $doc = $this->parse($blade);

        $allComponents = $doc->xpath('//List | //Map | //Component');
        expect($allComponents)->toHaveCount(3);

        $withGenerics = $allComponents->get()->filter(fn ($c) => $c->genericTypeArguments() !== null)->count();
        expect($withGenerics)->toBe(2);
    });

    it('finds component with multi-letter lowercase type names', function (): void {
        $blade = '<Map<string, number>>content</Map>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Map');
        expect($results)->toHaveCount(1);

        $map = $results->first();
        expect($map->genericTypeArguments())->toBe('<string, number>');
    });

    it('finds component with square bracket tuple types', function (): void {
        $blade = '<Container<[A, B, C]>>content</Container>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Container');
        expect($results)->toHaveCount(1);

        $container = $results->first();
        expect($container->genericTypeArguments())->toBe('<[A, B, C]>');
    });

    it('finds self-closing component with generics', function (): void {
        $blade = '<Input<string> />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Input');
        expect($results)->toHaveCount(1);

        $input = $results->first();
        expect($input->genericTypeArguments())->toBe('<string>')
            ->and($input->isSelfClosing())->toBeTrue();
    });

    it('finds deeply nested generic types', function (): void {
        $blade = '<Outer<Inner<Deep<Core<T>>>>>content</Outer>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Outer');
        expect($results)->toHaveCount(1);

        $outer = $results->first();
        expect($outer->genericTypeArguments())->toBe('<Inner<Deep<Core<T>>>>');
    });

    it('finds component with union types in generics', function (): void {
        $blade = '<Component<A | B | C>>content</Component>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Component');
        expect($results)->toHaveCount(1);

        $component = $results->first();
        expect($component->genericTypeArguments())->toBe('<A | B | C>');
    });

    it('finds component with intersection types in generics', function (): void {
        $blade = '<Component<A & B>>content</Component>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Component');
        expect($results)->toHaveCount(1);

        $component = $results->first();
        expect($component->genericTypeArguments())->toBe('<A & B>');
    });

    it('handles generics with TypeScript utility types', function (): void {
        $blade = '<Form<Partial<User>>>content</Form>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Form');
        expect($results)->toHaveCount(1);

        $form = $results->first();
        expect($form->genericTypeArguments())->toBe('<Partial<User>>');
    });

    test('lowercase HTML elements do not capture generics with lowercase types', function (): void {
        $blade = '<div<span>text</span>';
        $doc = $this->parse($blade);

        $divs = $doc->xpath('//div');
        $spans = $doc->xpath('//span');

        expect($divs)->toHaveCount(1)
            ->and($spans)->toHaveCount(1)
            ->and($divs->first()->genericTypeArguments())->toBeNull();
    });

    test('lowercase HTML elements can have uppercase-only generics', function (): void {
        $blade = '<div<T>>content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $div = $results->first();
        expect($div->genericTypeArguments())->toBe('<T>');
    });

    test('x-prefixed components are treated as components', function (): void {
        $blade = '<x-button<T>>click</x-button>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//x-button');
        expect($results)->toHaveCount(1);

        $button = $results->first();
        expect($button->genericTypeArguments())->toBe('<T>');
    });

    test('namespaced components support generics', function (): void {
        $blade = '<Admin.Table<User>>rows</Admin.Table>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//*[starts-with(name(), "Admin")]');
        expect($results)->toHaveCount(1);

        $table = $results->first();
        expect($table->tagNameText())->toBe('Admin.Table')
            ->and($table->genericTypeArguments())->toBe('<User>');
    });

    it('handles generics with string literal types', function (): void {
        $blade = '<Select<"red" | "blue" | "green">>options</Select>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Select');
        expect($results)->toHaveCount(1);

        $select = $results->first();
        expect($select->genericTypeArguments())->toBe('<"red" | "blue" | "green">');
    });

    it('handles generics with arrow function types', function (): void {
        $blade = '<Handler<(e: Event) => void>>handler</Handler>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Handler');
        expect($results)->toHaveCount(1);

        $handler = $results->first();
        expect($handler->genericTypeArguments())->toBe('<(e: Event) => void>');
    });

    it('handles generics with extends constraint', function (): void {
        $blade = '<Generic<T extends BaseModel>>content</Generic>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Generic');
        expect($results)->toHaveCount(1);

        $generic = $results->first();
        expect($generic->genericTypeArguments())->toBe('<T extends BaseModel>');
    });

    it('handles whitespace inside generics', function (): void {
        $blade = '<Component< T , U , V >>content</Component>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Component');
        expect($results)->toHaveCount(1);

        $component = $results->first();
        expect($component->genericTypeArguments())->toBe('< T , U , V >');
    });

    it('preserves attributes alongside complex generics', function (): void {
        $blade = '<List<string, number> class="items" :data="$items" {enabled}>content</List>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//List');
        expect($results)->toHaveCount(1);

        $list = $results->first();
        expect($list->genericTypeArguments())->toBe('<string, number>');

        $attrs = $list->getAttributes();
        expect($attrs)->toHaveCount(3);
    });

    test('camelCase tag names do not get lowercase generics', function (): void {
        $blade = '<myComponent<span>text</span>';
        $doc = $this->parse($blade);

        $spans = $doc->xpath('//span');
        expect($spans)->toHaveCount(1);
    });

    it('handles numeric literal types in generics', function (): void {
        $blade = '<Rating<1 | 2 | 3 | 4 | 5>>stars</Rating>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Rating');
        expect($results)->toHaveCount(1);

        $rating = $results->first();
        expect($rating->genericTypeArguments())->toBe('<1 | 2 | 3 | 4 | 5>');
    });

    it('handles conditional types in generics', function (): void {
        $blade = '<Conditional<T extends string ? StringType : OtherType>>content</Conditional>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Conditional');
        expect($results)->toHaveCount(1);

        $cond = $results->first();
        expect($cond->genericTypeArguments())->toBe('<T extends string ? StringType : OtherType>');
    });
});

describe('XPath standalone attributes (echo spreading)', function (): void {
    it('finds element with standalone echo attribute', function (): void {
        $blade = '<div {{ $attrs }}></div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first()->asElement();
        $hasStandalone = false;
        foreach ($element->attributes() as $attr) {
            if ($attr->isBladeConstruct()) {
                $hasStandalone = true;
                break;
            }
        }
        expect($hasStandalone)->toBeTrue();
    });

    it('finds element with standalone raw echo attribute', function (): void {
        $blade = '<div {!! $attrs !!}></div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $standaloneAttr = null;
        foreach ($element->attributes() as $attr) {
            if ($attr->isBladeConstruct()) {
                $standaloneAttr = $attr;
                break;
            }
        }
        expect($standaloneAttr)->not->toBeNull();

        $standaloneNode = $standaloneAttr->getBladeConstruct();
        expect($standaloneNode->echoType())->toBe('raw');
    });

    it('finds element with standalone triple echo attribute', function (): void {
        $blade = '<div {{{ $attrs }}}></div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $standaloneAttr = null;
        foreach ($element->attributes() as $attr) {
            if ($attr->isBladeConstruct()) {
                $standaloneAttr = $attr;
                break;
            }
        }
        expect($standaloneAttr)->not->toBeNull()
            ->and($standaloneAttr->getBladeConstruct()->echoType())->toBe('triple');
    });

    it('finds element with multiple standalone getEchoes', function (): void {
        $blade = '<div {{ $a }} {{ $b }}></div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $standaloneCount = 0;
        foreach ($element->attributes() as $attr) {
            if ($attr->isBladeConstruct()) {
                $standaloneCount++;
            }
        }
        expect($standaloneCount)->toBe(2);
    });

    it('finds element with mixed traditional and echo attributes', function (): void {
        $blade = '<div class="static" {{ $attrs }}></div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div[@class="static"]');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $staticCount = 0;
        $standaloneCount = 0;
        foreach ($element->attributes() as $attr) {
            if ($attr->isBladeConstruct()) {
                $standaloneCount++;
            } else {
                $staticCount++;
            }
        }
        expect($staticCount)->toBe(1)
            ->and($standaloneCount)->toBe(1);
    });

    it('finds element with complex echo expression in attribute', function (): void {
        $blade = '<ul {{ $attributes->merge([\'class\' => \'bg-\'.$color.\'-200\']) }}></ul>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//ul');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $standaloneAttr = null;
        foreach ($element->attributes() as $attr) {
            if ($attr->isBladeConstruct()) {
                $standaloneAttr = $attr;
                break;
            }
        }
        expect($standaloneAttr)->not->toBeNull();

        $content = $standaloneAttr->getBladeConstruct()->content();
        expect($content)->toContain('$attributes->merge');
    });
});

describe('XPath JSX-style expression attributes', function (): void {
    it('finds element with brace-wrapped expression attribute', function (): void {
        $blade = '<if {count > 0}>true</if>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//if');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs)->toHaveCount(1)
            ->and($attrs[0]->isExpression())->toBeTrue()
            ->and($attrs[0]->render())->toBe('{count > 0}');
    });

    it('finds element with comparison operator expression', function (): void {
        $blade = '<div {count >= 10}>content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs[0]->isExpression())->toBeTrue();
    });

    it('finds element with logical operator expression', function (): void {
        $blade = '<div {enabled && visible || fallback}>content</div>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs[0]->isExpression())->toBeTrue()
            ->and($attrs[0]->render())->toBe('{enabled && visible || fallback}');
    });

    it('finds element with JSX spread syntax', function (): void {
        $blade = '<Component {...props} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Component');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs[0]->isExpression())->toBeTrue()
            ->and($attrs[0]->render())->toBe('{...props}');
    });

    it('finds element with arrow function attribute value', function (): void {
        $blade = '<div model={() => { count++ }} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs[0]->nameText())->toBe('model')
            ->and($attrs[0]->valueText())->toBe('{() => { count++ }}');
    });

    it('finds element with multiple complex JSX attributes', function (): void {
        $blade = '<Component data=({count: 5}) onClick={() => alert("hi")} {enabled} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//Component');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs)->toHaveCount(3)
            ->and($attrs[0]->nameText())->toBe('data')
            ->and($attrs[0]->valueText())->toBe('({count: 5})')
            ->and($attrs[1]->nameText())->toBe('onClick')
            ->and($attrs[1]->valueText())->toBe('{() => alert("hi")}')
            ->and($attrs[2]->isExpression())->toBeTrue();
    });

    it('finds element with nested brace structures', function (): void {
        $blade = '<div data={a: {b: {c: 1}}} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs[0]->valueText())->toBe('{a: {b: {c: 1}}}');
    });

    it('finds element with ternary in attribute value', function (): void {
        $blade = '<div className={isActive ? "active" : "inactive"} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs[0]->valueText())->toBe('{isActive ? "active" : "inactive"}');
    });

    it('finds element with template literal attribute', function (): void {
        $blade = '<div data={`template ${var}`} />';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//div');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        $attrs = $element->getAttributes();
        expect($attrs[0]->valueText())->toBe('{`template ${var}`}');
    });
});

describe('XPath XML namespace attributes', function (): void {
    it('finds element with xmlns attribute', function (): void {
        $blade = '<feed xmlns="http://www.w3.org/2005/Atom">test</feed>';
        $doc = $this->parse($blade);

        $results = $doc->xpath('//feed');
        expect($results)->toHaveCount(1);

        $element = $results->first();
        expect($element->tagNameText())->toBe('feed');

        $attrs = $element->getAttributes();
        expect($attrs[0]->nameText())->toBe('xmlns')
            ->and($attrs[0]->valueText())->toBe('http://www.w3.org/2005/Atom');
    });

    it('finds element with xmlns prefixed attribute', function (): void {
        $blade = '<svg xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#icon"/></svg>';
        $doc = $this->parse($blade);

        $svg = $doc->xpath('//svg');
        expect($svg)->toHaveCount(1);

        $use = $doc->xpath('//use');
        expect($use)->toHaveCount(1);
    });
});

describe('XPath attribute type queries', function (): void {
    it('finds all elements with any standalone attributes', function (): void {
        $blade = '
            <div {{ $a }}>with echo</div>
            <div {!! $b !!}>with raw</div>
            <div class="static">no standalone</div>
        ';
        $doc = $this->parse($blade);

        $allDivs = $doc->xpath('//div');
        expect($allDivs)->toHaveCount(3);

        $withStandalone = $allDivs->get()->filter(
            fn ($div) => $div->attributes()->hasBladeConstruct()
        )->count();
        expect($withStandalone)->toBe(2);
    });

    it('finds all elements with expression attributes', function (): void {
        $blade = '
            <div {count > 0}>expression</div>
            <div class="static">no expression</div>
            <Component {...props} />
        ';
        $doc = $this->parse($blade);

        $allElements = $doc->xpath('//div | //Component');
        expect($allElements)->toHaveCount(3);

        // Count those with expression attrs
        $withExpression = $allElements->get()->filter(
            fn ($el) => $el->attributes()->hasExpression()
        )->count();
        expect($withExpression)->toBe(2);
    });

    it('finds all bound attributes across document', function (): void {
        $blade = '
            <div :class="$a" :id="$b">bound</div>
            <input :value="$c" />
            <span class="static">not bound</span>
        ';
        $doc = $this->parse($blade);

        $withBound = $doc->xpath('//*[@*[starts-with(name(), "forte:bind-")]]');
        expect($withBound)->toHaveCount(2);
    });

    it('finds all escaped attributes across document', function (): void {
        $blade = '
            <div ::class="$a">escaped</div>
            <input ::value="$b" />
            <span class="static">not escaped</span>
        ';
        $doc = $this->parse($blade);

        $withEscaped = $doc->xpath('//*[@*[starts-with(name(), "forte:escape-")]]');
        expect($withEscaped)->toHaveCount(2);
    });
});

describe('XPath extension nodes', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
        DomMapper::clearExtensionMappers();
    });

    it('finds extension nodes via custom DOM element name', function (): void {
        $doc = parseWithMarkers('Hello ::world:: there');

        $results = $doc->xpath('//forte:marker');
        expect($results)->toHaveCount(1);

        $node = $results->first();

        expect($node)->toBeInstanceOf(XPathTestMarkerNode::class)
            ->and($node->getDocumentContent())->toBe('::world::')
            ->and($node->markerName())->toBe('world');
    });

    test('extension nodes can be found with intuitive instanceof checks', function (): void {
        $doc = parseWithMarkers('<div>::alpha:: text ::beta::</div>');

        $markers = $doc->findAll(fn ($n) => $n instanceof XPathTestMarkerNode);
        expect($markers)->toHaveCount(2)
            ->and($markers[0])->toBeInstanceOf(XPathTestMarkerNode::class)
            ->and($markers[0]->markerName())->toBe('alpha')
            ->and($markers[1])->toBeInstanceOf(XPathTestMarkerNode::class)
            ->and($markers[1]->markerName())->toBe('beta');

        $first = $doc->find(fn ($n) => $n instanceof XPathTestMarkerNode);
        expect($first)->toBeInstanceOf(XPathTestMarkerNode::class)
            ->and($first->markerName())->toBe('alpha');
    });

    it('finds multiple extension nodes with clean XPath', function (): void {
        $doc = parseWithMarkers('::first:: middle ::second::');

        $results = $doc->xpath('//forte:marker');
        expect($results)->toHaveCount(2)
            ->and($results->first()->markerName())->toBe('first');
    });

    test('extension nodes alongside HTML elements', function (): void {
        $doc = parseWithMarkers('<div class="container">::marker:: content</div>');

        $divs = $doc->xpath('//div[@class="container"]');
        expect($divs)->toHaveCount(1);

        $markers = $doc->xpath('//forte:marker');
        expect($markers)->toHaveCount(1);
    });

    test('extension nodes with Blade directives', function (): void {
        $doc = parseWithMarkers('@if($show)::marker::@endif');

        $ifs = $doc->xpath('//forte:if');
        expect($ifs)->toHaveCount(1);

        $markers = $doc->xpath('//forte:marker');
        expect($markers)->toHaveCount(1);
    });

    test('multiple different extension markers', function (): void {
        $doc = parseWithMarkers('::start:: content ::middle:: more ::end::');

        $markers = $doc->xpath('//forte:marker');
        expect($markers)->toHaveCount(3);

        $names = $markers->get()->map(fn ($m) => $m->markerName())->all();
        expect($names)->toBe(['start', 'middle', 'end']);
    });

    test('extension nodes inside nested HTML', function (): void {
        $doc = parseWithMarkers('<div><span>::nested::</span></div>');

        $markers = $doc->xpath('//div/span/forte:marker');
        expect($markers)->toHaveCount(1)
            ->and($markers->first()->markerName())->toBe('nested');
    });

    it('counts extension nodes correctly', function (): void {
        $doc = parseWithMarkers('::a:: ::b:: ::c:: ::d:: ::e::');

        $count = $doc->xpath('//forte:marker')->count();
        expect($count)->toBe(5);
    });

    it('checks extension node existence', function (): void {
        $docWith = parseWithMarkers('Hello ::marker:: world');
        $docWithout = parseWithMarkers('Hello world');

        expect($docWith->xpath('//forte:marker')->exists())->toBeTrue()
            ->and($docWithout->xpath('//forte:marker')->exists())->toBeFalse();
    });

    test('extension nodes render back to original source', function (): void {
        $template = '<div>::test_marker::</div>';
        $doc = parseWithMarkers($template);

        expect($doc->render())->toBe($template);
    });

    test('extensions without registered DOM name fallback to forte:extension', function (): void {
        $this->freshRegistries();
        DomMapper::clearExtensionMappers();

        $kindId = app(NodeKindRegistry::class)->register(
            'test',
            'NoName',
            null,
            'NoName'
        );

        Document::parse('<div>test</div>', ParserOptions::defaults()->withAllDirectives());

        expect(app(NodeKindRegistry::class)->getDomElement($kindId))->toBeNull()
            ->and(app(NodeKindRegistry::class)->getDomElement(
                app(NodeKindRegistry::class)->register('test2', 'WithName', null, 'WithName', 'custom-elem')
            ))->toBe('custom-elem');

    });
});

describe('XPath custom ExtensionDomMapper', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
        DomMapper::clearExtensionMappers();
    });

    test('custom mapper overrides registered DOM element name', function (): void {
        $doc = parseWithMarkers('::custom::');

        $defaultResults = $doc->xpath('//forte:marker');
        expect($defaultResults)->toHaveCount(1);
        $kind = $defaultResults->first()->kind();

        $doc2 = parseWithMarkers('::custom::');

        DomMapper::registerExtensionMapper($kind, new class implements ExtensionDomMapper
        {
            public function toDOM(Node $node, DOMDocument $dom, DomMapper $mapper): DOMElement
            {
                $element = $dom->createElementNS(DomMapper::FORTE_NAMESPACE, 'forte:custom-marker');
                $element->setAttribute('marker-name', $node->getDocumentContent());
                $element->setAttribute('data-forte-idx', (string) $node->index());

                return $element;
            }
        });

        $customResults = $doc2->xpath('//forte:custom-marker');
        expect($customResults)->toHaveCount(1);

        $defaultNameResults = $doc2->xpath('//forte:marker');
        expect($defaultNameResults)->toHaveCount(0);
    });

    test('custom mapper can add attributes', function (): void {
        $doc1 = parseWithMarkers('::test::');
        $defaultResults = $doc1->xpath('//forte:marker');
        $kind = $defaultResults->first()->kind();

        $doc2 = parseWithMarkers('::attribute_test::');

        DomMapper::registerExtensionMapper($kind, new class implements ExtensionDomMapper
        {
            public function toDOM(Node $node, DOMDocument $dom, DomMapper $mapper): DOMElement
            {
                $element = $dom->createElementNS(DomMapper::FORTE_NAMESPACE, 'forte:marker');
                $content = $node->getDocumentContent();
                if (preg_match('/^::(\w+)::$/', $content, $matches)) {
                    $element->setAttribute('name', $matches[1]);
                }
                $element->setAttribute('type', 'custom-marker');
                $element->setAttribute('source', $content);
                $element->setAttribute('data-forte-idx', (string) $node->index());

                return $element;
            }
        });

        $byType = $doc2->xpath('//forte:marker[@type="custom-marker"]');
        expect($byType)->toHaveCount(1);

        $byName = $doc2->xpath('//forte:marker[@name="attribute_test"]');
        expect($byName)->toHaveCount(1);
    });

    test('custom mapper works with multiple nodes', function (): void {
        $doc1 = parseWithMarkers('::test::');
        $defaultResults = $doc1->xpath('//forte:marker');
        $kind = $defaultResults->first()->kind();

        $doc2 = parseWithMarkers('::alpha:: ::beta:: ::gamma::');

        DomMapper::registerExtensionMapper($kind, new class implements ExtensionDomMapper
        {
            public function toDOM(Node $node, DOMDocument $dom, DomMapper $mapper): DOMElement
            {
                $element = $dom->createElementNS(DomMapper::FORTE_NAMESPACE, 'forte:marker');
                $content = $node->getDocumentContent();
                if (preg_match('/^::(\w+)::$/', $content, $matches)) {
                    $element->setAttribute('id', $matches[1]);
                }
                $element->setAttribute('data-forte-idx', (string) $node->index());

                return $element;
            }
        });

        $markers = $doc2->xpath('//forte:marker');
        expect($markers)->toHaveCount(3);

        $ids = $markers->get()->map(function ($m) {
            $content = $m->getDocumentContent();
            preg_match('/^::(\w+)::$/', $content, $matches);

            return $matches[1] ?? null;
        })->filter()->all();
        expect($ids)->toBe(['alpha', 'beta', 'gamma']);
    });
});

describe('XPath extension nodes with complex documents', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
        DomMapper::clearExtensionMappers();
    });

    test('extension nodes in realistic template', function (): void {
        $template = '
            <div class="header">
                ::header_marker::
                <h1>{{ $title }}</h1>
            </div>
            @foreach($items as $item)
                <div class="item">
                    ::item_marker::
                    {{ $item->name }}
                </div>
            @endforeach
            <footer>::footer_marker::</footer>
        ';
        $doc = parseWithMarkers($template);

        $allMarkers = $doc->xpath('//forte:marker');
        expect($allMarkers)->toHaveCount(3);

        $headerMarker = $doc->xpath('//div[@class="header"]/forte:marker');
        expect($headerMarker)->toHaveCount(1)
            ->and($headerMarker->first()->markerName())->toBe('header_marker');

        $footerMarker = $doc->xpath('//footer/forte:marker');
        expect($footerMarker)->toHaveCount(1)
            ->and($footerMarker->first()->markerName())->toBe('footer_marker');

        $echos = $doc->xpath('//forte:echo');
        expect($echos)->toHaveCount(2);
    });

    test('extension nodes in component context', function (): void {
        $template = '<x-card title="Test">::card_marker::<span>Content</span></x-card>';
        $doc = parseWithMarkers($template);

        $components = $doc->xpath('//x-card');
        expect($components)->toHaveCount(1);

        $markers = $doc->xpath('//x-card/forte:marker');
        expect($markers)->toHaveCount(1);
    });

    test('extension nodes preserve document structure', function (): void {
        $template = '<ul><li>::item1::</li><li>::item2::</li><li>::item3::</li></ul>';
        $doc = parseWithMarkers($template);

        $lis = $doc->xpath('//ul/li');
        expect($lis)->toHaveCount(3);

        $expectedMarkers = ['item1', 'item2', 'item3'];
        foreach ($lis as $i => $li) {
            $markers = $doc->xpath('//ul/li['.($i + 1).']/forte:marker');
            expect($markers)->toHaveCount(1)
                ->and($markers->first()->markerName())->toBe($expectedMarkers[$i]);
        }
    });
});

describe('XPath component type queries', function (): void {
    it('finds Blade components by type', function (): void {
        $blade = '<x-alert type="warning">Warning!</x-alert><x-button>Click</x-button>';
        $doc = $this->parse($blade);

        $bladeComponents = $doc->xpath('//*[@data-forte-component-type="blade"]');
        expect($bladeComponents)->toHaveCount(2);
    });

    it('finds Livewire components by type', function (): void {
        $blade = '<livewire:counter /><livewire:search-users />';
        $doc = $this->parse($blade);

        $livewire = $doc->xpath('//*[@data-forte-component-type="livewire"]');
        expect($livewire)->toHaveCount(2);
    });

    it('finds Flux components by type', function (): void {
        $blade = '<flux:button>Save</flux:button><flux:input type="text" />';
        $doc = $this->parse($blade);

        $flux = $doc->xpath('//*[@data-forte-component-type="flux"]');
        expect($flux)->toHaveCount(2);
    });

    it('distinguishes component types in mixed document', function (): void {
        $blade = '
            <x-layout>
                <livewire:counter />
                <flux:button>Click</flux:button>
                <x-card>Content</x-card>
            </x-layout>
        ';
        $doc = $this->parse($blade);

        expect($doc->xpath('//*[@data-forte-component-type="blade"]'))->toHaveCount(2)
            ->and($doc->xpath('//*[@data-forte-component-type="livewire"]'))->toHaveCount(1)
            ->and($doc->xpath('//*[@data-forte-component-type="flux"]'))->toHaveCount(1);
    });
});

describe('XPath slot component queries', function (): void {
    it('finds slot components directly', function (): void {
        $blade = '
            <x-card>
                <x-slot:header>Header Content</x-slot:header>
                <p>Body content</p>
                <x-slot:footer>Footer Content</x-slot:footer>
            </x-card>
        ';
        $doc = $this->parse($blade);

        $slots = $doc->xpath('//*[@data-forte-slot="true"]');
        expect($slots)->toHaveCount(2);
    });

    it('finds slot by name attribute', function (): void {
        $blade = '
            <x-modal>
                <x-slot:title>My Title</x-slot:title>
                <x-slot:body>Content here</x-slot:body>
                <x-slot:actions>
                    <button>Save</button>
                </x-slot:actions>
            </x-modal>
        ';
        $doc = $this->parse($blade);

        $titleSlot = $doc->xpath('//*[@data-forte-slot-name="title"]');
        expect($titleSlot)->toHaveCount(1);

        $actionsSlot = $doc->xpath('//*[@data-forte-slot-name="actions"]');
        expect($actionsSlot)->toHaveCount(1);
    });

    it('distinguishes slots from regular components', function (): void {
        $blade = '
            <x-card>
                <x-slot:header>Header</x-slot:header>
                <x-button>Not a slot</x-button>
            </x-card>
        ';
        $doc = $this->parse($blade);

        $slots = $doc->xpath('//*[@data-forte-slot="true"]');
        expect($slots)->toHaveCount(1);

        $button = $doc->xpath('//x-button[not(@data-forte-slot)]');
        expect($button)->toHaveCount(1);
    });

    it('finds unnamed slot components', function (): void {
        $blade = '<x-layout><x-slot>Default slot content</x-slot></x-layout>';
        $doc = $this->parse($blade);

        $unnamedSlot = $doc->xpath('//*[@data-forte-slot="true"][not(@data-forte-slot-name)]');
        expect($unnamedSlot)->toHaveCount(1);
    });
});

describe('XPath spread attribute queries', function (): void {
    it('finds elements with attribute spread', function (): void {
        $blade = '<div {{ $attributes }} class="base">Content</div>';
        $doc = $this->parse($blade);

        $withSpread = $doc->xpath('//*[@data-forte-has-spread="true"]');
        expect($withSpread)->toHaveCount(1);
    });

    it('finds elements with @class directive spread', function (): void {
        $blade = '<button @class(["btn", "active" => $isActive])>Click</button>';
        $doc = $this->parse($blade);

        $withSpread = $doc->xpath('//button[@data-forte-has-spread="true"]');
        expect($withSpread)->toHaveCount(1);
    });

    it('finds elements with @style directive spread', function (): void {
        $blade = '<div @style(["color: red", "font-weight" => "bold"])>Styled</div>';
        $doc = $this->parse($blade);

        $withSpread = $doc->xpath('//div[@data-forte-has-spread="true"]');
        expect($withSpread)->toHaveCount(1);
    });

    it('distinguishes spread from non-spread elements', function (): void {
        $blade = '
            <div {{ $attributes }}>With spread</div>
            <div class="static">Without spread</div>
            <span @class(["test"])>Also with spread</span>
        ';
        $doc = $this->parse($blade);

        $withSpread = $doc->xpath('//*[@data-forte-has-spread="true"]');
        expect($withSpread)->toHaveCount(2);

        $withoutSpread = $doc->xpath('//div[not(@data-forte-has-spread)]');
        expect($withoutSpread)->toHaveCount(1);
    });

    it('finds components with merged attributes', function (): void {
        $blade = '<x-input {{ $attributes->merge(["class" => "form-control"]) }} />';
        $doc = $this->parse($blade);

        $withSpread = $doc->xpath('//x-input[@data-forte-has-spread="true"]');
        expect($withSpread)->toHaveCount(1);
    });
});

describe('XPath intermediate directive queries', function (): void {
    it('finds all @else directives directly', function (): void {
        $blade = '
            @if($a)
                A
            @else
                Not A
            @endif
            @if($b)
                B
            @else
                Not B
            @endif
        ';
        $doc = $this->parse($blade);

        $allElse = $doc->xpath('//*[@data-forte-intermediate="true"]');
        expect($allElse)->toHaveCount(2);
    });

    it('finds @elseif directives directly', function (): void {
        $blade = '
            @if($type === "a")
                Type A
            @elseif($type === "b")
                Type B
            @elseif($type === "c")
                Type C
            @else
                Unknown
            @endif
        ';
        $doc = $this->parse($blade);

        $elseifs = $doc->xpath('//forte:elseif[@data-forte-intermediate="true"]');
        expect($elseifs)->toHaveCount(2);

        $allIntermediates = $doc->xpath('//*[@data-forte-intermediate="true"]');
        expect($allIntermediates)->toHaveCount(3);
    });

    it('finds @empty directives in forelse', function (): void {
        $blade = '
            @forelse($items as $item)
                {{ $item }}
            @empty
                No items
            @endforelse
        ';
        $doc = $this->parse($blade);

        $empty = $doc->xpath('//forte:empty[@data-forte-intermediate="true"]');
        expect($empty)->toHaveCount(1);
    });

    it('finds @case and @default in switch', function (): void {
        $blade = '
            @switch($value)
                @case(1)
                    One
                    @break
                @case(2)
                    Two
                    @break
                @default
                    Other
            @endswitch
        ';
        $doc = $this->parse($blade);

        $cases = $doc->xpath('//forte:case[@data-forte-intermediate="true"]');
        expect($cases)->toHaveCount(2);

        $default = $doc->xpath('//forte:default[@data-forte-intermediate="true"]');
        expect($default)->toHaveCount(1);
    });

    it('counts all intermediates in complex document', function (): void {
        $blade = '
            @if($a)
                @if($b)
                    Both
                @else
                    Just A
                @endif
            @elseif($c)
                Just C
            @else
                Neither
            @endif
        ';
        $doc = $this->parse($blade);

        $count = $doc->xpath('//*[@data-forte-intermediate="true"]')->count();
        expect($count)->toBe(3);
    });
});

describe('XPath dynamic attribute name queries', function (): void {
    it('finds elements with dynamic attribute names', function (): void {
        $blade = '<div {{ $attrName }}="value">Dynamic attr name</div>';
        $doc = $this->parse($blade);

        $withDynamic = $doc->xpath('//*[@data-forte-dynamic-attrs]');
        expect($withDynamic)->toHaveCount(1);
    });

    it('finds elements by individual dynamic attribute name marker', function (): void {
        $blade = '<div class{{ $suffix }}="value">Dynamic name</div>';
        $doc = $this->parse($blade);

        $withDynamic = $doc->xpath('//*[@data-forte-dynamic-attrs]');
        expect($withDynamic)->toHaveCount(1);
    });

    it('distinguishes bound attributes from dynamic names', function (): void {
        $blade = '
            <div :class="$class">Bound class (value is dynamic)</div>
            <div class{{ $x }}="y">Dynamic name (name contains expression)</div>
            <div class="static">Static class</div>
        ';
        $doc = $this->parse($blade);

        $withDynamicName = $doc->xpath('//*[@data-forte-dynamic-attrs]');
        expect($withDynamicName)->toHaveCount(1);

        $withBoundClass = $doc->xpath('//*[@forte:bind-class]');
        expect($withBoundClass)->toHaveCount(1);
    });
});
