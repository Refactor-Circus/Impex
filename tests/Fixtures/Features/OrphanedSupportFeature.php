<?php

declare(strict_types=1);

namespace JayI\Impex\Tests\Fixtures\Features;

use JayI\Impex\Tests\Fixtures\Features\Missing\NotInstalledFeature;

/**
 * A feature whose parent belongs to a package that is not installed, as
 * ImpexSupportFeature is without jayi/pennantplus. Autoloading it throws.
 */
class OrphanedSupportFeature extends NotInstalledFeature {}
