<?php

declare(strict_types=1);

namespace Forte\Tests;

use Forte\Enclaves\EnclavesManager;
use Forte\Tests\Fixtures\TestPackage\TestPackageServiceProvider;
use Forte\Tests\Support\Transformers\AppMarkerVisitor;

class FeatureTestCase extends ForteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearCompiledViews();
    }

    protected function getPackageProviders($app)
    {
        $providers = parent::getPackageProviders($app);

        $providers[] = TestPackageServiceProvider::class;

        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $base = realpath(__DIR__.'/Fixtures/app');
        if ($base) {
            $app->setBasePath($base);
        }

        $compiled = sys_get_temp_dir().DIRECTORY_SEPARATOR.'forte_pest_compiled';

        if (! is_dir($compiled)) {
            @mkdir($compiled, 0755, true);
        }

        $app['config']->set('view.paths', [resource_path('views')]);
        $app['config']->set('view.compiled', $compiled);

        $app['router']->get('/app', fn () => view('app'));

        /** @var EnclavesManager $reg */
        $reg = $app->make(EnclavesManager::class);
        $reg->defaultEnclave()->use(AppMarkerVisitor::class, 10);
    }

    public function clearCompiledViews(): void
    {
        $dir = $this->app['config']->get('view.compiled');
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
