<?php

declare(strict_types=1);

namespace JayI\Impex\Atrium;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use JayI\Atrium\Support\ScreenAccess as AtriumScreenAccess;
use JayI\Foundation\Auth\Authorizer;
use JayI\Foundation\Packages\PackageRegistry;

/**
 * Whether the signed-in user may perform an ability, asked exactly as the
 * JSON API and MCP tools ask it. Controllers refuse with it and views hide
 * controls with it (as `@impexCan`), so a control is shown only when its
 * action would be allowed. With `impex.authorization` off everything is.
 *
 * An operator (`impex.atrium.show_all`) is the one difference from the API:
 * on these screens only, they see every run and may act on all of Impex's
 * models, owned or not. The JSON API and MCP tools never ask this class.
 */
final class ScreenAccess
{
    /**
     * Asked through Atrium's shared `ScreenAccess::allows('impex', ...)`,
     * after letting an operator through on Impex's own models. A check that
     * needs extra policy arguments (such as the run a step belongs to) goes
     * to the authorizer directly, which Atrium's helper does not take.
     *
     * @param  Model|class-string<Model>  $subject
     * @param  array<int, mixed>  $arguments
     */
    public static function allows(string $ability, Model|string $subject, array $arguments = [], ?Request $request = null): bool
    {
        $request ??= request();
        $user = $request->user();

        if (self::isOperator($user instanceof Authenticatable ? $user : null) && self::impexModel($subject)) {
            return true;
        }

        if ($arguments === []) {
            return AtriumScreenAccess::allows('impex', $ability, $subject, $request);
        }

        return self::authorizer()->can($user instanceof Authenticatable ? $user : null, $ability, $subject, $arguments);
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
     * The user the screens act as — who owns a run started from them — or
     * null when authorization is off.
     */
    public static function actor(): ?Model
    {
        return self::authorizer()->actor(request()->user());
    }

    /**
     * The user lists, counts, widgets and search are limited to: null when
     * authorization is off or the user is an operator, who sees every run.
     */
    public static function viewer(): ?Model
    {
        return self::operator() ? null : self::actor();
    }

    /**
     * Whether the signed-in user is an operator on the dashboard.
     */
    public static function operator(): bool
    {
        return self::isOperator(request()->user());
    }

    /**
     * `impex.atrium.show_all`: false (nobody), true (everyone signed in to
     * Atrium), or the name of a Gate ability the user must be allowed.
     * Only meaningful while authorization is on; with it off nothing is
     * scoped or refused anyway.
     */
    public static function isOperator(?Authenticatable $user): bool
    {
        if (! self::authorizer()->enabled() || ! $user instanceof Model) {
            return false;
        }

        $showAll = config('impex.atrium.show_all', false);

        if ($showAll === true) {
            return true;
        }

        return is_string($showAll) && $showAll !== '' && app(Gate::class)->forUser($user)->allows($showAll);
    }

    /**
     * Operators are let through only on Impex's own models.
     *
     * @param  Model|class-string<Model>  $subject
     */
    private static function impexModel(Model|string $subject): bool
    {
        $class = is_string($subject) ? $subject : $subject::class;

        // Each domain keeps its models in its own Models namespace.
        return preg_match('/^JayI\\\\Impex\\\\Domains\\\\\\w+\\\\Models\\\\/', ltrim($class, '\\')) === 1;
    }

    private static function authorizer(): Authorizer
    {
        return Authorizer::for(app(PackageRegistry::class)->get('impex'));
    }
}
