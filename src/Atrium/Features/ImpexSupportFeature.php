<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium\Features;

use JayI\PennantPlus\Domains\Feature\Support\OnLayeredFeature;
use Laravel\Pennant\Attributes\Name;

/**
 * Switches Impex in Atrium on and off: its navigation, widgets, settings,
 * search and pages. On until its global value is set. The `SupportFeature`
 * suffix matches PennantPlus's default `gate.global_only` pattern, so only
 * the global value counts and per-user access stays with Impex's policies.
 *
 * Needs jayi/pennantplus. Point `impex.atrium.features` at a subclass to
 * change the default, or at your own feature instead.
 *
 * Pennant stores a class-based feature under its class name. The class moved
 * from `JayI\Impex\Features`, so it keeps that name for its stored values;
 * a subclass is stored under its own class name, as before.
 */
#[Name('JayI\Impex\Features\ImpexSupportFeature')]
class ImpexSupportFeature extends OnLayeredFeature {}
