<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

function fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function restoreDeletedFiles(array $files): bool
{
    foreach ($files as $filePath => $contents) {
        if (file_put_contents($filePath, $contents, LOCK_EX) === false) {
            return false;
        }
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Šī pieprasījuma metode netiek atbalstīta.', 405);
}

$user = $_SESSION['user'] ?? null;
$sessionToken = $_SESSION['csrf_token'] ?? '';
$requestToken = (string) ($_POST['csrf_token'] ?? '');

if (!is_array($user) || !is_string($user['email'] ?? null)) {
    fail('Lai dzēstu kontu, vispirms pieraksties.', 401);
}

if (!is_string($sessionToken) || !hash_equals($sessionToken, $requestToken)) {
    fail('Pieprasījums ir nederīgs. Atjauno lapu un mēģini vēlreiz.', 403);
}

try {
    $email = strtolower($user['email']);
    $usersFile = __DIR__ . '/data/users.json';

    if (!is_file($usersFile)) {
        fail('Neizdevās atrast konta datus.', 500);
    }

    $usersContents = file_get_contents($usersFile);

    if ($usersContents === false) {
        fail('Neizdevās nolasīt konta datus.', 500);
    }

    $users = json_decode($usersContents, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($users) || !isset($users[$email])) {
        fail('Konts netika atrasts.', 404);
    }

    unset($users[$email]);
    $updatedUsers = json_encode(
        $users,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $dataDirectory = __DIR__ . '/data';
    $safeEmail = preg_replace('/[^A-Za-z0-9._-]/', '_', $email);
    $filesToDelete = [
        $dataDirectory . '/tasks-' . $safeEmail . '.json',
        $dataDirectory . '/lists-' . hash('sha256', $email) . '.json',
        $dataDirectory . '/shares-' . hash('sha256', $email) . '.json',
    ];
    $listsFile = $dataDirectory . '/lists-' . hash('sha256', $email) . '.json';

    if (is_file($listsFile)) {
        $listsContents = file_get_contents($listsFile);

        if ($listsContents === false) {
            fail('Neizdevās nolasīt konta uzdevumu sarakstus.', 500);
        }

        $listData = json_decode($listsContents, true, 512, JSON_THROW_ON_ERROR);

        if (
            !is_array($listData)
            || ($listData['owner_email'] ?? null) !== $email
            || !is_array($listData['lists'] ?? null)
        ) {
            fail('Konta uzdevumu sarakstu dati nav derīgi.', 500);
        }

        foreach ($listData['lists'] as $list) {
            if (!is_array($list)) {
                continue;
            }

            $listId = $list['id'] ?? null;

            if (
                !is_string($listId)
                || preg_match('/\Alist-[a-f0-9]{32}\z/', $listId) !== 1
            ) {
                continue;
            }

            $filesToDelete[] = $dataDirectory . '/tasks-' . $listId . '.json';
        }
    }

    $fileBackups = [];

    foreach (array_unique($filesToDelete) as $filePath) {
        if (!is_file($filePath)) {
            continue;
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            fail('Neizdevās sagatavot konta datu dzēšanu.', 500);
        }

        $fileBackups[$filePath] = $contents;
    }

    $deletedFiles = [];

    foreach (array_keys($fileBackups) as $filePath) {
        if (!unlink($filePath)) {
            $restored = restoreDeletedFiles($deletedFiles);

            if (!$restored) {
                fail('Neizdevās pabeigt konta datu dzēšanu vai atjaunot iepriekšējos datus.', 500);
            }

            fail('Neizdevās dzēst konta uzdevumu datus.', 500);
        }

        $deletedFiles[$filePath] = $fileBackups[$filePath];
    }

    if (file_put_contents($usersFile, $updatedUsers . PHP_EOL, LOCK_EX) === false) {
        $restoredUsers = file_put_contents($usersFile, $usersContents, LOCK_EX) !== false;
        $restoredData = restoreDeletedFiles($deletedFiles);

        if (!$restoredUsers || !$restoredData) {
            fail('Neizdevās dzēst kontu vai atjaunot visus iepriekšējos datus.', 500);
        }

        fail('Neizdevās saglabāt konta dzēšanu.', 500);
    }

    $_SESSION = [];
    session_destroy();

    echo json_encode(
        ['message' => 'Konts un tā uzdevumi tika dzēsti.'],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $exception) {
    fail('Serverim neizdevās dzēst kontu.', 500);
}
