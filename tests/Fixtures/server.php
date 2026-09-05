<?php

declare(strict_types=1);

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

function requestHeaders(): array
{
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = $value;
        }
    }

    return $headers;
}

function jsonBody(): array
{
    return json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
}

switch (true) {
    case $path === 'ping':
        echo '{"pong":true}';
        break;

    case $path === 'plain/items' && $method === 'GET':
        echo json_encode([['id' => 1], ['id' => 2]]);
        break;

    case $path === 'enveloped/items' && $method === 'GET':
        echo json_encode(['data' => [['id' => 1], ['id' => 2]]]);
        break;

    case $path === 'echo':
        echo json_encode(['query' => $_GET, 'headers' => requestHeaders(), 'method' => $method, 'body' => jsonBody()]);
        break;

    case $path === 'auth/bearer':
        $ok = (requestHeaders()['Authorization'] ?? '') === 'Bearer secret-token';
        http_response_code($ok ? 200 : 401);
        echo json_encode(['ok' => $ok]);
        break;

    case $path === 'auth/basic':
        $ok = ($_SERVER['PHP_AUTH_USER'] ?? '') === 'user' && ($_SERVER['PHP_AUTH_PW'] ?? '') === 'pass';
        http_response_code($ok ? 200 : 401);
        echo json_encode(['ok' => $ok]);
        break;

    case $path === 'auth/header':
        $ok = (requestHeaders()['X-Api-Key'] ?? '') === 'k123';
        http_response_code($ok ? 200 : 401);
        echo json_encode(['ok' => $ok]);
        break;

    case $path === 'flaky':
        $key = $_GET['key'] ?? 'default';
        $file = sys_get_temp_dir()."/laravel-rest-flaky-{$key}";
        $count = (int) @file_get_contents($file);
        file_put_contents($file, (string) ($count + 1));
        if ($count < 2) {
            http_response_code(503);
            echo '{"error":"unavailable"}';
        } else {
            echo json_encode(['id' => 1, 'attempts' => $count + 1]);
        }
        break;

    case $path === 'retry-after':
        $key = $_GET['key'] ?? 'default';
        $file = sys_get_temp_dir()."/laravel-rest-ra-{$key}";
        if (@file_get_contents($file) === false) {
            file_put_contents($file, '1');
            http_response_code(429);
            header('Retry-After: 1');
            echo '{"error":"slow down"}';
        } else {
            echo '{"id":1}';
        }
        break;

    case $path === 'malformed':
        echo '{oops';
        break;

    case $path === 'invalid' && $method === 'POST':
        http_response_code(422);
        echo json_encode(['errors' => ['name' => ['Required.']]]);
        break;

    case preg_match('#^posts/(\d+)/comments$#', $path, $m) === 1 && $method === 'GET':
        echo json_encode([['id' => 10, 'postId' => (int) $m[1]], ['id' => 11, 'postId' => (int) $m[1]]]);
        break;

    case $path === 'comments' && $method === 'GET':
        $all = [['id' => 10, 'postId' => 1], ['id' => 11, 'postId' => 1], ['id' => 12, 'postId' => 2]];
        $filtered = isset($_GET['postId'])
            ? array_values(array_filter($all, fn ($c) => $c['postId'] === (int) $_GET['postId']))
            : $all;
        echo json_encode($filtered);
        break;

    case preg_match('#^users/(\d+)$#', $path, $m) === 1 && $method === 'GET':
        echo json_encode(['id' => (int) $m[1], 'name' => 'User '.$m[1]]);
        break;

    default:
        http_response_code(404);
        echo '{"error":"not found"}';
}
