<?php

declare(strict_types=1);

namespace JayI\Impex\Http\Ui;

use Illuminate\Database\Eloquent\Model;
use JayI\Impex\Access\Authorizer;

/**
 * Whether the signed-in user may perform an ability, asked exactly as the
 * JSON API and MCP tools ask it. Controllers refuse with it and views hide
 * controls with it (as `@impexCan`), so a control is shown only when its
 * action would be allowed. With `impex.authorization` off everything is.
 */
final class ScreenAccess
{
    /**
     * @param  Model|class-string<Model>  $subject
     * @param  array<int, mixed>  $arguments
     */
    public static function allows(string $ability, Model|string $subject, array $arguments = []): bool
    {
        return self::authorizer()->can(request()->user(), $ability, $subject, $arguments);
    }

    /**
     * Whether the signed-in user may use the screens at all, for those such
     * as channels that no policy covers.
     */
    public static function signedIn(): bool
    {
        return self::authorizer()->authenticated(request()->user());
    }

    /**
     * The user lists are limited to, or null when authorization is off.
     */
    public static function actor(): ?Model
    {
        return self::authorizer()->actor(request()->user());
    }

    private static function authorizer(): Authorizer
    {
        return app(Authorizer::class);
    }
}
