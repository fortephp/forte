<?php

declare(strict_types=1);

use Forte\Enclaves\EnclavesManager;
use Forte\Rewriting\Visitor;

class AppTransformer extends Visitor {}
class PackageTransformer extends Visitor {}

describe('Published Views Enclaved', function (): void {
    test('package enclave applies to published vendor views while app enclave does not by default', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()->addRewriter(AppTransformer::class, 10);

        $pkg = $reg->create('vendor:acme/blog');

        $packageInternalViews = 'C:/packages/acme/blog/resources/views/**';
        $publishedVendorViews = resource_path('views/vendor/acme/blog/**');

        $pkg->include($packageInternalViews, $publishedVendorViews);
        $pkg->addRewriter(PackageTransformer::class, 5);

        $publishedFile = resource_path('views/vendor/acme/blog/home.blade.php');

        $classes = $reg->getRewriterClassesForPath($publishedFile);

        expect($classes)->toBe([PackageTransformer::class]);
    });

    test('most specific app include for vendor path overrides default exclusion and applies app transformer too', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()->addRewriter(AppTransformer::class, 10);

        $pkg = $reg->create('vendor:acme/blog');

        $packageInternalViews = 'C:/packages/acme/blog/resources/views/**';
        $publishedVendorViews = resource_path('views/vendor/acme/blog/**');

        $pkg->include($packageInternalViews, $publishedVendorViews);
        $pkg->addRewriter(PackageTransformer::class, 5);

        $reg->defaultEnclave()->include($publishedVendorViews);

        $publishedFile = resource_path('views/vendor/acme/blog/home.blade.php');

        $classes = $reg->getRewriterClassesForPath($publishedFile);

        expect($classes)->toBe([AppTransformer::class, PackageTransformer::class]);
    });
});
