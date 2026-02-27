<?php

declare(strict_types=1);

namespace Forte\Tests;

use Forte\Enclaves\EnclavesManager;
use Livewire\Blaze\BlazeServiceProvider;

class BlazeTestCase extends FeatureTestCase
{
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

        $componentsPath = realpath(__DIR__.'/Fixtures/blaze/components');

        if ($componentsPath) {
            $app['blade.compiler']->anonymousComponentPath($componentsPath);
        }

        $app['config']->set('blaze.enabled', true);
        $app['config']->set('blaze.debug', false);

        $app['router']->get('/blaze-if', fn () => view('blaze-if', ['show' => true]));

        $app['router']->get('/blaze-foreach', fn () => view('blaze-foreach', [
            'items' => collect([
                (object) ['name' => 'Alpha', 'color' => 'red'],
                (object) ['name' => 'Beta', 'color' => 'blue'],
            ]),
        ]));

        $app['router']->get('/blaze-combined', fn () => view('blaze-combined', [
            'users' => collect([
                (object) ['name' => 'Alice', 'role' => 'admin', 'active' => true],
                (object) ['name' => 'Bob', 'role' => 'user', 'active' => false],
                (object) ['name' => 'Charlie', 'role' => 'admin', 'active' => true],
            ]),
        ]));
    }
}
