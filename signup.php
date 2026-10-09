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
        fail('Unable to read account data.', 500);
    }

    $users = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    return is_array($users) ? $users : [];
}

function saveUsers(array $users): void
{
    $path = __DIR__ . '/data/users.json';
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        fail('Unable to create account storage.', 500);
    }

    $contents = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $contents . PHP_EOL, LOCK_EX) === false) {
        fail('Unable to save account data.', 500);
    }
}

try {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($name === '' || $name === ' ') {
        fail('Ievadiet jūsu pilno vardu.');
    }
    if ($email === false) {
        fail('Ievadiet derīgu e-pasta adresi.');
    }
    if (strlen($password) < 6) {
        fail('Parolē jābūt viss maz 6 rakstzīmēm.');
    }
    if ($password !== $confirm) {
        fail('Parole neapstiprinās.');
    }

    $users = readUsers();
    $normalizedEmail = strtolower($email);
    if (isset($users[$normalizedEmail])) {
        fail('Konts ar šādu e-pasta adresi jau eksistē.', 409);
    }

    $users[$normalizedEmail] = [
        'name' => $name,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => date('c'),
    ];
    saveUsers($users);

    http_response_code(201);
    echo json_encode(['message' => 'Konts tika izveidots.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    fail('Serveris nevarēja apstrādāt jūsu pieprasijumu.', 500);
}
