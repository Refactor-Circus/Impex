<?php

declare(strict_types=1);

namespace JayI\Impex\Http\Ui\Concerns;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Http\Ui\ScreenAccess;

/**
 * The same checks the JSON API and MCP tools make, for the Atrium screens.
 */
trait AuthorizesScreens
{
    /**
     * @param  Model|class-string<Model>  $subject
     * @param  array<int, mixed>  $arguments
     */
    private function authorizeScreen(string $ability, Model|string $subject, array $arguments = []): void
    {
        abort_unless(ScreenAccess::allows($ability, $subject, $arguments), 403);
    }
}
