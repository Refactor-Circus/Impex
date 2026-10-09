<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Transports;

use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RefactorCircus\Impex\Domains\Channel\Contracts\Transport;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Data\OutboundMessage;
use RefactorCircus\Impex\Domains\Channel\Data\Receipt;
use RefactorCircus\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Services\MessageRecorder;
use Throwable;

/**
 * Writes to a disk: a drop folder, an SFTP or S3 bucket a partner reads.
 *
 * Options: `disk` (the default disk when unset) and `path`, which may use
 * {id}, {date} and {time}. A message's endpoint, when set, is the path.
 */
final class FileTransport implements Transport
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly MessageRecorder $recorder,
    ) {}

    public function send(ChannelConfig $channel, OutboundMessage $message): Receipt
    {
        $disk = is_string($channel->option('disk')) ? $channel->option('disk') : null;
        $template = $message->endpoint ?? (is_string($channel->option('path')) ? $channel->option('path') : null)
            ?? throw ChannelUnavailableException::missingOption($channel->name, 'path');
        $path = $this->path($template, $message->id ?? (string) Str::ulid());
        $startedAt = microtime(true);
        $error = null;

        try {
            $storage = $this->filesystem->disk($disk);
            $written = $message->stream !== null
                ? $storage->writeStream($path, $message->stream)
                : $storage->put($path, (string) $message->body);

            if ($written === false) {
                $error = ['message' => sprintf('The disk refused the write to [%s].', $path)];
            }
        } catch (Throwable $e) {
            $error = ['message' => $e->getMessage(), 'class' => $e::class];
        }

        $recorded = $this->recorder->record(
            direction: Direction::Outbound,
            channel: $channel->name,
            transport: 'file',
            endpoint: sprintf('%s://%s', $disk ?? 'default', $path),
            body: $message->stream === null ? $message->body : null,
            runId: $message->link('run_id'),
            stepId: $message->link('step_id'),
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            error: $error,
            deliveryId: $message->link('delivery_id'),
        );

        return new Receipt(
            successful: $error === null,
            messageId: (string) $recorded->getKey(),
            durationMs: $recorded->duration_ms,
            error: $error,
        );
    }

    private function path(string $template, string $id): string
    {
        $now = Carbon::now();

        return strtr($template, [
            '{id}' => $id,
            '{date}' => $now->format('Y-m-d'),
            '{time}' => $now->format('His'),
        ]);
    }
}
