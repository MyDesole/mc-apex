<?php

namespace App\Domains\Moderation\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Техническое состояние сервера, на котором стоит сайт: нагрузка, память, диск,
 * база, Redis, очередь, веб-сокеты и свежие ошибки в логе.
 *
 * Все проверки защищены: недоступная метрика даёт null, а не ошибку дашборда.
 * Результат кэшируется на 20 секунд — частое открытие дашборда не нагружает сервер.
 */
class SystemHealthService
{
    private const CACHE_SECONDS = 20;

    public function snapshot(): array
    {
        return Cache::remember('admin:system-health', self::CACHE_SECONDS, fn () => $this->collect());
    }

    private function collect(): array
    {
        $host = $this->host();
        $database = $this->database();
        $redis = $this->redis();
        $queue = $this->queue();
        $reverb = $this->reverb();
        $log = $this->log();

        return [
            'checked_at' => now()->toIso8601String(),
            'app' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'timezone' => config('app.timezone'),
                'memory_limit' => ini_get('memory_limit'),
                'opcache' => function_exists('opcache_get_status') && (@opcache_get_status(false)['opcache_enabled'] ?? false),
            ],
            'host' => $host,
            'database' => $database,
            'redis' => $redis,
            'queue' => $queue,
            'reverb' => $reverb,
            'log' => $log,
            'alerts' => $this->alerts($host, $database, $redis, $queue, $reverb, $log),
        ];
    }

    /* ----------------------------- Сервер ----------------------------- */

    private function host(): array
    {
        $uptime = $this->readFloat('/proc/uptime');
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null;
        $mem = $this->meminfo();
        $path = base_path();
        $total = @disk_total_space($path) ?: null;
        $free = @disk_free_space($path) ?: null;

        return [
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'hostname' => gethostname() ?: null,
            'uptime_seconds' => $uptime !== null ? (int) $uptime : null,
            'cores' => $this->cores(),
            'load' => $load ? array_map(fn ($v) => round($v, 2), $load) : null,
            'memory' => $mem,
            'disk' => $total && $free !== null ? [
                'total_gb' => round($total / 1024 ** 3, 1),
                'used_gb' => round(($total - $free) / 1024 ** 3, 1),
                'used_pct' => (int) round(($total - $free) / $total * 100),
            ] : null,
        ];
    }

    private function readFloat(string $file): ?float
    {
        $raw = @file_get_contents($file);

        return $raw === false ? null : (float) explode(' ', trim($raw))[0];
    }

    private function cores(): ?int
    {
        $raw = @file_get_contents('/proc/cpuinfo');

        return $raw === false ? null : max(1, substr_count($raw, "\nprocessor"));
    }

    /** Память и своп из /proc/meminfo, МБ. */
    private function meminfo(): ?array
    {
        $raw = @file_get_contents('/proc/meminfo');

        if ($raw === false) {
            return null;
        }

        $kb = [];

        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $kb[$m[1]] = (int) $m[2];
            }
        }

        $total = $kb['MemTotal'] ?? 0;
        $available = $kb['MemAvailable'] ?? ($kb['MemFree'] ?? 0);

        if ($total === 0) {
            return null;
        }

        return [
            'total_mb' => (int) round($total / 1024),
            'used_mb' => (int) round(($total - $available) / 1024),
            'available_mb' => (int) round($available / 1024),
            'used_pct' => (int) round(($total - $available) / $total * 100),
            'swap_used_mb' => (int) round((($kb['SwapTotal'] ?? 0) - ($kb['SwapFree'] ?? 0)) / 1024),
        ];
    }

    /* ----------------------------- База, Redis, очередь ----------------------------- */

    private function database(): array
    {
        try {
            $started = microtime(true);
            DB::select('select 1');
            $ping = round((microtime(true) - $started) * 1000, 1);

            $info = ['ok' => true, 'driver' => DB::getDriverName(), 'ping_ms' => $ping, 'size_mb' => null, 'tables' => null, 'connections' => null];

            if (DB::getDriverName() === 'mysql') {
                $row = DB::selectOne(
                    'select round(sum(data_length + index_length) / 1024 / 1024, 1) as mb, count(*) as tables_count '
                    . 'from information_schema.tables where table_schema = database()'
                );
                $info['size_mb'] = $row ? (float) $row->mb : null;
                $info['tables'] = $row ? (int) $row->tables_count : null;

                $threads = DB::selectOne("show status like 'Threads_connected'");
                $info['connections'] = $threads ? (int) ($threads->Value ?? $threads->value ?? 0) : null;
            }

            return $info;
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_strimwidth($e->getMessage(), 0, 160, '…')];
        }
    }

    private function redis(): array
    {
        try {
            $started = microtime(true);
            $connection = Redis::connection();
            $connection->ping();
            $ping = round((microtime(true) - $started) * 1000, 1);
            $info = $connection->info();
            $info = $info['Memory'] ?? $info;
            $clients = $connection->info('clients');

            return [
                'ok' => true,
                'ping_ms' => $ping,
                'used_memory' => $info['used_memory_human'] ?? null,
                'clients' => (int) (($clients['Clients']['connected_clients'] ?? $clients['connected_clients'] ?? 0)),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_strimwidth($e->getMessage(), 0, 160, '…')];
        }
    }

    private function queue(): array
    {
        $info = ['connection' => config('queue.default'), 'pending' => null, 'failed' => null, 'workers' => null];

        try {
            $info['pending'] = (int) Queue::size();
        } catch (\Throwable) {
            // очередь недоступна — отметим null
        }

        try {
            $info['failed'] = (int) DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (\Throwable) {
            // таблицы нет
        }

        if (function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            $out = [];
            @exec('pgrep -fc "artisan queue:work" 2>/dev/null', $out);
            $info['workers'] = isset($out[0]) ? (int) $out[0] : null;
        }

        return $info;
    }

    private function reverb(): array
    {
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);

        if ($socket) {
            fclose($socket);
        }

        return ['port' => $port, 'ok' => (bool) $socket];
    }

    /* ----------------------------- Лог ----------------------------- */

    /** Размер лога и ошибки за сутки: читаем только хвост файла, он может быть огромным. */
    private function log(): array
    {
        $file = storage_path('logs/laravel.log');

        if (! is_file($file)) {
            return ['size_mb' => 0.0, 'errors_day' => 0, 'last' => []];
        }

        $size = filesize($file) ?: 0;
        $tail = '';
        $handle = @fopen($file, 'rb');

        if ($handle) {
            fseek($handle, max(0, $size - 400_000));
            $tail = (string) stream_get_contents($handle);
            fclose($handle);
        }

        $since = now()->subDay();
        $errors = [];

        // Строка начала записи: [2026-11-11 16:05:03] production.ERROR: сообщение
        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.{0,200})/m', $tail, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            if (strtotime($m[1]) >= $since->timestamp) {
                $errors[] = ['at' => $m[1], 'level' => $m[2], 'message' => trim($m[3])];
            }
        }

        return [
            'size_mb' => round($size / 1024 / 1024, 1),
            'errors_day' => count($errors),
            'last' => array_slice(array_reverse($errors), 0, 5),
        ];
    }

    /* ----------------------------- Тревоги ----------------------------- */

    /** Что требует внимания: короткие фразы для красной полосы на дашборде. */
    private function alerts(array $host, array $db, array $redis, array $queue, array $reverb, array $log): array
    {
        $alerts = [];

        if (! ($db['ok'] ?? false)) {
            $alerts[] = ['level' => 'crit', 'text' => 'База данных не отвечает'];
        }

        if (! ($redis['ok'] ?? false)) {
            $alerts[] = ['level' => 'crit', 'text' => 'Redis не отвечает (очередь и кэш)'];
        }

        if (! ($reverb['ok'] ?? false)) {
            $alerts[] = ['level' => 'warn', 'text' => 'Веб-сокеты (Reverb) недоступны: чат и уведомления без «живого» обновления'];
        }

        if (($queue['workers'] ?? 1) === 0) {
            $alerts[] = ['level' => 'crit', 'text' => 'Не запущен ни один обработчик очереди: письма и уведомления не уйдут'];
        }

        if (($queue['failed'] ?? 0) > 0) {
            $alerts[] = ['level' => 'warn', 'text' => "Упавших задач в очереди: {$queue['failed']}"];
        }

        if (($queue['pending'] ?? 0) > 200) {
            $alerts[] = ['level' => 'warn', 'text' => "В очереди скопилось задач: {$queue['pending']}"];
        }

        if (($host['disk']['used_pct'] ?? 0) >= 85) {
            $alerts[] = ['level' => ($host['disk']['used_pct'] >= 95 ? 'crit' : 'warn'), 'text' => "Диск заполнен на {$host['disk']['used_pct']}%"];
        }

        if (($host['memory']['used_pct'] ?? 0) >= 92) {
            $alerts[] = ['level' => 'warn', 'text' => "Память занята на {$host['memory']['used_pct']}%"];
        }

        $cores = $host['cores'] ?? 1;

        if (($host['load'][0] ?? 0) > $cores * 1.5) {
            $alerts[] = ['level' => 'warn', 'text' => "Высокая нагрузка CPU: load {$host['load'][0]} при {$cores} ядрах"];
        }

        if (($log['errors_day'] ?? 0) >= 50) {
            $alerts[] = ['level' => 'warn', 'text' => "Много ошибок в логе за сутки: {$log['errors_day']}"];
        }

        if (config('app.debug') && app()->environment('production')) {
            $alerts[] = ['level' => 'crit', 'text' => 'APP_DEBUG включён на проде'];
        }

        return $alerts;
    }
}
