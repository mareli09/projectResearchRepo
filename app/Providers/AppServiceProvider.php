<?php

namespace App\Providers;

use App\Domain\Audit\Listeners\LogAuthEvents;
use App\Domain\Audit\Listeners\LogSentMail;
use App\Models\User;
use App\Services\AnalyticsClient\AnalyticsClient;
use App\Services\AnalyticsClient\HttpAnalyticsClient;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\NullLlmClient;
use App\Services\SmsClient\NullSmsClient;
use App\Services\SmsClient\SmsClient;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swappable providers (CLAUDE.md §2, §14 Flexibility).
        $this->app->singleton(AnalyticsClient::class, fn () => new HttpAnalyticsClient(
            baseUrl: (string) config('tua.analytics_service.url'),
            secret: (string) config('tua.analytics_service.hmac_secret'),
            timeout: (int) config('tua.analytics_service.timeout'),
            retries: (int) config('tua.analytics_service.retries'),
        ));

        // Real providers are added once the LLM provider, DPO approval and SMS gateway are confirmed (§20 items 5–6).
        $this->app->singleton(LlmClient::class, NullLlmClient::class);
        $this->app->singleton(SmsClient::class, NullSmsClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catch lazy loading, silently discarded attributes and missing attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Activity log (CLAUDE.md §13) and mail log (§12).
        Event::listen(Login::class, [LogAuthEvents::class, 'login']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'logout']);
        Event::listen(Failed::class, [LogAuthEvents::class, 'failed']);
        Event::listen(Lockout::class, [LogAuthEvents::class, 'lockout']);
        Event::listen(MessageSent::class, LogSentMail::class);

        // Permissions are checked per action; the System administrator has no blanket bypass (§4).
        Gate::define('assign-role', fn (User $actor, User $target) => $actor->can('users.manage') && $actor->isNot($target));
    }
}
