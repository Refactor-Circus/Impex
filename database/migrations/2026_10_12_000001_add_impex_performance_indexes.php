<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RefactorCircus\Impex\Support\Tables;

/**
 * Indexes for the queries the engine and the dashboard run most, and the
 * claim time that lets a stalled append-stream fan-out be taken back.
 */
return new class extends Migration
{
    public function up(): void
    {
        // When an append stream's events were tagged for fan-out. A tag older
        // than the detection lease belongs to a worker that died or failed,
        // and its events are claimable again.
        Schema::table(Tables::name('events'), function (Blueprint $table): void {
            $table->timestamp('claimed_at')->nullable();
        });

        // Detection claims the oldest touches of a stream first.
        Schema::table(Tables::name('stream_touches'), function (Blueprint $table): void {
            $table->index(['stream', 'touched_at'], Tables::name('stream_touches').'_stream_touched_at_index');
        });

        // The deadline sweep looks only at pending and running steps and runs,
        // not at every finished one that ever had a deadline.
        Schema::table(Tables::name('run_steps'), function (Blueprint $table): void {
            $table->index(['status', 'expires_at'], Tables::name('run_steps').'_status_expires_at_index');
        });

        Schema::table(Tables::name('runs'), function (Blueprint $table): void {
            $table->index(['status', 'expires_at'], Tables::name('runs').'_status_expires_at_index');
            // Run listings: newest first with the id breaking ties, overall
            // and for one flow.
            $table->index(['created_at', 'id'], Tables::name('runs').'_created_at_id_index');
            $table->index(['flow', 'created_at'], Tables::name('runs').'_flow_created_at_index');
        });
    }

    public function down(): void
    {
        // Each table is checked first: a rollback after `impex.tables` has
        // changed must not fail on a table that is not there.
        if (Schema::hasTable(Tables::name('runs'))) {
            Schema::table(Tables::name('runs'), function (Blueprint $table): void {
                $table->dropIndex(Tables::name('runs').'_flow_created_at_index');
                $table->dropIndex(Tables::name('runs').'_created_at_id_index');
                $table->dropIndex(Tables::name('runs').'_status_expires_at_index');
            });
        }

        if (Schema::hasTable(Tables::name('run_steps'))) {
            Schema::table(Tables::name('run_steps'), function (Blueprint $table): void {
                $table->dropIndex(Tables::name('run_steps').'_status_expires_at_index');
            });
        }

        if (Schema::hasTable(Tables::name('stream_touches'))) {
            Schema::table(Tables::name('stream_touches'), function (Blueprint $table): void {
                $table->dropIndex(Tables::name('stream_touches').'_stream_touched_at_index');
            });
        }

        if (Schema::hasTable(Tables::name('events'))) {
            Schema::table(Tables::name('events'), function (Blueprint $table): void {
                $table->dropColumn('claimed_at');
            });
        }
    }
};
