<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Pirms ieļautieties.']);
    exit;
}

function fail(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode(['message' => $message]);
    exit;
}

function taskFile(string $email): string
{
    $safeEmail = preg_replace('/[^A-Za-z0-9._-]/', '_', strtolower($email));
    return __DIR__ . '/data/tasks-' . $safeEmail . '.json';
}

function loadTasks(string $email): array
{
    $path = taskFile($email);
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fail('Unable to load tasks.', 500);
    }

    $tasks = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($tasks)) {
        fail('Stored task data is invalid.', 500);
    }

    return $tasks;
}

function saveTasks(string $email, array $tasks): void
{
    $path = taskFile($email);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        fail('Unable to save tasks.', 500);
    }

    if (file_put_contents($path, json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX) === false) {
        fail('Unable to save tasks.', 500);
    }
}

$email = (string) $_SESSION['user']['email'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['tasks' => loadTasks($email)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('Unsupported request method.', 405);
    }

    $tasks = loadTasks($email);
    $id = (string) ($_POST['id'] ?? '');
    $action = (string) ($_POST['action'] ?? 'add');

    if ($action === 'add') {
        $task = trim((string) ($_POST['task'] ?? ''));
        if ($task === '') {
            fail('Uzdevuma teksts ir nepieciešams.');
        }

        $assignee = trim((string) ($_POST['assignee'] ?? $_SESSION['user']['name'] ?? 'Jūs'));
        $dueAt = trim((string) ($_POST['due_at'] ?? ''));
        $priority = isset($_POST['priority']) && $_POST['priority'] === '1';
        $dueTimestamp = $dueAt === '' ? false : strtotime($dueAt);
        $dueAtValue = $dueTimestamp === false ? null : date('c', $dueTimestamp);

        $id = uniqid('task-', true);
        $tasks[] = [
            'id' => $id,
            'title' => $task,
            'assignee' => $assignee,
            'due_at' => $dueAtValue,
            'priority' => $priority,
            'completed' => false,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        saveTasks($email, $tasks);
        echo json_encode(['message' => 'Uzdevums tika pievienots.', 'task' => $tasks[array_search($id, array_column($tasks, 'id'), true)]]);
        exit;
    }

    if ($id === '' || $action === '') {
        fail('Uzdevuma darbība nav pabeigta.');
    }

    $taskIndex = array_search($id, array_column($tasks, 'id'), true);
    if ($taskIndex === false) {
        fail('Uzdevums tika atrasts.', 404);
    }

    if ($action === 'toggle') {
        $tasks[$taskIndex]['completed'] = !$tasks[$taskIndex]['completed'];
        $tasks[$taskIndex]['updated_at'] = date('c');
        saveTasks($email, $tasks);
        echo json_encode(['message' => 'Uzdevums tika atjaunots.', 'task' => $tasks[$taskIndex]]);
        exit;
    }

    if ($action === 'delete') {
        array_splice($tasks, $taskIndex, 1);
        saveTasks($email, $tasks);
        echo json_encode(['message' => 'Uzdevums tika dzēsts.']);
        exit;
    }

    fail('Zināma uzdevuma darbība.');
} catch (Throwable $exception) {
    fail('Serveris nevarēja apstrādāt jūsu uzdevumus.', 500);
}
