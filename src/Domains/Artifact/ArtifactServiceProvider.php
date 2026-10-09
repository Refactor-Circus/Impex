<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Artifact;

use RefactorCircus\Impex\Domains\Artifact\Services\PayloadStore;
use RefactorCircus\Keystone\Support\ServiceProvider;

class ArtifactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayloadStore::class);
    }
}
