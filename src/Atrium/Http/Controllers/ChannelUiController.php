<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RefactorCircus\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens;
use RefactorCircus\Impex\Atrium\ScreenAccess;
use RefactorCircus\Impex\Domains\Channel\Actions\CreateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Actions\DeleteChannelAction;
use RefactorCircus\Impex\Domains\Channel\Actions\ListChannelsAction;
use RefactorCircus\Impex\Domains\Channel\Actions\RotateChannelSecretAction;
use RefactorCircus\Impex\Domains\Channel\Actions\ShowChannelAction;
use RefactorCircus\Impex\Domains\Channel\Actions\UpdateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Enums\ChannelStatus;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Services\TransportManager;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;

/**
 * The channels screens: every channel, configured or stored, and creating,
 * changing, rotating and deleting the stored ones — an integration added or a
 * leaked secret rotated without a deploy.
 */
final class ChannelUiController
{
    use AuthorizesScreens;

    public function index(): View
    {
        $this->authorizeScreen('viewAny', ChannelModel::class);

        /** @var view-string $view */
        $view = 'impex::ui.channels.index';

        return view($view, ['channels' => app(ListChannelsAction::class)->execute([], ScreenAccess::viewer())]);
    }

    public function create(): View
    {
        $this->authorizeScreen('create', ChannelModel::class);

        /** @var view-string $view */
        $view = 'impex::ui.channels.create';

        return view($view, $this->choices());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeScreen('create', ChannelModel::class);

        $data = $this->validated($request, CreateChannelAction::rules());

        $channel = app(CreateChannelAction::class)->execute($data, ScreenAccess::actor());

        return redirect()
            ->route('atrium.impex.channels.show', $channel->name)
            ->with('status', __('impex::impex.channel_created'));
    }

    public function show(string $name): View
    {
        $stored = ChannelModel::query()->where('name', $name)->first();

        $stored instanceof ChannelModel
            ? $this->authorizeScreen('view', $stored)
            : $this->authorizeScreen('viewAny', ChannelModel::class);

        /** @var view-string $view */
        $view = 'impex::ui.channels.show';

        return view($view, [
            'channel' => app(ShowChannelAction::class)->execute($name),
            'stored' => $stored,
            ...$this->choices(),
        ]);
    }

    public function update(Request $request, ChannelModel $channel): RedirectResponse
    {
        $this->authorizeScreen('update', $channel);

        $data = $this->validated($request, UpdateChannelAction::rules());

        app(UpdateChannelAction::class)->execute($channel, $data);

        return redirect()
            ->route('atrium.impex.channels.show', $channel->name)
            ->with('status', __('impex::impex.channel_updated'));
    }

    public function rotateSecret(ChannelModel $channel): RedirectResponse
    {
        $this->authorizeScreen('update', $channel);

        $rotated = app(RotateChannelSecretAction::class)->execute($channel);

        // Shown once, in the flash, and never again.
        return redirect()
            ->route('atrium.impex.channels.show', $channel->name)
            ->with('status', __('impex::impex.secret_rotated', ['secret' => $rotated['secret']]));
    }

    public function destroy(ChannelModel $channel): RedirectResponse
    {
        $this->authorizeScreen('delete', $channel);

        app(DeleteChannelAction::class)->execute($channel);

        return redirect()
            ->route('atrium.impex.channels.index')
            ->with('status', __('impex::impex.channel_deleted'));
    }

    /**
     * The form's fields as the action takes them: options arrive as JSON,
     * the signing secret on its own.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validated(Request $request, array $rules): array
    {
        $options = $request->input('options_json');
        $decoded = is_string($options) && trim($options) !== '' ? json_decode($options, true) : [];

        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['options_json' => __('impex::impex.options_invalid')]);
        }

        $secret = $request->input('signing_secret');

        $request->merge(['options' => $decoded]);

        // Left out unless a secret was typed, so a blank field keeps the
        // channel's secret rather than wiping it.
        if (is_string($secret) && $secret !== '') {
            $request->merge(['credentials' => ['signing_secret' => $secret]]);
        }

        /** @var array<string, mixed> $data */
        $data = $request->validate($rules);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function choices(): array
    {
        return [
            'directions' => Direction::cases(),
            'statuses' => ChannelStatus::cases(),
            'policies' => BodyPolicy::cases(),
            'transports' => array_values(array_filter(['http', 'mail', 'file'], fn (string $t): bool => app(TransportManager::class)->has($t))),
        ];
    }
}
