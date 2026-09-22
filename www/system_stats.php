<?php
declare(strict_types=1);

/**
 * Utilitas statistik sistem dari perspektif container.
 * Catatan: /proc/meminfo, load average, dan uptime mencerminkan
 * kernel host — hal ini normal untuk container (lihat README).
 */

function meminfo_kb(string $key): int
{
    $file = '/proc/meminfo';
    if (!is_readable($file)) {
        return 0;
    }
    $pattern = '/^' . preg_quote($key, '/') . ':\s+(\d+)\s+kB$/m';
    if (preg_match($pattern, (string) file_get_contents($file), $m)) {
        return (int) $m[1];
    }
    return 0;
}

function system_stats(): array
{
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0.0, 0.0, 0.0];

    $memTotalKb = meminfo_kb('MemTotal');
    $memAvailKb = meminfo_kb('MemAvailable');
    $memTotal   = $memTotalKb * 1024;
    $memUsed    = $memTotal - ($memAvailKb * 1024);
    $memPct     = $memTotal > 0 ? round($memUsed / $memTotal * 100, 1) : 0.0;

    $diskTotal = @disk_total_space('/var/www/html') ?: 0;
    $diskFree  = @disk_free_space('/var/www/html') ?: 0;
    $diskUsed  = max(0, $diskTotal - $diskFree);
    $diskPct   = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100, 1) : 0.0;

    $uptime = 0.0;
    $upFile = @file_get_contents('/proc/uptime');
    if ($upFile !== false) {
        $uptime = (float) strtok($upFile, ' ');
    }

    return [
        'load'   => [
            '1'  => round((float) $load[0], 2),
            '5'  => round((float) $load[1], 2),
            '15' => round((float) $load[2], 2),
        ],
        'mem'    => [
            'total'     => $memTotal,
            'used'      => $memUsed,
            'available' => $memAvailKb * 1024,
            'percent'   => $memPct,
        ],
        'disk'   => [
            'total'   => $diskTotal,
            'used'    => $diskUsed,
            'free'    => $diskFree,
            'percent' => $diskPct,
        ],
        'uptime' => $uptime,
        'host'   => gethostname(),
        'php'    => PHP_VERSION,
        'time'   => date('Y-m-d H:i:s'),
    ];
}

function format_bytes(float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}

function format_uptime(float $seconds): string
{
    $days  = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $mins  = floor(($seconds % 3600) / 60);
    $parts = [];
    if ($days > 0) {
        $parts[] = "{$days} hari";
    }
    if ($hours > 0) {
        $parts[] = "{$hours} jam";
    }
    $parts[] = "{$mins} menit";
    return implode(' ', $parts);
}