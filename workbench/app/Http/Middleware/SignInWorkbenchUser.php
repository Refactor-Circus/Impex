<?php

declare(strict_types=1);

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Orchestra\Workbench\Workbench;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs testbench.yaml's `workbench.user` in on any request a guest makes.
 * Testbench only does that at `/`, so opening a dashboard URL directly, or
 * keeping a session from before a rebuild, otherwise left you a guest and
 * hid the screens that need a signed-in user.
 */
class SignInWorkbenchUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = Workbench::config('user');

        if (! app()->runningUnitTests() && Auth::guest() && is_string($email) && $email !== '') {
            $user = Auth::getProvider()->retrieveByCredentials(['email' => $email]);

            if ($user !== null) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
