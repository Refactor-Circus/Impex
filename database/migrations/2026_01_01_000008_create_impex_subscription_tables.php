<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions: who wants which changes from which stream, and the narrow
 * tables that carry a change from the moment a package touches a subject to
 * the moment a subscriber has it.
 *
 * Built for catalogues in the tens of millions. Nothing here grows with
 * catalogue size times subscriber count: a change is one event row, and
 * each subscriber it matters to adds one two-column row pointing at it.
 * Payloads are built when they are sent, never copied per subscriber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impex_subscribers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            // The OAuth client the subscriber authenticates as.
            $table->string('client_id', 191)->nullable()->unique();
            $table->string('status', 16)->default('active');
            $table->string('owner_type', 191)->nullable();
            $table->string('owner_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('impex_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscriber_id')->constrained('impex_subscribers')->cascadeOnDelete();
            $table->string('stream', 191);
            // Null for a feed-only subscription, which is pulled, not pushed.
            $table->foreignUlid('channel_id')->nullable()->constrained('impex_channels')->nullOnDelete();
            $table->unsignedInteger('topics');
            $table->string('selection', 16)->default('all');
            $table->json('filter')->nullable();
            $table->string('format', 32)->default('slice');
            $table->json('options')->nullable();
            $table->string('status', 16)->default('active');
            // The last event delivered. Delivery reads forward from here.
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->timestamp('pending_at')->nullable();
            $table->timestamp('paused_until')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->json('last_error')->nullable();
            $table->string('last_export_path', 1024)->nullable();
            $table->timestamp('last_export_at')->nullable();
            $table->timestamps();

            $table->index(['stream', 'status']);
            $table->index(['status', 'pending_at']);
        });

        Schema::create('impex_subscription_subjects', function (Blueprint $table): void {
            $table->foreignUlid('subscription_id')->constrained('impex_subscriptions')->cascadeOnDelete();
            $table->string('subject_key', 191);

            $table->primary(['subscription_id', 'subject_key']);
            $table->index('subject_key');
        });

        // Subjects a package has said changed, not yet looked at. One row per
        // subject however often it is touched: a burst of saves is one look.
        Schema::create('impex_stream_touches', function (Blueprint $table): void {
            $table->string('stream', 191);
            $table->string('subject_key', 191);
            // New on every touch. The detector removes a row only if it was
            // not touched again while being looked at.
            $table->unsignedBigInteger('revision');
            $table->unsignedBigInteger('claimed_revision')->nullable();
            $table->string('lease_token', 26)->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamp('touched_at');

            $table->primary(['stream', 'subject_key']);
            $table->index(['stream', 'leased_until']);
            $table->index('lease_token');
        });

        // What each subject looked like when last compared, as one hash per
        // topic. A save that changes nothing a subscriber can see sends
        // nothing.
        Schema::create('impex_subject_states', function (Blueprint $table): void {
            $table->string('stream', 191);
            $table->string('subject_key', 191);
            // One 16-hex-character xxh3 per topic, in topic order.
            $table->text('hashes');
            $table->timestamp('updated_at');

            $table->primary(['stream', 'subject_key']);
        });

        // The sequence. Its id is every subscriber's cursor.
        Schema::create('impex_events', function (Blueprint $table): void {
            $table->id();
            $table->string('stream', 191);
            $table->string('subject_key', 191);
            $table->string('kind', 16);
            $table->string('name', 191)->nullable();
            $table->unsignedInteger('topics');
            $table->json('payload')->nullable();
            // Ties a bulk insert's rows back to the job that wrote them.
            $table->string('batch', 26)->nullable();
            $table->timestamp('fanned_out_at')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['stream', 'subject_key']);
            $table->index(['stream', 'fanned_out_at']);
            $table->index('batch');
            $table->index('occurred_at');
        });

        Schema::create('impex_subscription_events', function (Blueprint $table): void {
            $table->ulid('subscription_id');
            $table->unsignedBigInteger('event_id');

            $table->primary(['subscription_id', 'event_id']);
            $table->index('event_id');
        });

        Schema::create('impex_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->constrained('impex_subscriptions')->cascadeOnDelete();
            $table->unsignedBigInteger('first_event_id');
            $table->unsignedBigInteger('last_event_id');
            $table->unsignedInteger('events');
            $table->string('status', 16);
            $table->ulid('message_id')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('error')->nullable();
            $table->timestamp('attempted_at');

            $table->index(['subscription_id', 'attempted_at']);
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impex_deliveries');
        Schema::dropIfExists('impex_subscription_events');
        Schema::dropIfExists('impex_events');
        Schema::dropIfExists('impex_subject_states');
        Schema::dropIfExists('impex_stream_touches');
        Schema::dropIfExists('impex_subscription_subjects');
        Schema::dropIfExists('impex_subscriptions');
        Schema::dropIfExists('impex_subscribers');
    }
};
