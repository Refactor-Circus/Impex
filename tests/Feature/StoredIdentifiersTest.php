<?php

declare(strict_types=1);

use RefactorCircus\Impex\Jobs\DriveRun;

/*
 * Values the queue holds by class name must keep resolving, so queued jobs
 * stay where they are.
 */

it('keeps the queued job class names, so jobs already on the queue still run', function (): void {
    // A DriveRun as it sits on the queue.
    $job = unserialize('O:34:"RefactorCircus\\Impex\\Jobs\\DriveRun":1:{s:5:"runId";s:3:"abc";}');

    expect($job)->toBeInstanceOf(DriveRun::class)
        ->and($job->runId)->toBe('abc');
});
