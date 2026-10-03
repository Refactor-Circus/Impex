<?php

declare(strict_types=1);

use JayI\Impex\Atrium\Features\ImpexSupportFeature;
use JayI\Impex\Domains\Artifact\Models\ArtifactModel;
use JayI\Impex\Domains\Artifact\Policies\ArtifactPolicy;
use JayI\Impex\Domains\Batch\Models\BatchItemModel;
use JayI\Impex\Domains\Batch\Models\BatchModel;
use JayI\Impex\Domains\Batch\Policies\BatchItemPolicy;
use JayI\Impex\Domains\Batch\Policies\BatchPolicy;
use JayI\Impex\Domains\Flow\Models\FlowOverrideModel;
use JayI\Impex\Domains\Flow\Policies\FlowOverridePolicy;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Message\Policies\MessagePolicy;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunOwnerModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Policies\RunOwnerPolicy;
use JayI\Impex\Domains\Run\Policies\RunPolicy;
use JayI\Impex\Domains\Run\Policies\RunStepPolicy;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Domains\Signal\Models\TimerModel;
use JayI\Impex\Domains\Signal\Policies\SignalPolicy;
use JayI\Impex\Domains\Signal\Policies\TimerPolicy;

return [

    /*
    |--------------------------------------------------------------------------
    | Flows
    |--------------------------------------------------------------------------
    |
    | The flows this application can run, keyed by slug. Code is the source of
    | truth for which flows exist; the `impex_flows` table only holds runtime
    | overrides, so a flow can be paused or rescheduled from the dashboard
    | without a deploy. String keys set the slug; unkeyed entries derive
    | one from the class. Flows may also be registered at runtime via
    | Impex::flows()->register($slug, $class).
    |
    */

    'flows' => [
        // 'extract-products' => \App\Flows\ExtractProductsFlow::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | Flows to run on a cron expression, keyed by slug. A schedule stored on the
    | flow's override row takes precedence over the value here.
    |
    */

    'schedule' => [
        // 'extract-products' => '0 * * * *',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Where the engine's jobs are dispatched. Leave null to use the application
    | default. Jobs carry identifiers only, never payloads, so a message can
    | never approach SQS's 256KB limit however large a run's data is.
    |
    */

    'queue' => [
        'connection' => null,
        'queue' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | `max_step_seconds` should stay below the queue worker's timeout, which on
    | Vapor is capped at Lambda's 900-second ceiling. `lease_seconds` is how
    | long a claimed step is owned before another invocation may reclaim it:
    | set it above the longest step, or a slow step will be run twice.
    | `resume_margin_seconds` is the headroom before `max_step_seconds` at
    | which `shouldYield()` flips, and `max_resumptions` is how many times
    | one step may checkpoint before failing. `fan_out_max` bounds per-item
    | fan-out, because replay is O(history) per drive — use batch() above
    | it. `sync_seconds` caps how long a trigger may block when a caller
    | asks to wait for a result.
    |
    */

    'limits' => [
        'max_step_seconds' => 840,
        'resume_margin_seconds' => 30,
        'lease_seconds' => 900,
        'lock_seconds' => 120,
        'max_resumptions' => 10000,
        'fan_out_max' => 100,
        'sync_seconds' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deadlines
    |--------------------------------------------------------------------------
    |
    | Default deadlines in seconds, applied when a run or step does not set its
    | own. Null means no deadline. Enforcement happens in `impex:tick`, not
    | in-process: a step that has handed control to an upstream call cannot
    | check a clock, and a killed invocation never gets the chance. A run
    | that passes its deadline roll backs; a step that passes its own
    | fails and unwinds the run like any other failure.
    |
    */

    'deadlines' => [
        'run' => null,
        'step' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Artifacts
    |--------------------------------------------------------------------------
    |
    | Any payload or result serializing above the inline threshold is written to
    | this disk and referenced by id, keeping both the queue message and the
    | database row small. Point `disk` at S3 on Vapor; Lambda has no
    | persistent local filesystem.
    |
    */

    'artifacts' => [
        'disk' => null,
        'path' => 'impex',
        'inline_threshold' => 65536,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The store backing run locks. Lambda shares no memory between invocations,
    | so this must be a shared store — DynamoDB or Redis on Vapor. Leave null
    | to use the application default.
    |
    */

    'cache' => [
        'store' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Timers
    |--------------------------------------------------------------------------
    |
    | SQS caps message delay at 15 minutes, so a workflow that waits longer than
    | `max_queue_delay` cannot be expressed as a delayed job. Longer waits are
    | written to `impex_timers` and swept by `impex:tick`. `claim_seconds`
    | is the sweep's lease: a timer claimed but never dispatched becomes
    | claimable again after it, rather than stranding forever. Set `enabled`
    | to false only if you schedule `impex:tick` yourself.
    |
    */

    'timers' => [
        'enabled' => true,
        'max_queue_delay' => 900,
        'claim_seconds' => 300,
        'batch' => 250,
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    |
    | How much of a body is kept inline for the dashboard's list view. The full
    | body always lives on the artifact disk, so this is display only.
    |
    */

    'messages' => [
        'preview_bytes' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Artifacts must never expire before the rows referencing them, or a failed
    | run loses the payloads you would open it to read. Keep this value at or
    | above the longest of the others.
    |
    */

    'retention' => [
        'completed_runs_days' => 90,
        'failed_runs_days' => 365,
        'messages_days' => 90,
        'artifacts_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | When on, every call acts as the authenticated user: they list only the
    | runs they own (and those runs' messages), own the runs they start, and
    | every call is checked against the policies below. Turn it off only for
    | a trusted operator surface, where the route middleware is the only
    | check. The inbound channel endpoints are never user-authorized — they
    | authenticate with the channel's signature.
    |
    */

    'authorization' => true,

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | The policy the Gate uses for each model. With authorization on, the
    | JSON API and MCP tools check every call against these. By default a
    | run's owners may do anything with it and everyone else is denied;
    | steps, owners, signals, timers, batches, messages and artifacts follow
    | their run through the Gate. Point a model at your own class to replace
    | its policy.
    |
    */

    'policies' => [
        RunModel::class => RunPolicy::class,
        RunStepModel::class => RunStepPolicy::class,
        RunOwnerModel::class => RunOwnerPolicy::class,
        SignalModel::class => SignalPolicy::class,
        TimerModel::class => TimerPolicy::class,
        BatchModel::class => BatchPolicy::class,
        BatchItemModel::class => BatchItemPolicy::class,
        MessageModel::class => MessagePolicy::class,
        ArtifactModel::class => ArtifactPolicy::class,
        FlowOverrideModel::class => FlowOverridePolicy::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API Routes
    |--------------------------------------------------------------------------
    |
    | The prefix and middleware applied to the Impex API routes. Add
    | authentication middleware before exposing these in production —
    | they trigger and cancel workflows and expose the ledger of every
    | payload that has crossed the application boundary.
    |
    | `channel_middleware` is the separate stack for the inbound channel
    | receive endpoints. Those authenticate per request with the channel's
    | signing secret rather than with an operator token, so the operator
    | middleware above would lock out the upstreams they exist to receive.
    | Add a throttle of your own: it is an unauthenticated-by-token surface,
    | and only the application knows what limiter and budget it wants.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'impex',
        'middleware' => ['api'],
        'channel_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | Impex exposes the same operations over MCP as over HTTP: both surfaces
    | call one Action, so they cannot drift. Both transports ship disabled.
    | When enabling the web transport, add auth middleware — the server
    | triggers and cancels workflows and reads every recorded payload.
    |
    */

    'mcp' => [
        'web' => [
            'enabled' => false,
            'route' => 'mcp/impex',
            'middleware' => [],
        ],
        'local' => [
            'enabled' => false,
            'handle' => 'impex',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cortex
    |--------------------------------------------------------------------------
    |
    | When jayi/cortex is installed, the MCP server is registered with it, so
    | its instructions can be overridden, and the tools join its registry,
    | so Cortex agents can run and inspect workflows. Set `tools` to a list
    | of tool names, such as ['list-runs-tool', 'show-run-tool'], to offer
    | only some of them.
    |
    */

    'cortex' => [
        'enabled' => true,
        'server' => 'impex',
        'tools' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard UI
    |--------------------------------------------------------------------------
    |
    | Impex renders its dashboard through Atrium, which owns the path,
    | middleware and authorization gate. Gate it carefully: the dashboard
    | exposes every payload that has crossed the application boundary.
    |
    */

    'ui' => [

        /*
        | Whether Impex registers itself with the Atrium dashboard. The JSON
        | API is unaffected by this switch.
        */

        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Atrium Features
    |--------------------------------------------------------------------------
    |
    | Features that switch Impex in Atrium on and off as a whole: while any is
    | off its navigation, widgets, settings and search are hidden and its
    | pages answer 404. With jayi/pennantplus installed, ImpexSupportFeature
    | is on until its global value is set; per-user values are ignored, so
    | who sees what stays with the policies above. Without PennantPlus the
    | class is skipped and nothing is checked. Name a subclass, or your own
    | feature, to change it; an empty list turns the switch off.
    |
    */

    'atrium' => [
        'features' => [
            ImpexSupportFeature::class,
        ],

        /*
        | Operators on the dashboard. With authorization on, the screens show
        | each user only the runs they own, so runs with no owner — scheduled
        | and channel runs — appear to nobody. An operator sees every run,
        | message, count, widget and search result, and may use every Impex
        | control on them, on the Atrium screens only: the JSON API and MCP
        | tools still apply the policies. false: nobody is an operator. true:
        | everyone past Atrium's gate is. A string: the name of a Gate
        | ability, and those it allows are, e.g. 'impex-operator'.
        */

        'show_all' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    |
    | Named boundary configurations. Each inbound channel validates a signature,
    | applies a profile, records the request as a Message, and dispatches the
    | flow bound to it.
    |
    */

    'channels' => [
        // 'supplier-feed' => [
        //     'direction' => 'inbound',
        //     'signing_secret' => env('IMPEX_SUPPLIER_SECRET'),
        //     'signature_header' => 'X-Signature',
        //     'flow' => 'extract-products',
        //     'store_headers' => ['content-type'],
        // ],
    ],

];
