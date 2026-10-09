<?php

declare(strict_types=1);

$base = 'http://127.0.0.1/To-Do%20list/';
$cookieFile = tempnam(sys_get_temp_dir(), 'daily-');
$email = 'todo-test-' . time() . '@example.com';

function request(string $url, string $method = 'GET', string $body = '', string $cookie = ''): array
{
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $cookie === '' ? '' : "Cookie: $cookie\r\n" . 'Content-Type: application/x-www-form-urlencoded',
            'content' => $body,
            'timeout' => 10,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    if ($response === false) {
        return [0, 'Request failed'];
    }
    if (isset($http_response_header[0]) && preg_match('~HTTP/\S+\s+(\d{3})~', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
    }
    return [$status, $response];
}

function cookieFromResponse(array $headers): string
{
    foreach ($headers as $header) {
        if (str_starts_with($header, 'Set-Cookie:')) {
            if (preg_match('/(?:^|;\s*)PHPSESSID=([^;]+)/i', $header, $match)) {
                return $match[1];
            }
        }
    }
    return '';
}

$signup = request($base . 'signup.php', 'POST', http_build_query([
    'name' => 'Test User',
    'email' => $email,
    'password' => 'test123',
    'confirm' => 'test123',
]));

$loginContext = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => 'Content-Type: application/x-www-form-urlencoded',
    'content' => http_build_query(['email' => $email, 'password' => 'test123']),
    'timeout' => 10,
]]);
$loginResponse = @file_get_contents($base . 'login.php', false, $loginContext);
$loginStatus = 0;
if (isset($http_response_header[0]) && preg_match('~HTTP/\S+\s+(\d{3})~', $http_response_header[0], $matches)) {
    $loginStatus = (int) $matches[1];
}
$cookie = cookieFromResponse($http_response_header);

$dashboard = request($base . 'dashboard.php', 'GET', '', $cookie);
$add = request($base . 'todos.php', 'POST', http_build_query([
    'action' => 'add',
    'task' => 'Verify the to-do list',
]), $cookie);
$addData = json_decode($add[1], true);
$taskId = (string) ($addData['task']['id'] ?? '');
$toggle = request($base . 'todos.php', 'POST', http_build_query([
    'action' => 'toggle',
    'id' => $taskId,
]), $cookie);
$list = request($base . 'todos.php', 'GET', '', $cookie);
$delete = request($base . 'todos.php', 'POST', http_build_query([
    'action' => 'delete',
    'id' => $taskId,
]), $cookie);

$results = [
    'signup' => $signup[0],
    'login' => $loginStatus,
    'dashboard' => $dashboard[0],
    'add' => $add[0],
    'toggle' => $toggle[0],
    'list' => $list[0],
    'delete' => $delete[0],
];
foreach ($results as $name => $status) {
    echo "$name=$status\n";
}

@unlink($cookieFile);
@unlink(__DIR__ . '/data/users.json');
@unlink(__DIR__ . '/data/tasks-' . strtolower($email) . '.json');

if (in_array(0, $results, true)) {
    exit(1);
}
