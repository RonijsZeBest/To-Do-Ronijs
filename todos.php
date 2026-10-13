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
        echo json_encode(['tasks' => loadTasks($email)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Šī pieprasījuma metode netiek atbalstīta.', 405);
    }

    $tasks = loadTasks($email);
    $id = (string) ($_POST['id'] ?? '');
    $action = (string) ($_POST['action'] ?? 'add');

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

        saveTasks($email, $tasks);
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
        case 'toggle':
            $tasks[$taskIndex]['completed'] = !$tasks[$taskIndex]['completed'];
            $tasks[$taskIndex]['updated_at'] = date('c');
            saveTasks($email, $tasks);

            echo json_encode([
                'message' => 'Uzdevums tika atjaunināts.',
                'task' => $tasks[$taskIndex],
            ]);
            exit;

        case 'delete':
            array_splice($tasks, $taskIndex, 1);
            saveTasks($email, $tasks);

            echo json_encode(['message' => 'Uzdevums tika dzēsts.']);
            exit;

        default:
            fail('Nezināma uzdevuma darbība.');
    }
} catch (Throwable $exception) {
    fail('Serverim neizdevās apstrādāt uzdevumus.', 500);
}
