<?php

declare(strict_types=1);

use Forte\Enclaves\Enclave;
use Forte\Enclaves\EnclavesManager;
use Forte\Enclaves\PathMatcher;

describe('Enclave Path Matching', function (): void {
    it('supports exact file match with normalized slashes and case-insensitive comparison', function (): void {
        $enclave = new Enclave;
        $enclave->include('C:/App/src/Parser/Foo.php');

        expect($enclave->matches('C:\\app\\src\\Parser\\Foo.PHP'))->toBeTrue()
            ->and($enclave->matches('C:\\app\\src\\Parser\\Bar.php'))->toBeFalse();
    });

    it('supports single and double star wildcards', function (): void {
        $enclave = new Enclave;

        $enclave->include('**/src/*/Foo.php');
        expect($enclave->matches('C:/project/src/Parser/Foo.php'))->toBeTrue()
            ->and($enclave->matches('C:/project/src/Parser/Sub/Foo.php'))->toBeFalse();

        $enclave->include('**/src/**/Foo.php');
        expect($enclave->matches('C:/project/src/Parser/Sub/Foo.php'))->toBeTrue();
    });

    it('supports directory-wide includes and excludes with precedence to excludes', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');
        $enclave->exclude('**/src/**/Generated/**');

        expect($enclave->matches('D:/code/app/src/Module/File.php'))->toBeTrue()
            ->and($enclave->matches('D:/code/app/src/Generated/Thing.php'))->toBeFalse();
    });

    it('returns false when no include patterns are defined', function (): void {
        $enclave = new Enclave;
        expect($enclave->matches('any/path/file.txt'))->toBeFalse();
    });

    it('can manage multiple enclaves via registry', function (): void {
        $registry = new EnclavesManager;

        $registry->create('A')->include('**/src/**')->exclude('**/src/**/Tests/**');
        $registry->create('B')->include('**/tests/**');

        expect($registry->isPathInEnclave('A', 'C:/repo/src/Core/Service.php'))->toBeTrue()
            ->and($registry->isPathInEnclave('A', 'C:/repo/src/Core/Tests/ServiceTest.php'))->toBeFalse()
            ->and($registry->isPathInEnclave('B', 'C:/repo/tests/Feature/ExampleTest.php'))->toBeTrue()
            ->and($registry->isPathInEnclave('B', 'C:/repo/src/Core/Service.php'))->toBeFalse();

    });

    it('normalizes backslashes in patterns', function (): void {
        $enclave = new Enclave;
        $enclave->include('C:\\repo\\src\\**\\Foo.php');

        expect($enclave->matches('C:/repo/src/Domain/Foo.php'))->toBeTrue()
            ->and($enclave->matches('C:/repo/src/Domain/Bar.php'))->toBeFalse();
    });

    it('matches common path structures', function (): void {
        expect(PathMatcher::match('src/App.php', 'src/App.php'))->toBeTrue()
            ->and(PathMatcher::match('src/App.php', 'src/app.php'))->toBeTrue()
            ->and(PathMatcher::match('src/*/Foo.php', 'src/Parser/Foo.php'))->toBeTrue()
            ->and(PathMatcher::match('src/*/Foo.php', 'src/Parser/Sub/Foo.php'))->toBeFalse()
            ->and(PathMatcher::match('src/**/Foo.php', 'src/Parser/Sub/Foo.php'))->toBeTrue()
            ->and(PathMatcher::match('**/src/**/file.php', 'C:/X/src/Module/file.php'))->toBeTrue()
            ->and(PathMatcher::match('**/src/**/Generated/**', 'D:/code/app/src/Generated/Thing.php'))->toBeTrue();
    });

    it('returns 0 for empty pattern', function (): void {
        expect(PathMatcher::specificityScore(''))->toBe(0);
    });

    it('scores exact segment higher than wildcard segment', function (): void {
        $exactScore = PathMatcher::specificityScore('src/foo.php');
        $wildcardScore = PathMatcher::specificityScore('src/*.php');

        expect($exactScore)->toBeGreaterThan($wildcardScore);
    });

    it('scores pattern without wildcards higher than pattern with wildcards', function (): void {
        $noWildcardScore = PathMatcher::specificityScore('app/views/home.blade.php');
        $withWildcardScore = PathMatcher::specificityScore('app/*/home.blade.php');

        expect($noWildcardScore)->toBeGreaterThan($withWildcardScore);
    });

    it('adds depth bonus for double wildcard without specificity', function (): void {
        $withDoubleWildcard = PathMatcher::specificityScore('**/src/foo.php');
        $withoutDoubleWildcard = PathMatcher::specificityScore('src/foo.php');

        expect($withDoubleWildcard)->toBe($withoutDoubleWildcard + 1);
    });

    it('scores deeper patterns higher than shallow patterns', function (): void {
        $deepScore = PathMatcher::specificityScore('a/b/c/d.php');
        $shallowScore = PathMatcher::specificityScore('a/d.php');

        expect($deepScore)->toBeGreaterThan($shallowScore);
    });

    it('increases specificity with more non-wildcard characters', function (): void {
        $longerScore = PathMatcher::specificityScore('src/VeryLongFileName.php');
        $shorterScore = PathMatcher::specificityScore('src/a.php');

        expect($longerScore)->toBeGreaterThan($shorterScore);
    });

    it('scores patterns consistently for comparative ranking', function (): void {
        $patterns = [
            'app/views/users/profile.blade.php', // Exact path, no wildcards
            'app/views/users/*.blade.php',       // One wildcard in the filename
            'app/views/**/profile.blade.php',    // ** wildcard in the middle
            'app/**/profile.blade.php',          // ** earlier in a path
            '**/*.blade.php',                     // Only wildcards except extension
            '**/**',                              // Only double wildcards
        ];

        $scores = collect($patterns)->map(PathMatcher::specificityScore(...));

        expect($scores->sliding(2)->every(fn ($pair) => $pair->first() > $pair->last()))->toBeTrue();
    });

    it('handles patterns with only double wildcards', function (): void {
        $score = PathMatcher::specificityScore('**/**/**');

        expect($score)->toBeGreaterThan(0)
            ->and($score)->toBeLessThan(10);
    });

    it('gives identical patterns identical scores', function (): void {
        $pattern = 'src/app/**/views/*.blade.php';
        $score1 = PathMatcher::specificityScore($pattern);
        $score2 = PathMatcher::specificityScore($pattern);

        expect($score1)->toBe($score2);
    });

    it('normalizes patterns before scoring', function (): void {
        $unixStyle = PathMatcher::specificityScore('src/app/foo.php');
        $windowsStyle = PathMatcher::specificityScore('src\\app\\foo.php');
        $mixedCase = PathMatcher::specificityScore('SRC/APP/FOO.PHP');

        expect($unixStyle)->toBe($windowsStyle)
            ->and($unixStyle)->toBe($mixedCase);
    });

    it('matches most specific include over a generic include', function (): void {
        $specificInclude = PathMatcher::specificityScore('resources/views/vendor/acme/blog/**');
        $genericExclude = PathMatcher::specificityScore('resources/views/vendor/**');

        expect($specificInclude)->toBeGreaterThan($genericExclude);
    });

    it('scores file pattern higher than directory pattern', function (): void {
        $filePattern = PathMatcher::specificityScore('src/views/home.blade.php');
        $dirPattern = PathMatcher::specificityScore('src/views/**');

        expect($filePattern)->toBeGreaterThan($dirPattern);
    });
});
