<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function readUsers(): array
{
    $path = __DIR__ . '/data/users.json';
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fail('Neizdevās nolasīt konta datus.', 500);
    }

    $users = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    return is_array($users) ? $users : [];
}

try {
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');

    if ($email === false || $password === '') {
        fail('Ievadi derīgu e-pasta adresi un paroli.');
    }

    $users = readUsers();
    $normalizedEmail = strtolower($email);
    $user = $users[$normalizedEmail] ?? null;

    if ($user === null || !is_string($user['password_hash'] ?? null) || !password_verify($password, $user['password_hash'])) {
        fail('E-pasta adrese vai parole nav pareiza.', 401);
    }

    session_start();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'email' => $normalizedEmail,
        'name' => (string) ($user['name'] ?? 'To-Do lietotājs'),
    ];

    echo json_encode(['message' => 'Pierakstīšanās izdevās.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    fail('Serverim neizdevās apstrādāt pieprasījumu.', 500);
}
