<?php

namespace App\Providers;

use App\Domain\Accounts\SettingsLoginRateLimiter;
use App\Domain\Admin\SystemSettings;
use App\Domain\Audit\Listeners\LogAuthEvents;
use App\Domain\Audit\Listeners\LogSentMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnalyticsClient\AnalyticsClient;
use App\Services\AnalyticsClient\HttpAnalyticsClient;
use App\Services\LlmClient\LlmClient;
use App\Services\LlmClient\NullLlmClient;
use App\Services\LlmClient\OpenAiLlmClient;
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
use Laravel\Fortify\LoginRateLimiter;

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

        // LLM provider (§10): OpenAI when LLM_ENABLED=true, a key is set and the administrator has not
        // switched suggestions off in Settings. Otherwise the null client, so the TF-IDF fallback is used.
        $this->app->singleton(LlmClient::class, function () {
            $allowed = (bool) Setting::valueOf('llm.enabled', true);
            if (config('tua.llm.enabled') && $allowed && filled(config('tua.llm.api_key')) && in_array(config('tua.llm.provider') ?: 'openai', ['openai'], true)) {
                return new OpenAiLlmClient(
                    apiKey: (string) config('tua.llm.api_key'),
                    model: (string) (config('tua.llm.model') ?: 'gpt-4o-mini'),
                    timeout: SystemSettings::llmTimeout(),
                    ratePerMinute: SystemSettings::llmRate(),
                );
            }

            return new NullLlmClient;
        });
        $this->app->singleton(SmsClient::class, NullSmsClient::class);
        // Sign-in attempts before the pause come from System settings (§7 M9).
        $this->app->singleton(LoginRateLimiter::class, SettingsLoginRateLimiter::class);
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
