<?php

declare(strict_types=1);

use RefactorCircus\Atrium\Testing\AtriumStyles;

/**
 * Atrium owns every component and style of the suite, and Impex ships none:
 * every class its views use must exist in Atrium's compiled stylesheet, and
 * no view styles itself.
 */
it('uses only atrium styles', function (): void {
    $views = dirname(__DIR__, 3).'/resources/views';

    expect(AtriumStyles::missingClasses($views))->toBe([])
        ->and(AtriumStyles::inlineStyles($views))->toBe([]);
});

it('styles the run link on other packages\' screens with atrium classes only', function (): void {
    $view = dirname(__DIR__, 3).'/resources/views/ui/screens';

    expect(is_file($view.'/run-link.blade.php'))->toBeTrue()
        ->and((string) file_get_contents($view.'/run-link.blade.php'))->toContain('class="underline-offset-2 hover:underline"')
        ->and(AtriumStyles::missingClasses($view))->toBe([])
        ->and(AtriumStyles::inlineStyles($view))->toBe([]);
});

it('ships no stylesheet and no components of its own', function (): void {
    $root = dirname(__DIR__, 3);

    expect(is_dir($root.'/resources/css'))->toBeFalse()
        ->and(is_dir($root.'/resources/views/components'))->toBeFalse();
});
