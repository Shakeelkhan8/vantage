<?php

namespace App\Providers;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request, command or queued job — never shared across them.
        $this->app->scoped(WorkspaceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A lazy-loaded relation inside an ingestion loop is thousands of
        // queries, and it will not be obvious from the outside.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Vite::prefetch(concurrency: 3);
    }
}
