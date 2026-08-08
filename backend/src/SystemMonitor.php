<?php

/**
 * Reads host-level CPU/RAM/disk usage via read-only bind mounts of the
 * host's /proc and / into this container (see docker-compose.yml). Returns
 * null for any metric whose source path isn't available instead of failing,
 * so the dashboard can still render.
 */
final class SystemMonitor
{
    private const PROC_PATH = '/host/proc';
    private const ROOT_PATH = '/host/rootfs';

    public static function snapshot(): array
    {
        $cpu = self::cpu();
        $memory = self::memory();
        $disk = self::disk();
        $ollamaOnline = OllamaClient::ping();
        $databaseOnline = Database::isHealthy();

        return [
            'cpu' => $cpu,
            'memory' => $memory,
            'disk' => $disk,
            'ollamaOnline' => $ollamaOnline,
            'databaseOnline' => $databaseOnline,
            'services' => [
                ['name' => 'backend', 'label' => 'Backend API', 'online' => true],
                ['name' => 'database', 'label' => 'ฐานข้อมูล (MySQL/MariaDB)', 'online' => $databaseOnline],
                ['name' => 'ollama', 'label' => 'Ollama (AI)', 'online' => $ollamaOnline],
            ],
            'warnings' => [
                'cpu' => $cpu !== null && $cpu['percent'] > 90,
                'memory' => $memory !== null && $memory['percent'] > 90,
                'disk' => $disk !== null && $disk['percent'] > 85,
                'ollama' => !$ollamaOnline,
                'database' => !$databaseOnline,
            ],
        ];
    }

    private static function readCpuLine(string $statFile): ?array
    {
        $handle = @fopen($statFile, 'r');
        if ($handle === false) {
            return null;
        }
        $line = fgets($handle);
        fclose($handle);
        if ($line === false) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($line));
        array_shift($parts); // drop the leading "cpu" label
        return array_map('intval', $parts);
    }

    private static function cpu(): ?array
    {
        $statFile = self::PROC_PATH . '/stat';
        if (!is_readable($statFile)) {
            return null;
        }

        $before = self::readCpuLine($statFile);
        usleep(200000);
        $after = self::readCpuLine($statFile);

        if ($before === null || $after === null) {
            return null;
        }

        $idleBefore = ($before[3] ?? 0) + ($before[4] ?? 0);
        $idleAfter = ($after[3] ?? 0) + ($after[4] ?? 0);
        $totalBefore = array_sum($before);
        $totalAfter = array_sum($after);

        $totalDelta = $totalAfter - $totalBefore;
        $idleDelta = $idleAfter - $idleBefore;

        if ($totalDelta <= 0) {
            return ['percent' => 0.0];
        }

        $percent = (1 - $idleDelta / $totalDelta) * 100;
        return ['percent' => round(max(0.0, min(100.0, $percent)), 1)];
    }

    private static function memory(): ?array
    {
        $file = self::PROC_PATH . '/meminfo';
        if (!is_readable($file)) {
            return null;
        }

        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        $info = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $info[$m[1]] = (int) $m[2]; // kB
            }
        }

        $totalKb = $info['MemTotal'] ?? 0;
        if ($totalKb <= 0) {
            return null;
        }

        $availableKb = $info['MemAvailable'] ?? ($info['MemFree'] ?? 0);
        $usedKb = max(0, $totalKb - $availableKb);

        return [
            'totalMb' => round($totalKb / 1024, 1),
            'usedMb' => round($usedKb / 1024, 1),
            'percent' => round(($usedKb / $totalKb) * 100, 1),
        ];
    }

    private static function disk(): ?array
    {
        if (!is_dir(self::ROOT_PATH)) {
            return null;
        }

        $total = @disk_total_space(self::ROOT_PATH);
        $free = @disk_free_space(self::ROOT_PATH);
        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        $used = $total - $free;

        return [
            'totalGb' => round($total / 1073741824, 1),
            'usedGb' => round($used / 1073741824, 1),
            'percent' => round(($used / $total) * 100, 1),
        ];
    }
}
