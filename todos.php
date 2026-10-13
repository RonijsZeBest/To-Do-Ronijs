<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Vispirms pierakstieties.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function taskFile(string $email): string
{
    $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', strtolower($email));
    return __DIR__ . '/data/tasks-' . $fileName . '.json';
}

function shareFile(string $email): string
{
    return __DIR__ . '/data/shares-' . hash('sha256', strtolower($email)) . '.json';
}

function privateListsFile(string $email): string
{
    return __DIR__ . '/data/lists-' . hash('sha256', strtolower($email)) . '.json';
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
        fail('Saglabātie konta dati nav derīgi.', 500);
    }

    return $users;
}

function readShare(string $ownerEmail): array
{
    $filePath = shareFile($ownerEmail);

    if (!is_file($filePath)) {
        return [];
    }

    $contents = file_get_contents($filePath);

    if ($contents === false) {
        fail('Neizdevās nolasīt koplietošanas iestatījumus.', 500);
    }

    $share = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (
        !is_array($share)
        || ($share['owner_email'] ?? null) !== $ownerEmail
        || !is_array($share['collaborators'] ?? null)
    ) {
        fail('Koplietošanas dati nav derīgi.', 500);
    }

    return $share['collaborators'];
}

function readPrivateLists(string $ownerEmail): array
{
    $filePath = privateListsFile($ownerEmail);

    if (!is_file($filePath)) {
        return [];
    }

    $contents = file_get_contents($filePath);

    if ($contents === false) {
        fail('Neizdevās nolasīt tavus uzdevumu sarakstus.', 500);
    }

    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (
        !is_array($data)
        || ($data['owner_email'] ?? null) !== $ownerEmail
        || !is_array($data['lists'] ?? null)
    ) {
        fail('Uzdevumu sarakstu dati nav derīgi.', 500);
    }

    return $data['lists'];
}

function savePrivateLists(string $ownerEmail, array $lists): void
{
    $filePath = privateListsFile($ownerEmail);
    $data = json_encode(
        [
            'owner_email' => $ownerEmail,
            'lists' => array_values($lists),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    if (file_put_contents($filePath, $data . PHP_EOL, LOCK_EX) === false) {
        fail('Neizdevās saglabāt uzdevumu sarakstu.', 500);
    }
}

function getAuthorizedListId(string $currentEmail, string $requestedId): string
{
    if ($requestedId === $currentEmail) {
        return $currentEmail;
    }

    foreach (readPrivateLists($currentEmail) as $list) {
        if (is_array($list) && ($list['id'] ?? null) === $requestedId) {
            return $requestedId;
        }
    }

    $ownerEmail = strtolower($requestedId);

    if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        fail('Uzdevumu saraksts nav derīgs.');
    }

    if (!in_array($currentEmail, readShare($ownerEmail), true)) {
        fail('Tev nav piekļuves šim uzdevumu sarakstam.', 403);
    }

    if (!isset(readUsers()[$ownerEmail])) {
        fail('Šī saraksta īpašnieks vairs nav reģistrēts.', 404);
    }

    return $ownerEmail;
}

function getAvailableLists(string $currentEmail): array
{
    $users = readUsers();
    $currentUser = $users[$currentEmail] ?? [];
    $lists = [[
        'id' => $currentEmail,
        'name' => (string) ($currentUser['name'] ?? 'Mans saraksts'),
        'is_owner' => true,
        'is_default' => true,
    ]];

    foreach (readPrivateLists($currentEmail) as $list) {
        if (
            !is_array($list)
            || !is_string($list['id'] ?? null)
            || !preg_match('/\Alist-[a-f0-9]{32}\z/', $list['id'])
            || !is_string($list['name'] ?? null)
        ) {
            continue;
        }

        $lists[] = [
            'id' => $list['id'],
            'name' => $list['name'],
            'is_owner' => true,
            'is_default' => false,
        ];
    }

    $shareFiles = glob(__DIR__ . '/data/shares-*.json');

    if ($shareFiles === false) {
        fail('Neizdevās nolasīt koplietotos sarakstus.', 500);
    }

    foreach ($shareFiles as $filePath) {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            fail('Neizdevās nolasīt koplietošanas iestatījumus.', 500);
        }

        $share = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $ownerEmail = $share['owner_email'] ?? null;
        $collaborators = $share['collaborators'] ?? null;

        if (
            !is_string($ownerEmail)
            || !is_array($collaborators)
            || !in_array($currentEmail, $collaborators, true)
            || !isset($users[$ownerEmail])
        ) {
            continue;
        }

        $lists[] = [
            'id' => $ownerEmail,
            'name' => (string) ($users[$ownerEmail]['name'] ?? $ownerEmail),
            'is_owner' => false,
            'is_default' => false,
        ];
    }

    return $lists;
}

function loadTasks(string $email): array
{
    $filePath = taskFile($email);

    if (!is_file($filePath)) {
        return [];
    }

    $contents = file_get_contents($filePath);
    if ($contents === false) {
        fail('Neizdevās ielādēt uzdevumus.', 500);
    }

    $tasks = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($tasks)) {
        fail('Saglabātie uzdevumu dati nav derīgi.', 500);
    }

    return $tasks;
}

function saveTasks(string $email, array $tasks): void
{
    $filePath = taskFile($email);
    $directory = dirname($filePath);

    if (
        !is_dir($directory)
        && !mkdir($directory, 0777, true)
        && !is_dir($directory)
    ) {
        fail('Neizdevās saglabāt uzdevumus.', 500);
    }

    $contents = json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents($filePath, $contents . PHP_EOL, LOCK_EX) === false) {
        fail('Neizdevās saglabāt uzdevumus.', 500);
    }
}

$email = (string) $_SESSION['user']['email'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['action'] ?? '') === 'lists') {
            echo json_encode(['lists' => getAvailableLists($email)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $listId = getAuthorizedListId(
            $email,
            (string) ($_GET['list_id'] ?? $_GET['owner_email'] ?? $email)
        );

        echo json_encode(['tasks' => loadTasks($listId)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Šī pieprasījuma metode netiek atbalstīta.', 405);
    }

    $id = (string) ($_POST['id'] ?? '');
    $action = (string) ($_POST['action'] ?? 'add');

    if ($action === 'create_list') {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = (string) ($_POST['csrf_token'] ?? '');

        if (!is_string($sessionToken) || !hash_equals($sessionToken, $requestToken)) {
            fail('Lapa ir novecojusi. Atjauno to un mēģini vēlreiz.', 403);
        }

        $name = trim((string) ($_POST['name'] ?? ''));

        if (preg_match('/\A.{1,80}\z/us', $name) !== 1) {
            fail('Saraksta nosaukumam jābūt 1 līdz 80 rakstzīmēm garam.');
        }

        $lists = readPrivateLists($email);
        $newList = [
            'id' => 'list-' . bin2hex(random_bytes(16)),
            'name' => $name,
        ];
        $lists[] = $newList;
        savePrivateLists($email, $lists);

        http_response_code(201);
        echo json_encode([
            'message' => 'Uzdevumu saraksts tika izveidots.',
            'list' => $newList,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $listId = getAuthorizedListId(
        $email,
        (string) ($_POST['list_id'] ?? $_POST['owner_email'] ?? $email)
    );
    $tasks = loadTasks($listId);

    if ($action === 'add') {
        $title = trim((string) ($_POST['task'] ?? ''));

        if ($title === '') {
            fail('Lūdzu, ievadi uzdevuma tekstu.');
        }

        $assignee = trim(
            (string) ($_POST['assignee'] ?? $_SESSION['user']['name'] ?? 'Jūs')
        );
        $dueAt = trim((string) ($_POST['due_at'] ?? ''));
        $priority = (string) ($_POST['priority'] ?? 'medium');

        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            fail('Izvēlies derīgu prioritātes līmeni.');
        }

        $dueTimestamp = $dueAt === '' ? false : strtotime($dueAt);
        $dueAtValue = $dueTimestamp === false
            ? null
            : date('c', $dueTimestamp);
        $id = uniqid('task-', true);

        $tasks[] = [
            'id' => $id,
            'title' => $title,
            'assignee' => $assignee,
            'due_at' => $dueAtValue,
            'priority' => $priority,
            'completed' => false,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];

        saveTasks($listId, $tasks);
        $taskIndex = array_search($id, array_column($tasks, 'id'), true);

        echo json_encode([
            'message' => 'Uzdevums tika pievienots.',
            'task' => $tasks[$taskIndex],
        ]);
        exit;
    }

    if ($id === '' || $action === '') {
        fail('Uzdevuma darbība nav pabeigta.');
    }

    $taskIndex = array_search($id, array_column($tasks, 'id'), true);

    if ($taskIndex === false) {
        fail('Uzdevums netika atrasts.', 404);
    }

    switch ($action) {
        case 'update':
            $title = trim((string) ($_POST['task'] ?? ''));

            if ($title === '') {
                fail('Uzdevuma nosaukums nevar būt tukšs.');
            }

            $priority = (string) ($_POST['priority'] ?? 'medium');

            if (!in_array($priority, ['low', 'medium', 'high'], true)) {
                fail('Izvēlies derīgu prioritātes līmeni.');
            }

            $dueAt = trim((string) ($_POST['due_at'] ?? ''));
            $dueTimestamp = $dueAt === '' ? false : strtotime($dueAt);

            if ($dueAt !== '' && $dueTimestamp === false) {
                fail('Ievadi derīgu termiņu.');
            }

            $tasks[$taskIndex]['title'] = $title;
            $tasks[$taskIndex]['assignee'] = trim((string) ($_POST['assignee'] ?? ''));
            $tasks[$taskIndex]['due_at'] = $dueTimestamp === false
                ? null
                : date('c', $dueTimestamp);
            $tasks[$taskIndex]['priority'] = $priority;
            $tasks[$taskIndex]['updated_at'] = date('c');
            saveTasks($listId, $tasks);

            echo json_encode([
                'message' => 'Uzdevums tika atjaunināts.',
                'task' => $tasks[$taskIndex],
            ]);
            exit;

        case 'toggle':
            $tasks[$taskIndex]['completed'] = !$tasks[$taskIndex]['completed'];
            $tasks[$taskIndex]['updated_at'] = date('c');
            saveTasks($listId, $tasks);

            echo json_encode([
                'message' => 'Uzdevums tika atjaunināts.',
                'task' => $tasks[$taskIndex],
            ]);
            exit;

        case 'delete':
            array_splice($tasks, $taskIndex, 1);
            saveTasks($listId, $tasks);

            echo json_encode(['message' => 'Uzdevums tika dzēsts.']);
            exit;

        default:
            fail('Nezināma uzdevuma darbība.');
    }
} catch (Throwable $exception) {
    fail('Serverim neizdevās apstrādāt uzdevumus.', 500);
}
