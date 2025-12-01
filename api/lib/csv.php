<?php
declare(strict_types=1);

/**
 * CSV file handling library
 */

function ensure_csv(string $path, array $headers): void {
    if (!file_exists($path)) {
        $fh = fopen($path, 'w');
        if ($fh) {
            fputcsv($fh, $headers);
            fclose($fh);
        }
    }
}

function load_rows(string $path): array {
    if (!file_exists($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];
    $rows = [];
    $headers = fgetcsv($fh);
    if (!$headers) {
        fclose($fh);
        return [];
    }
    while (($line = fgetcsv($fh)) !== false) {
        $row = [];
        foreach ($headers as $i => $h) {
            $val = $line[$i] ?? '';
            $row[$h] = is_string($val) ? $val : '';
        }
        if (count(array_filter($row, fn($v) => $v !== '')) === 0) continue;
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function save_rows(string $path, array $headers, array $rows): bool {
    $tmp = $path . '.tmp';
    $fh = fopen($tmp, 'w');
    if (!$fh) return false;
    fputcsv($fh, $headers);
    foreach ($rows as $r) {
        $out = [];
        foreach ($headers as $h) {
            $out[] = isset($r[$h]) && is_string($r[$h]) ? $r[$h] : '';
        }
        fputcsv($fh, $out);
    }
    fclose($fh);
    return rename($tmp, $path);
}
