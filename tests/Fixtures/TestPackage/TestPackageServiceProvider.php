<?php

declare(strict_types=1);

namespace Forte\Tests\Fixtures\TestPackage;

use Forte\Enclaves\EnclavesManager;
use Forte\Tests\Support\Transformers\PackageMarkerVisitor;
use Illuminate\Support\ServiceProvider;

class TestPackageServiceProvider extends ServiceProvider
{
    public function register() {}

    public function boot()
    {
        $viewsPath = realpath(__DIR__.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views');
        if ($viewsPath) {
            $this->loadViewsFrom($viewsPath, 'testpkg');
        }

        $this->app['router']->get('/pack', fn () => view('testpkg::index'));

        /** @var EnclavesManager $registry */
        $registry = $this->app->make(EnclavesManager::class);
        $pkg = $registry->create('testpkg');

        if ($viewsPath) {
            $pkg->include($viewsPath.DIRECTORY_SEPARATOR.'**');
        }

        $pkg->use(PackageMarkerVisitor::class, 10);
    }
}
