<?php

declare(strict_types=1);

use Forte\Enclaves\EnclavesManager;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Tests\FeatureTestCase;

uses(FeatureTestCase::class);

describe('Enclaves', function (): void {
    it('renders the app route and applies app enclave transformer only', function (): void {
        $response = $this->get('/app');

        $response->assertOk()
            ->assertSee('Hello from App World', false)
            ->assertSee(' [APP]', false)
            ->assertDontSee(' [PKG]', false);
    });

    it('renders the package route and applies package transformer only', function (): void {
        $response = $this->get('/pack');

        $response->assertOk()
            ->assertSee('Hello from Package World', false)
            ->assertSee(' [PKG]', false)
            ->assertDontSee(' [APP]', false);
    });

    it('allows tests to modify the app enclave at runtime', function (): void {
        /** @var EnclavesManager $reg */
        $reg = $this->app->make(EnclavesManager::class);

        $reg->defaultEnclave()->use(new class extends Visitor
        {
            private bool $markerAdded = false;

            public function leave(NodePath $path): void
            {
                if ($this->markerAdded) {
                    return;
                }

                if ($path->isRoot() && $path->nextSibling() === null) {
                    $path->insertAfter(' [APP2]');
                    $this->markerAdded = true;
                }
            }
        }, 9);

        $this->clearCompiledViews();

        $this->get('/app')
            ->assertOk()
            ->assertSee(' [APP]', false)
            ->assertSee(' [APP2]', false);
    });
});
