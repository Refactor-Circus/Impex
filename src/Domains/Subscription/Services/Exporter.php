<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RuntimeException;

/**
 * Writes everything a subscription covers to one file, so a new subscriber
 * starts from a download instead of from millions of webhooks.
 *
 * The cursor moves to where the stream stood when the export began: events
 * up to there are in the file, and the ones after it follow as usual. A
 * subject changed during the export may arrive twice, once in the file and
 * once as an event, which a subscriber taking state rather than diffs never
 * notices.
 */
final class Exporter
{
    public function __construct(
        private readonly StreamRegistry $streams,
        private readonly Filesystem $filesystem,
        private readonly ConnectionInterface $db,
        private readonly Config $config,
    ) {}

    public function export(string $subscriptionId): ?string
    {
        $subscription = SubscriptionModel::query()->find($subscriptionId);

        if (! $subscription instanceof SubscriptionModel) {
            return null;
        }

        $stream = $this->streams->get($subscription->stream);
        $start = (int) $this->db->table('impex_events')->max('id');

        $handle = tmpfile();

        if ($handle === false) {
            throw new RuntimeException('Impex could not open a temporary file for the export.');
        }

        try {
            $stream->export($subscription, $handle);
            rewind($handle);

            $path = sprintf('%s/%s/%s.jsonl', trim($this->prefix(), '/'), $subscription->id, strtolower((string) Str::ulid()));
            $this->filesystem->disk($this->disk())->writeStream($path, $handle);
        } finally {
            fclose($handle);
        }

        $this->db->table('impex_subscriptions')->where('id', $subscription->id)->update([
            'last_export_path' => $path,
            'last_export_at' => Carbon::now(),
            'cursor' => max($subscription->cursor, $start),
        ]);

        return $path;
    }

    public function disk(): ?string
    {
        $disk = $this->config->get('impex.subscriptions.exports.disk');

        return is_string($disk) ? $disk : null;
    }

    private function prefix(): string
    {
        $prefix = $this->config->get('impex.subscriptions.exports.path', 'impex/exports');

        return is_string($prefix) ? $prefix : 'impex/exports';
    }
}
