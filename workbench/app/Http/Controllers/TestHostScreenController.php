<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View as Views;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;

/**
 * Another package's list screen, as the tests need one: `GET test-host/{key}`
 * declares `Screen::standard($key, Screen::LIST)`, resolves its extensions
 * and renders the tab bar, the open registered tab's panel, the
 * `header.actions`, `before` and `after` sections, and a table whose rows each
 * render their `row.actions` sections.
 *
 * Tests key it `showroom.transfers`, so Impex's run link is exercised as it
 * would be on Showroom's transfers page, without Showroom installed. The rows
 * are whatever models a test puts in the `test-host.rows` config key.
 * Extensions' `permission()`s are asked of the Gate, with the row (or no
 * record) as the subject.
 *
 * It names Atrium classes, so the WithoutAtrium suite never loads it.
 */
final class TestHostScreenController
{
    public function __invoke(Request $request, ScreenExtensions $extensions, string $key): View
    {
        $screen = Screen::standard($key, Screen::LIST)
            ->access(fn (string $permission, Request $request, ?Model $record): bool => Gate::forUser($request->user())
                ->allows($permission, $record === null ? [] : [$record]));

        /** @var list<Model> $rows */
        $rows = config('test-host.rows', []);

        // Testbench does not discover the workbench's views in feature tests.
        Views::replaceNamespace('workbench', dirname(__DIR__, 3).'/resources/views');

        return view('workbench::test-host', [
            'resolved' => $extensions->resolve($screen, $request),
            'rows' => $rows,
        ]);
    }
}
