<?php

namespace App\Domains\Server\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Управление игровым сервером (apevpvp.pro) по SSH.
 *
 * Вся логика запуска/остановки живёт в скрипте на самом сервере
 * (apex-panel.sh): панель только вызывает его и разбирает ответ. Так
 * сайт не зависит от того, как именно устроен запуск Minecraft, а ключ
 * даёт ровно те права, что нужны.
 *
 * Ключ и адрес — в config/services.php (game_server), иначе после
 * config:cache на проде env() вернёт null.
 */
class GameServerService
{
    public const PANEL = '/opt/minecraft/server/apex-panel.sh';

    /** Что можно загружать на сервер. */
    public const UPLOAD_EXT = ['jar', 'zip', 'yml', 'yaml', 'json', 'txt', 'properties', 'conf', 'sh'];

    /**
     * Куда можно загружать. Каталога config на этом сервере нет (это
     * Spigot, а не Paper), поэтому целей две: корень и плагины.
     */
    public const UPLOAD_DIRS = ['' => '', 'plugins' => '/plugins'];

    public function host(): string
    {
        return (string) config('services.game_server.host', '168.113.181.145');
    }

    public function user(): string
    {
        return (string) config('services.game_server.user', 'root');
    }

    public function key(): string
    {
        return (string) config('services.game_server.key', storage_path('app/server/id_ed25519'));
    }

    public function dir(): string
    {
        return rtrim((string) config('services.game_server.dir', '/opt/minecraft/server'), '/');
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $result = $this->ssh('bash ' . self::PANEL . ' status');

        if ($result['code'] !== 0) {
            throw new RuntimeException('Сервер не ответил: ' . trim($result['err'] ?: $result['out']));
        }

        $data = [];

        foreach (explode("\n", trim($result['out'])) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value);
        }

        return [
            'host' => $this->host(),
            'running' => ($data['running'] ?? '0') === '1',
            'pid' => $data['pid'] ?? null,
            'uptime' => $data['uptime'] ?? null,
            'jar' => $data['jar'] ?? null,
            'players' => (int) ($data['players'] ?? 0),
            'mem_total' => (int) ($data['mem_total'] ?? 0),
            'mem_used' => (int) ($data['mem_used'] ?? 0),
            'disk_used' => $data['disk_used'] ?? null,
            'disk_total' => $data['disk_total'] ?? null,
            'load' => $data['load'] ?? null,
            'log_size' => $data['size'] ?? null,
            'log_updated' => $data['updated'] ?? null,
        ];
    }

    /**
     * Точное число игроков: посылаем list и разбираем ответ.
     *
     * Оценка по журналу (входы минус выходы) врёт после перезапуска
     * сервера — журнал начинается заново. Поэтому для точности отдельный
     * вызов.
     *
     * @return array{online: int, max: int}
     */
    public function players(): array
    {
        $result = $this->ssh('bash ' . self::PANEL . ' players', 30);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Сервер не ответил: ' . trim($result['err'] ?: $result['out']));
        }

        $data = [];

        foreach (explode("\n", trim($result['out'])) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = (int) trim($value);
        }

        return [
            'online' => $data['online'] ?? -1,
            'max' => $data['max'] ?? 0,
        ];
    }

    /** @return array<int, array{name: string, size: string}> */
    public function plugins(): array
    {
        $result = $this->ssh('bash ' . self::PANEL . ' plugins', 20);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Сервер не ответил: ' . trim($result['err'] ?: $result['out']));
        }

        $plugins = [];

        foreach (explode("\n", trim($result['out'])) as $line) {
            if (! str_contains($line, "\t")) {
                continue;
            }

            [$name, $size] = explode("\t", $line, 2);

            $plugins[] = ['name' => trim($name), 'size' => trim($size)];
        }

        return $plugins;
    }

    /* ==================== Конфиги ==================== */

    /** Расширения, которые разрешено редактировать. */
    public const CONFIG_EXT = ['properties', 'yml', 'yaml', 'json', 'txt', 'conf'];

    /**
     * Список редактируемых конфигов: корень сервера и plugins/*//*.
     *
     * @return array<int, array{path: string, size: string, group: string}>
     */
    public function files(): array
    {
        $result = $this->ssh('bash ' . self::PANEL . ' files', 25);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Сервер не ответил: ' . trim($result['err'] ?: $result['out']));
        }

        $files = [];

        foreach (explode("\n", trim($result['out'])) as $line) {
            if (! str_contains($line, "\t")) {
                continue;
            }

            [$path, $size] = explode("\t", $line, 2);
            $path = trim($path);

            $files[] = [
                'path' => $path,
                'size' => trim($size),
                'group' => str_starts_with($path, 'plugins/') ? 'plugins' : 'root',
            ];
        }

        return $files;
    }

    /** Прочитать конфиг. */
    public function readFile(string $path): string
    {
        $this->assertConfigPath($path);

        $result = $this->ssh('bash ' . self::PANEL . ' read ' . escapeshellarg($path), 20);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Не удалось прочитать: ' . trim($result['out'] ?: $result['err']));
        }

        return $result['out'];
    }

    /** Записать конфиг. */
    public function saveFile(string $path, string $content): void
    {
        $this->assertConfigPath($path);

        if (strlen($content) > 512000) {
            throw new RuntimeException('Файл больше 500 КБ — так не сохранить.');
        }

        $result = $this->ssh('bash ' . self::PANEL . ' write ' . escapeshellarg($path), 30, $content);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Не удалось сохранить: ' . trim($result['out'] ?: $result['err']));
        }
    }

    /** Путь конфига: только внутри сервера, без .., с известным расширением. */
    private function assertConfigPath(string $path): void
    {
        if ($path === '' || strlen($path) > 200) {
            throw new RuntimeException('Некорректный путь.');
        }

        if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, "\\")) {
            throw new RuntimeException('Некорректный путь.');
        }

        if (! preg_match('#^[A-Za-z0-9_./-]+$#', $path)) {
            throw new RuntimeException('В пути недопустимые символы.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($ext, self::CONFIG_EXT, true)) {
            throw new RuntimeException('Такой файл не редактируется (.' . $ext . ').');
        }
    }

    /* ==================== Архивы сервера ==================== */

    /** @return array<int, array{name: string, size: string, created: string}> */
    public function archives(): array
    {
        return $this->table($this->ssh('bash ' . self::PANEL . ' archives', 20), ['name', 'size', 'created']);
    }

    /** @return array<int, array{name: string, size: string, created: string}> */
    public function backups(): array
    {
        return $this->table($this->ssh('bash ' . self::PANEL . ' backups', 20), ['name', 'size', 'created']);
    }

    private function table(array $result, array $columns): array
    {
        if ($result['code'] !== 0) {
            throw new RuntimeException('Сервер не ответил: ' . trim($result['err'] ?: $result['out']));
        }

        $rows = [];

        foreach (explode("\n", trim($result['out'])) as $line) {
            if (! str_contains($line, "\t")) {
                continue;
            }

            $cells = explode("\t", $line);
            $row = [];

            foreach ($columns as $index => $column) {
                $row[$column] = trim($cells[$index] ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /** Залить zip в папку обновлений на сервере. */
    public function uploadArchive(UploadedFile $file): array
    {
        $name = $this->safeArchiveName($file->getClientOriginalName());

        $result = $this->exec([
            'scp', '-i', $this->key(),
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            (string) $file->getRealPath(),
            $this->user() . '@' . $this->host() . ':/opt/minecraft/updates/' . $name,
        ], 600);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Загрузка не удалась: ' . trim($result['err'] ?: $result['out']));
        }

        return ['name' => $name, 'size' => (int) $file->getSize()];
    }

    /**
     * Применить архив: стоп → бэкап → распаковка поверх → старт.
     *
     * @return array{applied: bool, backup: ?string, output: string}
     */
    public function applyArchive(string $name): array
    {
        $name = $this->safeArchiveName($name);

        $result = $this->ssh('bash ' . self::PANEL . ' apply-zip ' . escapeshellarg($name), 600);
        $out = trim($result['out'] . "\n" . $result['err']);

        if ($result['code'] !== 0 || ! str_contains($out, 'applied')) {
            throw new RuntimeException('Применение не удалось: ' . ($out !== '' ? $out : 'нет ответа'));
        }

        $backup = null;

        if (preg_match('/backup=(server-[0-9-]+\.tar\.gz)/', $out, $m)) {
            $backup = $m[1];
        }

        return ['applied' => true, 'backup' => $backup, 'output' => $out];
    }

    public function deleteArchive(string $name): void
    {
        $name = $this->safeArchiveName($name);

        $result = $this->ssh('bash ' . self::PANEL . ' rm-archive ' . escapeshellarg($name), 20);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Не удалось удалить: ' . trim($result['out'] ?: $result['err']));
        }
    }

    /** Восстановить каталог из бэкапа (тоже со стопом и стартом). */
    public function restoreBackup(string $name): string
    {
        if (! preg_match('/^server-[0-9-]+\.tar\.gz$/', $name)) {
            throw new RuntimeException('Некорректное имя бэкапа.');
        }

        $result = $this->ssh('bash ' . self::PANEL . ' restore ' . escapeshellarg($name), 600);
        $out = trim($result['out'] . "\n" . $result['err']);

        if ($result['code'] !== 0 || ! str_contains($out, 'restored')) {
            throw new RuntimeException('Восстановление не удалось: ' . ($out !== '' ? $out : 'нет ответа'));
        }

        return $out;
    }

    /** Имя архива: только простое имя, обязательно .zip. */
    private function safeArchiveName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));

        if (! preg_match('/^[A-Za-z0-9._-]{1,120}\.zip$/i', $name)) {
            throw new RuntimeException('Имя архива должно быть вида name.zip (латиница, цифры, точка, дефис).');
        }

        return $name;
    }

    public function console(int $lines = 150): string
    {
        $lines = max(20, min(500, $lines));

        return $this->ssh('bash ' . self::PANEL . ' console ' . $lines)['out'];
    }

    public function start(): string
    {
        return $this->simple('start', 60);
    }

    public function stop(): string
    {
        return $this->simple('stop', 90);
    }

    public function restart(): string
    {
        return $this->simple('restart', 150);
    }

    /** Отправить команду в консоль сервера (say, list, whitelist…). */
    public function command(string $command): string
    {
        $command = trim($command);

        if ($command === '') {
            throw new RuntimeException('Пустая команда.');
        }

        if (mb_strlen($command) > 300) {
            throw new RuntimeException('Команда слишком длинная.');
        }

        return $this->simple('cmd ' . escapeshellarg($command), 20);
    }

    public function startCommand(): string
    {
        return $this->ssh('bash ' . self::PANEL . ' get-start')['out'];
    }

    public function saveStartCommand(string $content): void
    {
        $content = trim($content);

        if ($content === '') {
            throw new RuntimeException('Пустая команда запуска.');
        }

        if (! str_contains($content, 'java')) {
            throw new RuntimeException('В команде запуска должна быть java.');
        }

        $result = $this->ssh('bash ' . self::PANEL . ' set-start', 20, $content . "\n");

        if ($result['code'] !== 0) {
            throw new RuntimeException('Не удалось сохранить: ' . trim($result['err'] ?: $result['out']));
        }
    }

    /** @return array{name: string, dir: string, size: int} */
    public function upload(UploadedFile $file, string $target = ''): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($ext, self::UPLOAD_EXT, true)) {
            throw new RuntimeException('Такой файл загружать нельзя (.' . $ext . ').');
        }

        if (! array_key_exists($target, self::UPLOAD_DIRS)) {
            throw new RuntimeException('Неизвестная папка для загрузки.');
        }

        $remote = $this->dir() . self::UPLOAD_DIRS[$target];

        $result = $this->exec([
            'scp', '-i', $this->key(),
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            (string) $file->getRealPath(),
            $this->user() . '@' . $this->host() . ':' . $remote . '/',
        ], 180);

        if ($result['code'] !== 0) {
            throw new RuntimeException('Загрузка не удалась: ' . trim($result['err'] ?: $result['out']));
        }

        return [
            'name' => $file->getClientOriginalName(),
            'dir' => $target === '' ? 'корень сервера' : $target,
            'size' => (int) $file->getSize(),
        ];
    }

    private function simple(string $action, int $timeout): string
    {
        $result = $this->ssh('bash ' . self::PANEL . ' ' . $action, $timeout);
        $out = trim($result['out'] . "\n" . $result['err']);

        if ($result['code'] !== 0) {
            throw new RuntimeException($out !== '' ? $out : 'Команда завершилась с ошибкой.');
        }

        return $out;
    }

    /** @return array{code:int, out:string, err:string} */
    private function ssh(string $remote, int $timeout = 20, ?string $stdin = null): array
    {
        return $this->exec([
            'ssh', '-i', $this->key(),
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            $this->user() . '@' . $this->host(),
            $remote,
        ], $timeout, $stdin);
    }

    /**
     * Запуск процесса без оболочки (массив аргументов) — так команда из
     * консоли не может стать аргументом sh.
     *
     * @param  array<int, string>  $args
     * @return array{code:int, out:string, err:string}
     */
    protected function exec(array $args, int $timeout = 20, ?string $stdin = null): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($args, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Не удалось запустить ssh — проверьте ключ доступа.');
        }

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out = '';
        $err = '';
        $deadline = time() + $timeout;

        while (true) {
            $out .= stream_get_contents($pipes[1]);
            $err .= stream_get_contents($pipes[2]);

            $status = proc_get_status($process);

            if (! $status['running']) {
                break;
            }

            if (time() > $deadline) {
                proc_terminate($process, 9);
                $err .= "\n(таймаут)";
                break;
            }

            usleep(50000);
        }

        $out .= stream_get_contents($pipes[1]);
        $err .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return ['code' => $code, 'out' => $out, 'err' => $err];
    }
}
