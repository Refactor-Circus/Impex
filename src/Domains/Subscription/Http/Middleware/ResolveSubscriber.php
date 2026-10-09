<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Subscription\Contracts\ResolvesSubscriber;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the subscriber a request authenticated as, for the subscriber API.
 * A request the resolver cannot place gets nothing.
 */
final class ResolveSubscriber
{
    public const string ATTRIBUTE = 'impex.subscriber';

    public function __construct(private readonly ResolvesSubscriber $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $subscriber = $this->resolver->resolve($request);

        if ($subscriber === null) {
            return new JsonResponse(['message' => 'No active subscriber is registered for these credentials.'], 403);
        }

        $request->attributes->set(self::ATTRIBUTE, $subscriber);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
