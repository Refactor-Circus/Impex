<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Artifact;

use RefactorCircus\Foundation\Support\ServiceProvider;
use RefactorCircus\Impex\Domains\Artifact\Services\PayloadStore;

class ArtifactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayloadStore::class);
    }
}
