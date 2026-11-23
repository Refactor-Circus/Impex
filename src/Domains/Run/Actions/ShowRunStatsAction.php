<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Run\Data\RunStats;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Events\RunStatsShowingActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunStatsShownActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * Runs by status, the active count, the latest failures and the last day's
 * message volume. It only reads, so there is no transaction.
 */
final class ShowRunStatsAction
{
    /**
     * How many failed runs the stats list.
     */
    private const int RECENT_FAILURES = 5;

    /**
     * How far back the message counts look.
     */
    private const int MESSAGE_WINDOW_HOURS = 24;

    /**
     * @param  Model|null  $viewer  When given, only the runs they own and those runs' messages.
     * @param  bool  $withMessages  Whether to count messages at all.
     */
    public function execute(?Model $viewer, bool $withMessages): RunStats
    {
        RunStatsShowingActionEvent::dispatch($viewer, $withMessages);

        $result = $this->perform($viewer, $withMessages);

        RunStatsShownActionEvent::dispatch($result, $viewer);

        return $result;
    }

    private function perform(?Model $viewer, bool $withMessages): RunStats
    {
        return new RunStats(
            byStatus: $this->statusCounts($viewer),
            active: $this->runs($viewer)->active()->count(),
            recentFailures: $this->runs($viewer)
                ->where('status', RunStatus::Failed)
                ->latest('finished_at')
                ->latest('id')
                ->limit(self::RECENT_FAILURES)
                ->get(),
            messages: $withMessages ? $this->messageCounts($viewer) : null,
        );
    }

    /**
     * Runs by status, every status present, in one grouped count.
     *
     * @return array<string, int>
     */
    private function statusCounts(?Model $viewer): array
    {
        $counts = array_fill_keys(array_column(RunStatus::cases(), 'value'), 0);

        $rows = $this->runs($viewer)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            /** @var object{status: string, aggregate: int|string} $row */
            if (array_key_exists($row->status, $counts)) {
                $counts[$row->status] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /**
     * @return array{window_hours: int, inbound: int, outbound: int}
     */
    private function messageCounts(?Model $viewer): array
    {
        $since = Carbon::now()->subHours(self::MESSAGE_WINDOW_HOURS);

        return [
            'window_hours' => self::MESSAGE_WINDOW_HOURS,
            'inbound' => $this->messages($viewer, Direction::Inbound, $since)->count(),
            'outbound' => $this->messages($viewer, Direction::Outbound, $since)->count(),
        ];
    }

    /**
     * @return Builder<RunModel>
     */
    private function runs(?Model $viewer): Builder
    {
        $query = RunModel::query();

        if ($viewer !== null) {
            $query->whereOwnedBy($viewer);
        }

        return $query;
    }

    /**
     * Messages in one direction since the given moment, limited to those of
     * the viewer's runs as runs are.
     *
     * @return Builder<MessageModel>
     */
    private function messages(?Model $viewer, Direction $direction, Carbon $since): Builder
    {
        $query = MessageModel::query()
            ->where('direction', $direction)
            ->where('occurred_at', '>=', $since);

        if ($viewer !== null) {
            $query->whereHas('run', function (Builder $runs) use ($viewer): void {
                $runs->whereOwnedBy($viewer);
            });
        }

        return $query;
    }
}
