<?php
declare(strict_types=1);

final class AiClient
{
    public static function test(array $settings, string $apiKey): array
    {
        $started = microtime(true);
        $text = self::complete($settings, $apiKey, 'ตอบสั้น ๆ ว่า pong', [], 16);
        $ms = (int) round((microtime(true) - $started) * 1000);
        if (trim($text) === '') {
            throw new RuntimeException('API ตอบกลับว่าง');
        }
        return ['ok' => true, 'ms' => $ms, 'message' => 'เชื่อมต่อสำเร็จ · ตอบกลับใน ' . $ms . ' ms'];
    }

    public static function chat(array $settings, string $apiKey, string $system, array $history): string
    {
        return self::complete($settings, $apiKey, $system, $history, 700);
    }

    private static function complete(array $settings, string $apiKey, string $system, array $history, int $maxTokens): string
    {
        $endpoint = self::endpoint($settings, $apiKey);
        if ($endpoint['style'] === 'google') {
            $contents = [];
            foreach ($history as $message) {
                $contents[] = [
                    'role' => ($message['role'] ?? '') === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string) $message['content']]],
                ];
            }
            if ($contents === []) {
                $contents[] = ['role' => 'user', 'parts' => [['text' => $system]]];
                $body = [
                    'contents' => $contents,
                    'generationConfig' => ['maxOutputTokens' => $maxTokens],
                ];
            } else {
                $body = [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => $contents,
                    'generationConfig' => ['maxOutputTokens' => $maxTokens],
                ];
            }
        } else {
            $messages = [['role' => 'system', 'content' => $system]];
            foreach ($history as $message) {
                $messages[] = [
                    'role' => ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user',
                    'content' => (string) $message['content'],
                ];
            }
            if (count($messages) === 1) {
                $messages[] = ['role' => 'user', 'content' => 'ping'];
            }
            $body = [
                'model' => $settings['model'],
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ];
        }
        $result = self::request($endpoint['url'], $endpoint['headers'], $body);
        $json = $result['json'];
        if ($endpoint['style'] === 'google') {
            $text = (string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
        } else {
            $text = (string) ($json['choices'][0]['message']['content'] ?? '');
        }
        if ($text === '') {
            throw new RuntimeException('อ่านคำตอบจาก API ไม่ได้');
        }
        return $text;
    }

    private static function endpoint(array $settings, string $apiKey): array
    {
        $base = rtrim((string) $settings['base_url'], '/');
        if (($settings['provider'] ?? '') === 'google') {
            if (!str_contains($base, ':generateContent')) {
                $base .= '/models/' . rawurlencode((string) $settings['model']) . ':generateContent';
            }
            $join = str_contains($base, '?') ? '&' : '?';
            return [
                'url' => $base . $join . 'key=' . rawurlencode($apiKey),
                'headers' => ['Content-Type: application/json'],
                'style' => 'google',
            ];
        }
        if (!str_ends_with($base, '/chat/completions')) {
            $base .= '/chat/completions';
        }
        return [
            'url' => $base,
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            'style' => 'openai',
        ];
    }

    private static function request(string $url, array $headers, array $body): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('เซิร์ฟเวอร์ไม่มีส่วนขยาย cURL สำหรับเรียก API');
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $raw = curl_exec($handle);
        $errno = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($raw === false || $errno !== 0) {
            throw new RuntimeException('เชื่อมต่อ API ไม่สำเร็จ');
        }
        $json = json_decode($raw, true);
        if ($status >= 400 || !is_array($json)) {
            $message = 'API ตอบกลับข้อผิดพลาด';
            if (is_array($json)) {
                $message = (string) ($json['error']['message'] ?? $json['error']['status'] ?? $message);
            }
            throw new RuntimeException($message . ' (HTTP ' . $status . ')');
        }
        return ['json' => $json];
    }
}
