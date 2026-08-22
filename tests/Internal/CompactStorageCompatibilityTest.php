<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Internal\FlatNodeRecord;
use Forte\Internal\TokenRecord;
use Forte\Lexer\Lexer;
use Forte\Parser\TreeBuilder;

describe('Compact storage compatibility', function (): void {
    it('keeps lexer and tree-builder return shapes unchanged', function (): void {
        $source = '<div class="p-4">{{ $value }}</div>';
        $lexerResult = (new Lexer($source))->tokenize();
        $treeResult = (new TreeBuilder($lexerResult->tokens, $source))->build();

        expect($lexerResult->tokens)->toBeArray()->not->toBeEmpty()
            ->and($lexerResult->tokens[0])->toBeArray()->toHaveKeys(['type', 'start', 'end'])
            ->and($treeResult)->toHaveKeys(['nodes', 'source', 'tokens'])
            ->and($treeResult['nodes'][0])->toBeArray()->toHaveKeys([
                'kind',
                'parent',
                'firstChild',
                'lastChild',
                'nextSibling',
                'tokenStart',
                'tokenCount',
                'genericOffset',
                'data',
            ])
            ->and($treeResult['tokens'])->toBe($lexerResult->tokens);
    });

    it('retains compact records while public document accessors return arrays', function (): void {
        $source = '<div>{{ $value }}</div>';
        $document = Document::parse($source);
        $nodesProperty = new ReflectionProperty($document, 'nodes');
        $storedNodes = $nodesProperty->getValue($document);

        expect($storedNodes[0])->toBeInstanceOf(FlatNodeRecord::class)
            ->and($document->getTokenRecords()[0])->toBeInstanceOf(TokenRecord::class)
            ->and($document->getFlatNode(0))->toBeArray()->toHaveKeys([
                'kind',
                'parent',
                'firstChild',
                'lastChild',
                'nextSibling',
                'tokenStart',
                'tokenCount',
                'genericOffset',
                'data',
            ])
            ->and($document->getToken(0))->toBeArray()->toHaveKeys(['type', 'start', 'end'])
            ->and($document->getNodes())->each->toBeArray()
            ->and($document->getTokens())->each->toBeArray();
    });

    it('round-trips public flat parts without changing content or shapes', function (): void {
        $source = '<section><p>Hello</p></section>';
        $document = Document::parse($source);
        $copy = Document::fromParts(
            $document->getNodes(),
            $document->getTokens(),
            $document->source(),
            $document->getDirectivesRegistry(),
            $document->getComponentManager(),
        );

        expect($copy->render())->toBe($source)
            ->and($copy->getNodes())->toBe($document->getNodes())
            ->and($copy->getTokens())->toBe($document->getTokens());
    });

    it('preserves non-structural directive metadata in flat-node arrays', function (): void {
        $document = Document::parse('@if($show)<p>Hello</p>@endif');
        $nodes = $document->getNodes();

        expect($nodes[1])->toHaveKeys(['name', 'args'])
            ->and($nodes[1]['name'])->toBe('if')
            ->and($nodes[1]['args'])->toBe('($show)')
            ->and($nodes[2])->toHaveKeys(['name', 'args', 'role']);
    });
});
