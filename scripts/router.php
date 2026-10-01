<?php
declare(strict_types=1);

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';
if ($uri === '') {
    $uri = '/';
}

$first = explode('/', trim($uri, '/'))[0] ?? '';
if (in_array($first, ['app', 'config', 'database', 'storage'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden\n";
    return;
}

$root = getcwd();
$target = realpath($root . $uri);
$rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if ($uri !== '/' && $target !== false && is_file($target) && str_starts_with($target, $rootPrefix)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
