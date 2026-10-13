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
    $filePath = __DIR__ . '/data/users.json';

    if (!is_file($filePath)) {
        return [];
    }

    $contents = file_get_contents($filePath);

    if ($contents === false) {
        fail('Neizdevās nolasīt konta datus.', 500);
    }

    $users = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($users)) {
        return [];
    }

    return $users;
}

function saveUsers(array $users): void
{
    $filePath = __DIR__ . '/data/users.json';
    $directory = dirname($filePath);

    if (
        !is_dir($directory)
        && !mkdir($directory, 0777, true)
        && !is_dir($directory)
    ) {
        fail('Neizdevās izveidot konta datu krātuvi.', 500);
    }

    $contents = json_encode(
        $users,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    if (file_put_contents($filePath, $contents . PHP_EOL, LOCK_EX) === false) {
        fail('Neizdevās saglabāt konta datus.', 500);
    }
}

try {
    $name = trim((string) ($_POST['name'] ?? ''));
    $emailInput = trim((string) ($_POST['email'] ?? ''));
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($name === '') {
        fail('Ievadi savu vārdu un uzvārdu.');
    }

    if ($email === false) {
        fail('Ievadi derīgu e-pasta adresi.');
    }

    if (strlen($password) < 6) {
        fail('Parolei jābūt vismaz 6 rakstzīmes garai.');
    }

    if ($password !== $confirm) {
        fail('Paroles nesakrīt.');
    }

    $users = readUsers();
    $normalizedEmail = strtolower($email);

    if (isset($users[$normalizedEmail])) {
        fail('Konts ar šo e-pasta adresi jau pastāv.', 409);
    }

    $users[$normalizedEmail] = [
        'name' => $name,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c'),
    ];
    saveUsers($users);

    http_response_code(201);
    echo json_encode(
        ['message' => 'Konts veiksmīgi izveidots.'],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    fail('Serverim neizdevās apstrādāt pieprasījumu.', 500);
}
