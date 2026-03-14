<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\TextNode;
use Forte\Lexer\Lexer;
use Forte\Parser\Directives\Directives;
use Forte\Parser\ParserOptions;

describe('Directive Discovery', function (): void {
    test('custom unregistered conditions', function (): void {
        $template = <<<'BLADE'
@randomCondition('one')
    <!-- Branch One -->
@elseRandomCondition('two')
    <!-- Branch Two -->
@else
    <!-- Branch Three -->
@endrandomCondition

@unlessrandomCondition('one')
    <!-- Branch Four -->
@endrandomCondition
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->getChildren())->toHaveCount(4)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[2]->getChildren())->toHaveCount(2);

        $randomConditionNodes = $nodes[0]->getChildren();

        expect($randomConditionNodes[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes[0]->asDirective()->nameText())->toBe('randomcondition')
            ->and($randomConditionNodes[0]->asDirective()->arguments())->toBe("('one')")
            ->and($randomConditionNodes[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes[1]->asDirective()->nameText())->toBe('elserandomcondition')
            ->and($randomConditionNodes[1]->asDirective()->arguments())->toBe("('two')")
            ->and($randomConditionNodes[2]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes[2]->asDirective()->nameText())->toBe('else')
            ->and($randomConditionNodes[2]->asDirective()->arguments())->toBeNull()
            ->and($randomConditionNodes[3]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes[3]->asDirective()->nameText())->toBe('endrandomcondition')
            ->and($randomConditionNodes[3]->asDirective()->arguments())->toBeNull()
            ->and($randomConditionNodes[3]->asDirective()->getChildren())->toHaveCount(0);

        $randomCondition = $randomConditionNodes[0]->getChildren();

        expect($randomCondition[0])->toBeInstanceOf(TextNode::class)
            ->and($randomCondition[1])->toBeInstanceOf(CommentNode::class);

        $elseRandomCondition = $randomConditionNodes[1]->getChildren();

        expect($elseRandomCondition[0])->toBeInstanceOf(TextNode::class)
            ->and($elseRandomCondition[1])->toBeInstanceOf(CommentNode::class);

        $else = $randomConditionNodes[2]->getChildren();

        expect($else[0])->toBeInstanceOf(TextNode::class)
            ->and($else[1])->toBeInstanceOf(CommentNode::class);

        $unlessRandomConditionNodes = $nodes[2]->getChildren();

        expect($unlessRandomConditionNodes)->toHaveCount(2)
            ->and($unlessRandomConditionNodes[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($unlessRandomConditionNodes[0]->asDirective()->nameText())->toBe('unlessrandomcondition')
            ->and($unlessRandomConditionNodes[0]->asDirective()->arguments())->toBe("('one')")
            ->and($unlessRandomConditionNodes[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($unlessRandomConditionNodes[1]->asDirective()->nameText())->toBe('endrandomcondition')
            ->and($unlessRandomConditionNodes[1]->asDirective()->arguments())->toBeNull();

        $unlessRandomConditionNodes = $unlessRandomConditionNodes[0]->getChildren();

        expect($unlessRandomConditionNodes)->toHaveCount(3)
            ->and($unlessRandomConditionNodes[0])->toBeInstanceOf(TextNode::class)
            ->and($unlessRandomConditionNodes[1])->toBeInstanceOf(CommentNode::class)
            ->and($unlessRandomConditionNodes[2])->toBeInstanceOf(TextNode::class)
            ->and($doc->render())->toBe($template);
    });

    test('unregistered paired directives are detected', function (): void {
        $blade = <<<'BLADE'
@capture('region name')
    Something here
@endcapture
BLADE;

        $doc = $this->parse($blade, ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockNodes = $nodes[0]->getChildren();

        expect($blockNodes)->toHaveCount(2)
            ->and($blockNodes[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes[0]->asDirective()->nameText())->toBe('capture')
            ->and($blockNodes[0]->asDirective()->arguments())->toBe("('region name')")
            ->and($blockNodes[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes[1]->asDirective()->nameText())->toBe('endcapture')
            ->and($blockNodes[1]->asDirective()->arguments())->toBeNull();

        $captureNodes = $blockNodes[0]->getChildren();

        expect($captureNodes)->toHaveCount(1)
            ->and($captureNodes[0])->toBeInstanceOf(TextNode::class)
            ->and($captureNodes[0]->asText()->getContent())->toBe("\n    Something here\n")
            ->and($doc->render())->toBe($blade);
    });

    test('unregistered paired directives can be promoted to conditions', function (): void {
        $template = <<<'BLADE'
@randomCondition('one')
    <!-- Branch One -->
@endrandomCondition
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockNodes = $nodes[0]->getChildren();

        expect($blockNodes)->toHaveCount(2)
            ->and($blockNodes[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes[0]->asDirective()->nameText())->toBe('randomcondition')
            ->and($blockNodes[0]->asDirective()->arguments())->toBe("('one')")
            ->and($blockNodes[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes[1]->asDirective()->nameText())->toBe('endrandomcondition')
            ->and($blockNodes[1]->asDirective()->arguments())->toBeNull()
            ->and($doc->render())->toBe($template);

        $template = <<<'BLADE'
@randomCondition('one')
    <!-- Branch One -->
@elseRandomCondition('two')
    <!-- Branch Two -->
@else
    <!-- Branch Three -->
@endrandomCondition

@unlessrandomCondition('one')
    <!-- Branch Four -->
@endrandomCondition
BLADE;

        $doc2 = $this->parse($template);
        $nodes2 = $doc2->getChildren();

        expect($nodes2)->toHaveCount(3)
            ->and($nodes2[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes2[0]->getChildren())->toHaveCount(4)
            ->and($nodes2[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes2[2])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes2[2]->getChildren())->toHaveCount(2);

        $randomConditionNodes2 = $nodes2[0]->getChildren();

        expect($randomConditionNodes2[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes2[0]->asDirective()->nameText())->toBe('randomcondition')
            ->and($randomConditionNodes2[0]->asDirective()->arguments())->toBe("('one')")
            ->and($randomConditionNodes2[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes2[1]->asDirective()->nameText())->toBe('elserandomcondition')
            ->and($randomConditionNodes2[1]->asDirective()->arguments())->toBe("('two')")
            ->and($randomConditionNodes2[2]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes2[2]->asDirective()->nameText())->toBe('else')
            ->and($randomConditionNodes2[2]->asDirective()->arguments())->toBeNull()
            ->and($randomConditionNodes2[3]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($randomConditionNodes2[3]->asDirective()->nameText())->toBe('endrandomcondition')
            ->and($randomConditionNodes2[3]->asDirective()->arguments())->toBeNull()
            ->and($randomConditionNodes2[3]->getChildren())->toHaveCount(0);

        $randomCondition2 = $randomConditionNodes2[0]->getChildren();

        expect($randomCondition2[0])->toBeInstanceOf(TextNode::class)
            ->and($randomCondition2[1])->toBeInstanceOf(CommentNode::class);

        $elseRandomCondition2 = $randomConditionNodes2[1]->getChildren();

        expect($elseRandomCondition2[0])->toBeInstanceOf(TextNode::class)
            ->and($elseRandomCondition2[1])->toBeInstanceOf(CommentNode::class);

        $else2 = $randomConditionNodes2[2]->getChildren();

        expect($else2[0])->toBeInstanceOf(TextNode::class)
            ->and($else2[1])->toBeInstanceOf(CommentNode::class);

        $unlessRandomConditionNodes2 = $nodes2[2]->getChildren();

        expect($unlessRandomConditionNodes2)->toHaveCount(2)
            ->and($unlessRandomConditionNodes2[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($unlessRandomConditionNodes2[0]->asDirective()->nameText())->toBe('unlessrandomcondition')
            ->and($unlessRandomConditionNodes2[0]->asDirective()->arguments())->toBe("('one')")
            ->and($unlessRandomConditionNodes2[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($unlessRandomConditionNodes2[1]->asDirective()->nameText())->toBe('endrandomcondition')
            ->and($unlessRandomConditionNodes2[1]->asDirective()->arguments())->toBeNull();

        $unlessRandomConditionNodes2 = $unlessRandomConditionNodes2[0]->getChildren();

        expect($unlessRandomConditionNodes2)->toHaveCount(3)
            ->and($unlessRandomConditionNodes2[0])->toBeInstanceOf(TextNode::class)
            ->and($unlessRandomConditionNodes2[1])->toBeInstanceOf(CommentNode::class)
            ->and($unlessRandomConditionNodes2[2])->toBeInstanceOf(TextNode::class)
            ->and($doc2->render())->toBe($template);

        $template3 = <<<'BLADE'
@randomCondition('one')
    <!-- Branch One -->
@endrandomCondition
BLADE;

        $doc3 = $this->parse($template3);
        $nodes3 = $doc3->getChildren();

        expect($nodes3)->toHaveCount(1)
            ->and($nodes3[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockNodes3 = $nodes3[0]->getChildren();

        expect($blockNodes3)->toHaveCount(2)
            ->and($blockNodes3[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes3[0]->asDirective()->nameText())->toBe('randomcondition')
            ->and($blockNodes3[0]->asDirective()->arguments())->toBe("('one')")
            ->and($blockNodes3[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes3[1]->asDirective()->nameText())->toBe('endrandomcondition')
            ->and($blockNodes3[1]->asDirective()->arguments())->toBeNull()
            ->and($doc3->render())->toBe($template3);
    });

    test('directive case is preserved in render output and name()', function (): void {
        $template = '@IF ($tHiS) content @ENDIF';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0];
        $blockNodes = $block->getChildren();

        expect($blockNodes[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes[0]->nameText())->toBe('if')
            ->and($blockNodes[1])->toBeInstanceOf(DirectiveNode::class)
            ->and($blockNodes[1]->nameText())->toBe('endif')
            ->and($block->asDirectiveBlock()->name())->toBe('IF')
            ->and($blockNodes[0]->asDirective()->name())->toBe('IF')
            ->and($blockNodes[1]->asDirective()->name())->toBe('ENDIF')
            ->and($doc->render())->toBe($template);
    });

    test('training custom conditions does not mutate the explicit registry', function (): void {
        $template = <<<'BLADE'
@randomCondition('one')
    <!-- Branch One -->
@elseRandomCondition('two')
    <!-- Branch Two -->
@else
    <!-- Branch Three -->
@endrandomCondition

@unlessrandomCondition('one')
    <!-- Branch Four -->
@endrandomCondition
BLADE;

        $directives = Directives::withDefaults();
        $directives->setAcceptAll(true);

        $lexer = new Lexer($template, $directives);
        $tokens = $lexer->tokenize()->tokens;
        $directives->train($tokens, $template);

        expect($directives->isCondition('randomcondition'))->toBeFalse()
            ->and($directives->isPaired('randomcondition'))->toBeFalse()
            ->and($directives->isCondition('unlessrandomcondition'))->toBeFalse()
            ->and($directives->isPaired('unlessrandomcondition'))->toBeFalse();
    });

    test('standalone @endwidget does not invent @widget', function (): void {
        $doc = $this->parse('@endwidget', ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[0]->asDirective()->nameText())->toBe('endwidget')
            ->and($doc->getBlockDirectives()->all())->toHaveCount(0);
    });

    test('standalone @elsewidget does not invent @widget', function (): void {
        $doc = $this->parse('@elsewidget', ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[0]->asDirective()->nameText())->toBe('elsewidget')
            ->and($doc->getBlockDirectives()->all())->toHaveCount(0);
    });

    test('custom exact-name branch evidence allows generic condition closers', function (): void {
        $template = "@disk('local') Local storage @elsedisk Global storage @endif";

        $doc = $this->parse($template, ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockNodes = collect($nodes[0]->getChildren())
            ->filter(static fn ($node) => $node instanceof DirectiveNode)
            ->values()
            ->all();

        expect($blockNodes)->toHaveCount(3)
            ->and($blockNodes[0]->asDirective()->nameText())->toBe('disk')
            ->and($blockNodes[1]->asDirective()->nameText())->toBe('elsedisk')
            ->and($blockNodes[2]->asDirective()->nameText())->toBe('endif')
            ->and($doc->render())->toBe($template);
    });

    test('custom unless-family openers are discovered with the shared @endfoo closer', function (): void {
        $template = "@unlessdisk('local') Not local @enddisk";

        $doc = $this->parse($template, ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockNodes = collect($nodes[0]->getChildren())
            ->filter(static fn ($node) => $node instanceof DirectiveNode)
            ->values()
            ->all();

        expect($blockNodes)->toHaveCount(2)
            ->and($blockNodes[0]->asDirective()->nameText())->toBe('unlessdisk')
            ->and($blockNodes[1]->asDirective()->nameText())->toBe('enddisk')
            ->and($doc->render())->toBe($template);

        $directives = Directives::withDefaults();
        $directives->setAcceptAll(true);

        $lexer = new Lexer($template, $directives);
        $tokens = $lexer->tokenize()->tokens;
        $directives->train($tokens, $template);

        expect($directives->isCondition('disk'))->toBeFalse()
            ->and($directives->isCondition('unlessdisk'))->toBeFalse()
            ->and($directives->isPaired('unlessdisk'))->toBeFalse();
    });

    test('unknown pairs without exact-name branch evidence do not gain generic condition branches', function (): void {
        $template = "@disk('s3') Content @else Fallback @enddisk";

        $doc = $this->parse($template, ParserOptions::make()->acceptAllDirectives());
        $blocks = $doc->getBlockDirectives()->values()->all();

        expect($blocks)->toHaveCount(1);

        $blockNodes = collect($blocks[0]->getChildren())
            ->filter(static fn ($node) => $node instanceof DirectiveNode)
            ->values()
            ->all();

        expect($blockNodes)->toHaveCount(2)
            ->and($blockNodes[0]->asDirective()->nameText())->toBe('disk')
            ->and($blockNodes[1]->asDirective()->nameText())->toBe('enddisk');
    });

    test('registered directive discovery does not invent @sub when @endsub already belongs to @hassub', function (): void {
        $template = <<<'BLADE'
@hasfield('list')
  <ul>
    @fields('list')
      <li>@sub('item')</li>
    @endfields
  </ul>
@endfield

@hasoption('facebook_url')
  Find us on <a href="@option('facebook_url')" target="_blank">Facebook</a>
@endoption

@hassub('icon')
  <i class="fas fa-@sub('icon')"></i>
@endsub
BLADE;

        $directives = Directives::withDefaults();
        $directives->loadJson(<<<'JSON'
[
  {"name":"hasfield","args":true,"structure":{"role":"open","terminators":"endfield"}},
  {"name":"endfield","args":false,"structure":{"role":"close"}},
  {"name":"fields","args":true,"structure":{"role":"open","terminators":"endfields"}},
  {"name":"endfields","args":false,"structure":{"role":"close"}},
  {"name":"hasoption","args":true,"structure":{"role":"open","terminators":"endoption"}},
  {"name":"endoption","args":false,"structure":{"role":"close"}},
  {"name":"hassub","args":true,"structure":{"role":"open","terminators":"endsub"}},
  {"name":"endsub","args":false,"structure":{"role":"close"}}
]
JSON);

        $doc = $this->parse($template, ParserOptions::make()->directives($directives)->acceptAllDirectives());
        $blocks = $doc->getBlockDirectives()->values()->all();
        $blockNames = collect($blocks)
            ->map(static fn (DirectiveBlockNode $node): string => $node->nameText())
            ->all();

        expect($directives->isPaired('sub'))->toBeFalse()
            ->and($blockNames)->toBe(['hasfield', 'fields', 'hasoption', 'hassub'])
            ->and($doc->findBlockDirectivesByName('sub')->all())->toHaveCount(0)
            ->and($doc->render())->toBe($template);
    });

    test('accept-all discovery does not invent @sub, @option, or @field when matching @has* siblings are present', function (): void {
        $template = <<<'BLADE'
@hasfield('list')
  <ul>
    @fields('list')
      <li>@sub('item')</li>
    @endfields
  </ul>
@endfield

@hasoption('facebook_url')
  Find us on <a href="@option('facebook_url')" target="_blank">Facebook</a>
@endoption

@hassub('icon')
  <i class="fas fa-@sub('icon')"></i>
@endsub
BLADE;

        $doc = $this->parse($template, ParserOptions::make()->acceptAllDirectives());
        $blocks = $doc->getBlockDirectives()->values()->all();
        $blockNames = collect($blocks)
            ->map(static fn (DirectiveBlockNode $node): string => $node->nameText())
            ->all();

        expect($blockNames)->toBe(['fields'])
            ->and($doc->findBlockDirectivesByName('field')->all())->toHaveCount(0)
            ->and($doc->findBlockDirectivesByName('option')->all())->toHaveCount(0)
            ->and($doc->findBlockDirectivesByName('sub')->all())->toHaveCount(0)
            ->and($doc->render())->toBe($template);
    });

    test('accept-all discovery does not let @elsesection overwrite registered @section semantics', function (): void {
        $template = "@section('sidebar') Content @elsesection @endsection";

        $doc = $this->parse($template, ParserOptions::make()->acceptAllDirectives());
        $blocks = $doc->getBlockDirectives()->values()->all();

        expect($blocks)->toHaveCount(1);

        $blockNodes = collect($blocks[0]->getChildren())
            ->filter(static fn ($node) => $node instanceof DirectiveNode)
            ->values()
            ->all();

        expect($blockNodes)->toHaveCount(2)
            ->and($blockNodes[0]->asDirective()->nameText())->toBe('section')
            ->and($blockNodes[1]->asDirective()->nameText())->toBe('endsection');

        $strayElseSection = $doc->findDirectivesByName('elsesection')->values()->all();

        expect($strayElseSection)->toHaveCount(1)
            ->and($strayElseSection[0]->getParent())->toBeInstanceOf(DirectiveNode::class);
    });
});
