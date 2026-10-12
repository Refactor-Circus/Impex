<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Domains\Subscription\Services\Holdings;
use RefactorCircus\Impex\Support\Tables;

/**
 * Which subjects each filtered or listed subscription currently has, so
 * fan-out can tell who a subject reached or left from the keys in hand
 * instead of reading their whole event history.
 *
 * Existing subscriptions are filled from the events they were sent, which
 * covers the retention window: the same answer the history gave before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::name('subscription_holdings'), function (Blueprint $table): void {
            $table->foreignUlid('subscription_id')->constrained(Tables::name('subscriptions'))->cascadeOnDelete();
            $table->string('subject_key', 191);

            $table->primary(['subscription_id', 'subject_key']);
        });

        $holdings = app(Holdings::class);

        DB::table(Tables::name('subscriptions'))
            ->where('selection', '!=', Selection::All->value)
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (mixed $id) => $holdings->rebuild((string) $id));
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('subscription_holdings'));
    }
};
