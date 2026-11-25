<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Data;

use Illuminate\Database\Eloquent\Collection;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * The run and message figures a dashboard shows.
 */
final readonly class RunStats
{
    /**
     * @param  array<string, int>  $byStatus  Runs per status value, every status present.
     * @param  int  $active  Runs that may still be driven or signalled.
     * @param  Collection<int, RunModel>  $recentFailures  The latest failed runs, newest first.
     * @param  array{window_hours: int, inbound: int, outbound: int}|null  $messages  Messages per direction within the window, or null when not counted.
     */
    public function __construct(
        public array $byStatus,
        public int $active,
        public Collection $recentFailures,
        public ?array $messages = null,
    ) {}
}
