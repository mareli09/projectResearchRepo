<?php

namespace App\Domain\Admin;

use App\Domain\Audit\Activity;
use App\Models\MailLog;
use App\Models\RecordFile;
use App\Services\AnalyticsClient\AnalyticsClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** Live figures for "System health" and "Services and security" (CLAUDE.md §7 M9). */
final class SystemHealth
{
    public const SERVER_DISK_GB = 70;

    public const SERVER_RAM_GB = 4;

    /** @return array{used: ?float, total: ?float} GB on the disk that holds storage/ */
    public static function disk(): array
    {
        $total = @disk_total_space(storage_path()) ?: 0;
        $free = @disk_free_space(storage_path()) ?: 0;

        return $total ? ['used' => round(($total - $free) / 1073741824, 1), 'total' => round($total / 1073741824, 1)] : ['used' => null, 'total' => null];
    }

    public static function filesGb(): float
    {
        return round((int) RecordFile::sum('size') / 1073741824, 2);
    }

    public static function databaseGb(): ?float
    {
        try {
            $bytes = DB::selectOne('SELECT SUM(data_length + index_length) AS b FROM information_schema.tables WHERE table_schema = DATABASE()');

            return round(((int) ($bytes->b ?? 0)) / 1073741824, 3);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{used: ?float, total: ?float} GB, from /proc/meminfo on the Ubuntu server; null elsewhere */
    public static function memory(): array
    {
        $info = @file_get_contents('/proc/meminfo');
        if (! is_string($info) || ! preg_match('/MemTotal:\s+(\d+)/', $info, $t) || ! preg_match('/MemAvailable:\s+(\d+)/', $info, $a)) {
            return ['used' => null, 'total' => null];
        }

        return ['used' => round(((int) $t[1] - (int) $a[1]) / 1048576, 1), 'total' => round((int) $t[1] / 1048576, 1)];
    }

    /** @return array{ok: bool, detail: string, ms: int} */
    public static function analytics(): array
    {
        $started = microtime(true);
        try {
            $h = app(AnalyticsClient::class)->health();
            $ms = (int) ((microtime(true) - $started) * 1000);

            return ['ok' => (bool) $h['ok'], 'detail' => number_format($h['n_docs']).' records indexed · '.round($h['mem_mb']).' MB · '.($h['vectorizer_version'] ?? 'no index'), 'ms' => $ms];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => Str::limit($e->getMessage(), 120), 'ms' => (int) ((microtime(true) - $started) * 1000)];
        }
    }

    public static function llmConfigured(): bool
    {
        return (bool) config('tua.llm.enabled') && filled(config('tua.llm.api_key')) && SystemSettings::current('llm')['enabled'];
    }

    /** @return list<array{name: string, status: string, tone: string, when: string}> waiting and failed jobs, newest first */
    public static function jobs(int $limit = 10): array
    {
        $name = fn (string $payload) => class_basename((string) (json_decode($payload, true)['displayName'] ?? 'Job'));
        $waiting = DB::table('jobs')->orderByDesc('id')->limit($limit)->get(['payload', 'attempts', 'created_at'])
            ->map(fn ($j) => ['name' => $name($j->payload), 'status' => $j->attempts ? 'Retrying' : 'Waiting', 'tone' => 'info',
                'when' => Carbon::createFromTimestamp((int) $j->created_at)->timezone(config('app.timezone'))->format('M j · g:i A'), 'at' => (int) $j->created_at]);
        $failed = DB::table('failed_jobs')->orderByDesc('id')->limit($limit)->get(['payload', 'exception', 'failed_at'])
            ->map(fn ($j) => ['name' => $name($j->payload), 'status' => 'Failed: '.Str::limit(strtok((string) $j->exception, "\n") ?: '', 60), 'tone' => 'bad',
                'when' => Carbon::parse($j->failed_at)->format('M j · g:i A'), 'at' => Carbon::parse($j->failed_at)->timestamp]);

        return $waiting->concat($failed)->sortByDesc('at')->take($limit)->map(fn ($j) => array_diff_key($j, ['at' => 1]))->values()->all();
    }

    /** @return list<array{when: string, service: string, purpose: string, fields: string, ok: bool, result: string}> */
    public static function recentCalls(int $limit = 15): array
    {
        return Activity::where('event', 'service')->latest('id')->limit($limit)->get()->map(function (Activity $a) {
            $d = (array) ($a->properties['details'] ?? []);
            $result = (string) ($d['result'] ?? 'ok');

            return [
                'when' => $a->created_at?->format('M j · g:i A') ?? '',
                'service' => ['analytics' => 'Core → Analytics', 'llm' => 'Core → LLM', 'mail' => 'Core → Mail', 'queue' => 'Core → Queue'][$d['service'] ?? ''] ?? 'Core',
                'purpose' => (string) $a->description,
                'fields' => implode(', ', str_replace('_', ' ', (array) ($d['fields_sent'] ?? []))) ?: '—',
                'ok' => in_array($result, ['ok', 'done'], true),
                'result' => $result.(isset($d['ms']) ? ' · '.number_format($d['ms'] / 1000, 2).' s' : ''),
            ];
        })->values()->all();
    }

    /** @return array{failed: int, recent: list<array{to: string, subject: string, error: ?string, when: string}>} */
    public static function mail(): array
    {
        return [
            'failed' => MailLog::where('status', 'failed')->count(),
            'recent' => MailLog::where('status', 'failed')->latest('id')->limit(5)->get()->map(fn (MailLog $m) => [
                'to' => (string) $m->getAttribute('to'), 'subject' => (string) $m->getAttribute('subject'), 'error' => $m->getAttribute('last_error'),
                'when' => $m->getAttribute('updated_at')?->format('M j · g:i A') ?? '',
            ])->values()->all(),
        ];
    }
}
