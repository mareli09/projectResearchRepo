<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * php artisan analytics:serve — run the Python analytics service locally on 127.0.0.1:8001.
 * It reads ANALYTICS_HMAC_SECRET from this project's .env. On the server, Supervisor runs it instead.
 */
class AnalyticsServe extends Command
{
    protected $signature = 'analytics:serve {--port=8001}';

    protected $description = 'Run the Python analytics service (TF-IDF screening, K-Means) on 127.0.0.1';

    public function handle(): int
    {
        $dir = base_path('analytics');
        $bin = PHP_OS_FAMILY === 'Windows' ? $dir.'\\.venv\\Scripts\\uvicorn.exe' : $dir.'/.venv/bin/uvicorn';
        if (! is_file($bin)) {
            $this->error('The analytics virtual environment is missing. See README, "Analytics service".');

            return self::FAILURE;
        }

        $this->info('Analytics service on http://127.0.0.1:'.$this->option('port').' (Ctrl+C to stop)');
        $process = new Process([$bin, 'app.main:app', '--host', '127.0.0.1', '--port', (string) $this->option('port'), '--workers', '1'], $dir, [
            'ANALYTICS_HMAC_SECRET' => (string) config('tua.analytics_service.hmac_secret'),
            'ANALYTICS_WARM_NLTK' => '1',
        ]);
        $process->setTimeout(null);

        return $process->run(fn ($type, $out) => $this->output->write($out));
    }
}
