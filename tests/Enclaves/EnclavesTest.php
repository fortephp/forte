<?php

declare(strict_types=1);

use Forte\Enclaves\Enclave;
use Forte\Enclaves\EnclavesManager;
use Forte\Rewriting\Visitor;

class ConvenienceAppTransformer extends Visitor
{
    public function getName(): string
    {
        return 'ConvenienceAppTransformer';
    }
}

class ConveniencePackageTransformer extends Visitor
{
    public function getName(): string
    {
        return 'ConveniencePackageTransformer';
    }
}

describe('Enclaves', function (): void {
    test('forPackage method configures enclave for both internal and published package views', function (): void {
        $enclave = new Enclave;
        $enclave->forPackage('blog-system', 'C:/packages/blog-system');

        $publishedView = resource_path('views/vendor/blog-system/post.blade.php');
        $internalView = 'C:/packages/blog-system/resources/views/admin.blade.php';

        expect($enclave->matches($publishedView))->toBeTrue()
            ->and($enclave->matches($internalView))->toBeTrue();
    });

    test('forPackage method works with package name only', function (): void {
        $enclave = new Enclave;
        $enclave->forPackage('cms-tools');

        $publishedView = resource_path('views/vendor/cms-tools/dashboard.blade.php');

        expect($enclave->matches($publishedView))->toBeTrue();
    });

    test('includeVendorPackage method includes specific vendor packages', function (): void {
        $enclave = new Enclave;
        $enclave->includeVendorPackage('package1', 'package2', 'package3');

        $pkg1View = resource_path('views/vendor/package1/view.blade.php');
        $pkg2View = resource_path('views/vendor/package2/template.blade.php');
        $pkg3View = resource_path('views/vendor/package3/component.blade.php');
        $otherView = resource_path('views/vendor/other-package/view.blade.php');

        expect($enclave->matches($pkg1View))->toBeTrue()
            ->and($enclave->matches($pkg2View))->toBeTrue()
            ->and($enclave->matches($pkg3View))->toBeTrue()
            ->and($enclave->matches($otherView))->toBeFalse();
    });

    test('includeAllVendorViews method includes all vendor views', function (): void {
        $enclave = new Enclave;
        $enclave->includeAllVendorViews();

        $vendorPaths = [
            resource_path('views/vendor/any-package/view.blade.php'),
            resource_path('views/vendor/deep/nested/package/template.blade.php'),
            resource_path('views/vendor/simple.blade.php'),
        ];

        expect(collect($vendorPaths)->every(fn ($path) => $enclave->matches($path)))->toBeTrue();
    });

    test('excludeVendorPackage method excludes specific vendor packages', function (): void {
        $enclave = new Enclave;
        $enclave
            ->includeAllVendorViews()
            ->excludeVendorPackage('excluded1', 'excluded2');

        $includedView = resource_path('views/vendor/included/view.blade.php');
        $excluded1View = resource_path('views/vendor/excluded1/view.blade.php');
        $excluded2View = resource_path('views/vendor/excluded2/template.blade.php');

        expect($enclave->matches($includedView))->toBeTrue()
            ->and($enclave->matches($excluded1View))->toBeFalse()
            ->and($enclave->matches($excluded2View))->toBeFalse();
    });

    test('createForPackage registry method creates properly configured package enclave', function (): void {
        $reg = new EnclavesManager;
        $packageEnclave = $reg->createForPackage('newsletter', 'C:/packages/newsletter');

        expect($reg->get('vendor:newsletter'))->toBe($packageEnclave);

        $publishedView = resource_path('views/vendor/newsletter/email.blade.php');
        $internalView = 'C:/packages/newsletter/resources/views/template.blade.php';

        expect($packageEnclave->matches($publishedView))->toBeTrue()
            ->and($packageEnclave->matches($internalView))->toBeTrue();
    });

    test('includeVendorPackages registry method configures app enclave to include vendor packages', function (): void {
        $reg = new EnclavesManager;
        $reg->defaultEnclave()->addRewriter(ConvenienceAppTransformer::class, 10);
        $reg->includeVendorPackages('chosen1', 'chosen2');

        $chosen1View = resource_path('views/vendor/chosen1/view.blade.php');
        $chosen2View = resource_path('views/vendor/chosen2/template.blade.php');
        $otherView = resource_path('views/vendor/other/view.blade.php');

        $chosen1Transformers = $reg->getRewriterClassesForPath($chosen1View);
        $chosen2Transformers = $reg->getRewriterClassesForPath($chosen2View);
        $otherTransformers = $reg->getRewriterClassesForPath($otherView);

        expect($chosen1Transformers)->toBe([ConvenienceAppTransformer::class])
            ->and($chosen2Transformers)->toBe([ConvenienceAppTransformer::class])
            ->and($otherTransformers)->toBeEmpty();
    });

    test('includeVendor registry method configures app enclave for all vendor views', function (): void {
        $reg = new EnclavesManager;
        $reg->defaultEnclave()->addRewriter(ConvenienceAppTransformer::class, 10);
        $reg->includeVendor();

        $vendorPaths = [
            resource_path('views/vendor/package1/view.blade.php'),
            resource_path('views/vendor/package2/template.blade.php'),
            resource_path('views/vendor/nested/deep/component.blade.php'),
        ];

        expect(collect($vendorPaths)->every(
            fn ($path) => $reg->getRewriterClassesForPath($path) === [ConvenienceAppTransformer::class]
        ))->toBeTrue();
    });

    test('excludeVendorPackages registry method excludes specific packages from app enclave', function (): void {
        $reg = new EnclavesManager;
        $reg->defaultEnclave()->addRewriter(ConvenienceAppTransformer::class, 10);
        $reg->includeVendor()
            ->excludeVendorPackages('unwanted1', 'unwanted2');

        $normalView = resource_path('views/vendor/normal/view.blade.php');
        $unwanted1View = resource_path('views/vendor/unwanted1/view.blade.php');
        $unwanted2View = resource_path('views/vendor/unwanted2/template.blade.php');

        $normalTransformers = $reg->getRewriterClassesForPath($normalView);
        $unwanted1Transformers = $reg->getRewriterClassesForPath($unwanted1View);
        $unwanted2Transformers = $reg->getRewriterClassesForPath($unwanted2View);

        expect($normalTransformers)->toBe([ConvenienceAppTransformer::class])
            ->and($unwanted1Transformers)->toBeEmpty()
            ->and($unwanted2Transformers)->toBeEmpty();
    });

    test('convenience methods work together for complex scenarios', function (): void {
        $reg = new EnclavesManager;

        $reg->defaultEnclave()
            ->addRewriter(ConvenienceAppTransformer::class, 20)
            ->includeVendorPackage('shared-ui', 'core-components');

        $packageEnclave = $reg->createForPackage('shared-ui');
        $packageEnclave->addRewriter(ConveniencePackageTransformer::class, 10);

        $sharedView = resource_path('views/vendor/shared-ui/button.blade.php');
        $transformers = $reg->getRewriterClassesForPath($sharedView);
        expect($transformers)->toBe([ConvenienceAppTransformer::class, ConveniencePackageTransformer::class]);

        $coreView = resource_path('views/vendor/core-components/modal.blade.php');
        $coreTransformers = $reg->getRewriterClassesForPath($coreView);
        expect($coreTransformers)->toBe([ConvenienceAppTransformer::class]);

        $otherView = resource_path('views/vendor/other-package/view.blade.php');
        $otherTransformers = $reg->getRewriterClassesForPath($otherView);
        expect($otherTransformers)->toBeEmpty();
    });
});
