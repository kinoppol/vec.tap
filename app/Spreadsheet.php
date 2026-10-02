<?php
declare(strict_types=1);

final class Spreadsheet
{
    public static function rows(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'csv' || $extension === 'txt') {
            return self::csv($path);
        }
        if ($extension === 'xlsx') {
            return self::xlsx($path);
        }
        throw new RuntimeException('รองรับเฉพาะไฟล์ CSV หรือ Excel .xlsx');
    }

    public static function csvDownload(array $header, array $rows, string $filename): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    private static function csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('อ่านไฟล์ไม่ได้');
        }
        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            return [];
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $rows = [str_getcsv($first)];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return self::clean($rows);
    }

    private static function xlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('เซิร์ฟเวอร์ไม่มี ZipArchive จึงอ่าน .xlsx ไม่ได้ กรุณาบันทึกเป็น CSV');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('เปิดไฟล์ Excel ไม่ได้');
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml)) {
            $xml = simplexml_load_string($sharedXml);
            if ($xml !== false) {
                foreach ($xml->si as $item) {
                    $shared[] = trim(strip_tags($item->asXML() ?: ''));
                }
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!is_string($sheet)) {
            throw new RuntimeException('ไม่พบชีตแรกในไฟล์ Excel');
        }
        $xml = simplexml_load_string($sheet);
        if ($xml === false) {
            throw new RuntimeException('อ่านชีต Excel ไม่ได้');
        }
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is)) {
                    $value = '';
                    foreach ($cell->is->xpath('.//t') ?: [] as $text) {
                        $value .= (string) $text;
                    }
                }
                $index = self::columnIndex((string) $cell['r']);
                if ($index < 0) {
                    $line[] = $value;
                    continue;
                }
                $line[$index] = $value;
            }
            if ($line === []) {
                continue;
            }
            ksort($line);
            $filled = [];
            for ($column = 0; $column <= max(array_keys($line)); $column++) {
                $filled[] = (string) ($line[$column] ?? '');
            }
            $rows[] = $filled;
        }
        return self::clean($rows);
    }

    private static function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/', $reference, $match)) {
            return -1;
        }
        $index = 0;
        foreach (str_split($match[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }
        return $index - 1;
    }

    private static function clean(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $values = array_map(static fn ($value): string => trim((string) $value), $row);
            if (implode('', $values) === '') {
                continue;
            }
            $clean[] = $values;
        }
        return $clean;
    }
}
