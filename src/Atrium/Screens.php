<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Atrium;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;

/**
 * Impex's own screens as they declare themselves to Atrium's extension
 * registry: the standard slots (`header.actions`, `before`, `after`, plus
 * `aside` on a detail screen or `row.actions` on a list), the record, and an
 * `access()` that grants an extension's `permission()` exactly as Impex's
 * pages grant their own controls, operators included.
 *
 * Screens declare no audit subject: Keen attaches History to every screen.
 */
final class Screens
{
    /**
     * @param  string  $kind  Screen::DETAIL or Screen::LIST.
     * @param  Model|null  $record  The detail screen's record, if it has one.
     * @param  class-string<Model>|null  $subject  The model class a permission is asked of when there is no record (or row).
     */
    public static function declare(string $key, string $kind, ?Model $record, ?string $subject): Screen
    {
        return Screen::standard($key, $kind)
            ->record($record)
            ->access(function (string $permission, Request $request, ?Model $record) use ($subject): bool {
                $target = $record ?? $subject;

                return $target !== null && ScreenAccess::allows($permission, $target, [], $request);
            });
    }
}
