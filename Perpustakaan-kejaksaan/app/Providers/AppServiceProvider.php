<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Summary;
use App\Policies\DocumentPolicy;
use App\Policies\SummaryPolicy;
use App\Services\KlienLayananAi;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KlienLayananAi::class, fn () => new KlienLayananAi(
            config('pustakaai.ai.url'),
            config('pustakaai.ai.token'),
        ));
    }

    public function boot(): void
    {
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Summary::class, SummaryPolicy::class);

        // NFR 6.2: seluruh komunikasi lewat HTTPS di lingkungan produksi.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
