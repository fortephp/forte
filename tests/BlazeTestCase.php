<?php

declare(strict_types=1);

namespace Forte\Tests;

use Forte\Enclaves\EnclavesManager;
use Livewire\Blaze\BlazeServiceProvider;

class BlazeTestCase extends ForteTestCase
{
    protected function setUp(): void
    {
        if (! class_exists(BlazeServiceProvider::class)) {
            $this->markTestSkipped('Blaze is not installed.');
        }

        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        $providers = parent::getPackageProviders($app);

        $providers[] = BlazeServiceProvider::class;

        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        /** @var EnclavesManager $reg */
        $reg = $app->make(EnclavesManager::class);
        $reg->defaultEnclave()
            ->elementConditionalAttributes()
            ->elementForeachAttributes()
            ->elementForelseAttributes();

        $app['blade.compiler']->anonymousComponentPath(
            __DIR__.'/Fixtures/blaze/components',
        );

        $app['config']->set('blaze.enabled', true);
        $app['config']->set('blaze.debug', false);
    }
}
