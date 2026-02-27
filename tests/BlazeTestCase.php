<?php

declare(strict_types=1);

namespace Forte\Tests;

use Forte\Enclaves\EnclavesManager;
use Forte\ServiceProvider;
use Livewire\Blaze\BlazeServiceProvider;
use Orchestra\Testbench\TestCase;

class BlazeTestCase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
            BlazeServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
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
