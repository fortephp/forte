<?php

declare(strict_types=1);

use Forte\Support\ImageSource;

describe('image sources', function (): void {
    it('recognizes SVG and data URLs using URL boundary normalization', function (): void {
        expect(ImageSource::isSvg(' /icons/logo.SVG?theme=dark '))->toBeTrue()
            ->and(ImageSource::isSvg('data:image/svg+xml,<svg/>'))->toBeTrue()
            ->and(ImageSource::isSvg('photo.png'))->toBeFalse()
            ->and(ImageSource::isDataUrl("\x0Bdata:image/png;base64,AA"))->toBeTrue();
    });
});
