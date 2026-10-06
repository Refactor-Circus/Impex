<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Artifact;

use JayI\Foundation\Support\ServiceProvider;
use JayI\Impex\Domains\Artifact\Models\ArtifactModel;
use JayI\Impex\Domains\Artifact\Services\PayloadStore;

class ArtifactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayloadStore::class);
    }

    public function boot(): void
    {
        $this->keepMorphAliases([
            'JayI\Impex\Models\Artifact' => ArtifactModel::class,
        ]);
    }
}
