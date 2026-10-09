<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Tests\Fixtures\Features;

use RefactorCircus\Impex\Tests\Fixtures\Features\Missing\NotInstalledFeature;

/**
 * A feature whose parent belongs to a package that is not installed, as
 * ImpexSupportFeature is without refactor-circus/pennantplus. Autoloading it throws.
 */
class OrphanedSupportFeature extends NotInstalledFeature {}
