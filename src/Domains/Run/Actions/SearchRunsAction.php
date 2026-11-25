<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Run\Data\SearchRunsData;
use RefactorCircus\Impex\Domains\Run\Events\RunsSearchedActionEvent;
use RefactorCircus\Impex\Domains\Run\Events\RunsSearchingActionEvent;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;

/**
 * Runs whose flow contains the query or whose id starts with it, newest
 * first. It only reads, so there is no transaction.
 */
final class SearchRunsAction
{
    /**
     * The LIKE escape character. Not a backslash: MySQL reads one inside a
     * string literal as an escape of its own, so it is not portable in SQL.
     */
    private const string ESCAPE = '!';

    /**
     * @param  Model|null  $viewer  When given, only the runs they own.
     * @return Collection<int, RunModel>
     */
    public function execute(SearchRunsData $data, ?Model $viewer = null): Collection
    {
        RunsSearchingActionEvent::dispatch($data, $viewer);

        $result = $this->perform($data, $viewer);

        RunsSearchedActionEvent::dispatch($result, $viewer);

        return $result;
    }

    /**
     * @return Collection<int, RunModel>
     */
    private function perform(SearchRunsData $data, ?Model $viewer): Collection
    {
        $query = RunModel::query();

        // Escaped, so a `%` or `_` someone types is a character to find, not
        // a wildcard that widens the match.
        $term = $this->escape($data->q);

        // Run ids are lowercase ULIDs, so the prefix is compared in lowercase.
        $prefix = $this->escape(mb_strtolower($data->q));

        $query->where(function (Builder $match) use ($term, $prefix): void {
            $match->whereRaw('flow like ? escape \''.self::ESCAPE.'\'', ['%'.$term.'%'])
                ->orWhereRaw('id like ? escape \''.self::ESCAPE.'\'', [$prefix.'%']);
        });

        if ($viewer !== null) {
            $query->whereOwnedBy($viewer);
        }

        // Newest first, with the id breaking ties between runs started in the
        // same second, so the order is stable.
        return $query->latest('created_at')
            ->latest('id')
            ->limit($data->limit)
            ->get();
    }

    /**
     * The value with the LIKE wildcards and the escape character escaped.
     */
    private function escape(string $value): string
    {
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $value,
        );
    }
}
