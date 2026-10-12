<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Support;

use RefactorCircus\Keystone\Database\Tables as KeystoneTables;

/**
 * The table each of Impex's records lives in, from the `impex.tables`
 * config key. Models, migrations, validation rules and the engine's own
 * claim, lease and fan-out queries all ask here, so renaming a table is one
 * config change made before the migrations run.
 *
 * A published config that lists only some tables keeps the defaults for the
 * rest.
 */
final class Tables extends KeystoneTables
{
    protected const string CONFIG = 'impex.tables';

    /**
     * @var array<string, string>
     */
    public const array DEFAULTS = [
        'artifacts' => 'impex_artifacts',
        'runs' => 'impex_runs',
        'run_steps' => 'impex_run_steps',
        'run_owners' => 'impex_run_owners',
        'signals' => 'impex_signals',
        'timers' => 'impex_timers',
        'messages' => 'impex_messages',
        'flows' => 'impex_flows',
        'batches' => 'impex_batches',
        'batch_items' => 'impex_batch_items',
        'channels' => 'impex_channels',
        'subscribers' => 'impex_subscribers',
        'subscriptions' => 'impex_subscriptions',
        'subscription_subjects' => 'impex_subscription_subjects',
        'stream_touches' => 'impex_stream_touches',
        'subject_states' => 'impex_subject_states',
        'events' => 'impex_events',
        'subscription_events' => 'impex_subscription_events',
        'subscription_holdings' => 'impex_subscription_holdings',
        'deliveries' => 'impex_deliveries',
    ];
}
