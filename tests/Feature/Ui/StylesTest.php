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

it('ships no stylesheet and no components of its own', function (): void {
    $root = dirname(__DIR__, 3);

    expect(is_dir($root.'/resources/css'))->toBeFalse()
        ->and(is_dir($root.'/resources/views/components'))->toBeFalse();
});
