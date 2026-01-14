<?php

declare(strict_types=1);

use Forte\Enclaves\EnclavesManager;
use Forte\Rewriting\Visitor;

class VendorCoverageAppTransformer extends Visitor
{
    public function getName(): string
    {
        return 'VendorCoverageAppTransformer';
    }
}

class VendorCoveragePackageTransformer extends Visitor
{
    public function getName(): string
    {
        return 'VendorCoveragePackageTransformer';
    }
}

class VendorCoverageAnotherPackageTransformer extends Visitor
{
    public function getName(): string
    {
        return 'VendorCoverageAnotherPackageTransformer';
    }
}

class VendorCoverageOptInTransformer extends Visitor
{
    public function getName(): string
    {
        return 'VendorCoverageOptInTransformer';
    }
}

describe('Enclaves Vendor Directory Coverage', function (): void {
    it('packages can define their own enclave that applies transformers only to their package views', function (): void {
        $reg = new EnclavesManager;

        $acmeEnclave = $reg->create('vendor:acme/blog');
        $acmeEnclave
            ->include('C:/packages/acme/blog/resources/views/**')  // Internal package views
            ->include(resource_path('views/vendor/acme/blog/**')) // Published views
            ->addRewriter(VendorCoveragePackageTransformer::class, 10);

        $utilsEnclave = $reg->create('vendor:utils/helpers');
        $utilsEnclave
            ->include('C:/packages/utils/helpers/resources/views/**')
            ->include(resource_path('views/vendor/utils/helpers/**'))
            ->addRewriter(VendorCoverageAnotherPackageTransformer::class, 10);

        $acmeInternalView = 'C:/packages/acme/blog/resources/views/post.blade.php';
        $acmePublishedView = resource_path('views/vendor/acme/blog/index.blade.php');

        $acmeInternalTransformers = $reg->getRewriterClassesForPath($acmeInternalView);
        $acmePublishedTransformers = $reg->getRewriterClassesForPath($acmePublishedView);

        expect($acmeInternalTransformers)->toBe([VendorCoveragePackageTransformer::class])
            ->and($acmePublishedTransformers)->toBe([VendorCoveragePackageTransformer::class]);

        $utilsInternalView = 'C:/packages/utils/helpers/resources/views/helper.blade.php';
        $utilsPublishedView = resource_path('views/vendor/utils/helpers/alert.blade.php');

        $utilsInternalRewriters = $reg->getRewriterClassesForPath($utilsInternalView);
        $utilsPublishedRewriters = $reg->getRewriterClassesForPath($utilsPublishedView);

        expect($utilsInternalRewriters)->toBe([VendorCoverageAnotherPackageTransformer::class])
            ->and($utilsPublishedRewriters)->toBe([VendorCoverageAnotherPackageTransformer::class]);
    });

    it('does not rewrite views from the vendor directory by default', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()->addRewriter(VendorCoverageAppTransformer::class, 5);

        $vendorView = resource_path('views/vendor/some-package/template.blade.php');
        $transformers = $reg->getRewriterClassesForPath($vendorView);

        expect($transformers)->toBeEmpty();
    });

    it('package rewriters can register the published path (resources/views/vendor/package/path)', function (): void {
        $reg = new EnclavesManager;

        $packageEnclave = $reg->create('vendor:newsletter/manager');

        $publishedPath = resource_path('views/vendor/newsletter/manager/**');
        $packageEnclave
            ->include($publishedPath)
            ->addRewriter(VendorCoveragePackageTransformer::class, 8);

        $publishedView = resource_path('views/vendor/newsletter/manager/email-template.blade.php');
        $rewriters = $reg->getRewriterClassesForPath($publishedView);

        expect($rewriters)->toBe([VendorCoveragePackageTransformer::class]);

        $otherVendorView = resource_path('views/vendor/other-package/template.blade.php');
        $otherRewriters = $reg->getRewriterClassesForPath($otherVendorView);

        expect($otherRewriters)->toBeEmpty();
    });

    it('application rewriters do not apply to vendor views by default', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()
            ->addRewriter(VendorCoverageAppTransformer::class, 10)
            ->addRewriter(VendorCoverageOptInTransformer::class, 5);

        $vendorPaths = [
            resource_path('views/vendor/package1/template.blade.php'),
            resource_path('views/vendor/nested/deep/package/view.blade.php'),
            resource_path('views/vendor/simple.blade.php'),
        ];

        expect($reg->getRewriterClassesForPath($vendorPaths[0]))->toBeEmpty()
            ->and($reg->getRewriterClassesForPath($vendorPaths[1]))->toBeEmpty()
            ->and($reg->getRewriterClassesForPath($vendorPaths[2]))->toBeEmpty();

        $appView = resource_path('views/welcome.blade.php');
        $appRewriters = $reg->getRewriterClassesForPath($appView);
        expect($appRewriters)
            ->toBe([
                VendorCoverageAppTransformer::class,
                VendorCoverageOptInTransformer::class,
            ]);
    });

    it('allows application transformers to opt-in to rewrite vendor views with explicit include', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()->addRewriter(VendorCoverageAppTransformer::class, 10);

        $vendorView = resource_path('views/vendor/chosen-package/template.blade.php');
        $initialTransformers = $reg->getRewriterClassesForPath($vendorView);
        expect($initialTransformers)->toBeEmpty();

        $reg->defaultEnclave()->include(resource_path('views/vendor/chosen-package/**'));

        $optedInTransformers = $reg->getRewriterClassesForPath($vendorView);
        expect($optedInTransformers)->toBe([VendorCoverageAppTransformer::class]);

        $otherVendorView = resource_path('views/vendor/other-package/template.blade.php');
        $otherTransformers = $reg->getRewriterClassesForPath($otherVendorView);
        expect($otherTransformers)->toBeEmpty();
    });

    it('allows opting in to rewrite all vendor views globally', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()->addRewriter(VendorCoverageAppTransformer::class, 10);

        $reg->defaultEnclave()->include(resource_path('views/vendor/**'));

        $vendorPaths = [
            resource_path('views/vendor/package1/template.blade.php'),
            resource_path('views/vendor/package2/nested/view.blade.php'),
            resource_path('views/vendor/simple.blade.php'),
        ];

        expect($reg->getRewriterClassesForPath($vendorPaths[0]))->toBe([VendorCoverageAppTransformer::class])
            ->and($reg->getRewriterClassesForPath($vendorPaths[1]))->toBe([VendorCoverageAppTransformer::class])
            ->and($reg->getRewriterClassesForPath($vendorPaths[2]))->toBe([VendorCoverageAppTransformer::class]);
    });

    it('package transformers work alongside app transformers when app opts in', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()
            ->addRewriter(VendorCoverageAppTransformer::class, 20)
            ->include(resource_path('views/vendor/shared-package/**')); // Opt-in to this vendor package

        $packageEnclave = $reg->create('vendor:shared-package');
        $packageEnclave
            ->include(resource_path('views/vendor/shared-package/**'))
            ->addRewriter(VendorCoveragePackageTransformer::class, 10);

        $sharedView = resource_path('views/vendor/shared-package/shared-template.blade.php');
        $transformers = $reg->getRewriterClassesForPath($sharedView);

        expect($transformers)->toBe([
            VendorCoverageAppTransformer::class,
            VendorCoveragePackageTransformer::class,
        ]);
    });

    it('respects priority ordering when multiple enclaves target vendor paths', function (): void {
        $reg = new EnclavesManager;

        $highPriorityEnclave = $reg->create('high-priority');
        $highPriorityEnclave
            ->include(resource_path('views/vendor/multi-target/**'))
            ->addRewriter(VendorCoverageAppTransformer::class, 100);

        $medPriorityEnclave = $reg->create('med-priority');
        $medPriorityEnclave
            ->include(resource_path('views/vendor/multi-target/**'))
            ->addRewriter(VendorCoveragePackageTransformer::class, 50);

        $lowPriorityEnclave = $reg->create('low-priority');
        $lowPriorityEnclave
            ->include(resource_path('views/vendor/multi-target/**'))
            ->addRewriter(VendorCoverageOptInTransformer::class, 10);

        $targetView = resource_path('views/vendor/multi-target/view.blade.php');
        $transformers = $reg->getRewriterClassesForPath($targetView);

        expect($transformers)->toBe([
            VendorCoverageAppTransformer::class,           // Priority 100
            VendorCoveragePackageTransformer::class,       // Priority 50
            VendorCoverageOptInTransformer::class,         // Priority 10
        ]);
    });

    it('allows complex include/exclude patterns for vendor directory control', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()
            ->addRewriter(VendorCoverageAppTransformer::class, 10)
            ->include(resource_path('views/vendor/**'))          // Include all vendor
            ->exclude(resource_path('views/vendor/excluded/**')) // But exclude specific package
            ->include(resource_path('views/vendor/excluded/special/**')); // Re-include specific subpath

        // Included vendor path gets transformer
        $includedView = resource_path('views/vendor/included/template.blade.php');
        $includedTransformers = $reg->getRewriterClassesForPath($includedView);
        expect($includedTransformers)->toBe([VendorCoverageAppTransformer::class]);

        // Excluded vendor path gets no transformer
        $excludedView = resource_path('views/vendor/excluded/template.blade.php');
        $excludedTransformers = $reg->getRewriterClassesForPath($excludedView);
        expect($excludedTransformers)->toBeEmpty();

        // The re-included path gets a transformer
        $specialView = resource_path('views/vendor/excluded/special/template.blade.php');
        $specialTransformers = $reg->getRewriterClassesForPath($specialView);
        expect($specialTransformers)->toBe([VendorCoverageAppTransformer::class]);
    });
});
