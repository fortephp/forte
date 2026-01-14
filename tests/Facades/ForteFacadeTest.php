<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Facades\Forte;
use Forte\Parser\Directives\Directives;
use Forte\Parser\ParserOptions;

describe('Forte Facade', function (): void {
    describe('parse()', function (): void {
        it('parses a template string and returns a Document', function (): void {
            $doc = Forte::parse('<div>Hello</div>');

            expect($doc)->toBeInstanceOf(Document::class)
                ->and($doc->render())->toBe('<div>Hello</div>');
        });

        it('accepts optional ParserOptions', function (): void {
            $options = ParserOptions::make()
                ->directives(Directives::withDefaults());

            $doc = Forte::parse('<div>Hello</div>', $options);

            expect($doc)->toBeInstanceOf(Document::class);
        });
    });

    describe('parseFile()', function (): void {
        it('parses a template file and returns a Document', function (): void {
            $path = sys_get_temp_dir().'/forte_facade_test_'.uniqid().'.blade.php';
            file_put_contents($path, '<div>From File</div>');

            try {
                $doc = Forte::parseFile($path);

                expect($doc)->toBeInstanceOf(Document::class)
                    ->and($doc->render())->toBe('<div>From File</div>')
                    ->and($doc->getFilePath())->toBe($path);
            } finally {
                unlink($path);
            }
        });

        it('throws exception for non-existent file', function (): void {
            Forte::parseFile('/non/existent/file.blade.php');
        })->throws(RuntimeException::class, 'Template file not found');

    });
});
