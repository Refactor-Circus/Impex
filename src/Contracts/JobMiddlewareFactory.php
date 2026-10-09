<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Contracts;

/**
 * Builds queue middleware for one job, for middleware that needs something
 * from the job itself — a lock keyed by the subscription a delivery is for,
 * say. Name the factory in `impex.jobs.middleware` like any middleware.
 */
interface JobMiddlewareFactory
{
    /**
     * @return array<int, object>
     */
    public function middleware(object $job): array;
}
