<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header('Location: index.html');
    exit;
}

$user = $_SESSION['user'];
$ownerEmail = strtolower((string) ($user['email'] ?? ''));
$dataDirectory = __DIR__ . '/data';
$usersFile = $dataDirectory . '/users.json';
$sharesFile = $dataDirectory . '/shares-' . hash('sha256', $ownerEmail) . '.json';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$notice = '';
$error = '';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function saveCollaborators(string $filePath, string $ownerEmail, array $collaborators): void
{
    $data = json_encode(
        [
            'owner_email' => $ownerEmail,
            'collaborators' => array_values(array_unique($collaborators)),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    if (file_put_contents($filePath, $data . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Neizdevās saglabāt koplietošanas iestatījumus.');
    }
}

try {
    $usersContents = is_file($usersFile) ? file_get_contents($usersFile) : false;

    if ($usersContents === false) {
        throw new RuntimeException('Neizdevās nolasīt reģistrēto lietotāju sarakstu.');
    }

    $users = json_decode($usersContents, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($users)) {
        throw new RuntimeException('Lietotāju dati nav derīgi.');
    }

    $shareData = ['owner_email' => $ownerEmail, 'collaborators' => []];

    if (is_file($sharesFile)) {
        $shareContents = file_get_contents($sharesFile);

        if ($shareContents === false) {
            throw new RuntimeException('Neizdevās nolasīt koplietošanas iestatījumus.');
        }

        $savedShareData = json_decode($shareContents, true, 512, JSON_THROW_ON_ERROR);

        if (
            is_array($savedShareData)
            && ($savedShareData['owner_email'] ?? null) === $ownerEmail
            && is_array($savedShareData['collaborators'] ?? null)
        ) {
            $shareData['collaborators'] = $savedShareData['collaborators'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $requestToken = (string) ($_POST['csrf_token'] ?? '');

        if (!hash_equals($_SESSION['csrf_token'], $requestToken)) {
            throw new RuntimeException('Lapa ir novecojusi. Atjauno to un mēģini vēlreiz.');
        }

        $action = (string) ($_POST['action'] ?? '');
        $collaborators = $shareData['collaborators'];

        if ($action === 'invite') {
            $inviteEmail = filter_var(
                strtolower(trim((string) ($_POST['email'] ?? ''))),
                FILTER_VALIDATE_EMAIL
            );

            if ($inviteEmail === false) {
                throw new RuntimeException('Ievadi derīgu e-pasta adresi.');
            }

            if ($inviteEmail === $ownerEmail) {
                throw new RuntimeException('Nevari uzaicināt pats sevi.');
            }

            if (!isset($users[$inviteEmail])) {
                throw new RuntimeException('Lietotājs ar šo e-pasta adresi nav reģistrējies.');
            }

            if (in_array($inviteEmail, $collaborators, true)) {
                throw new RuntimeException('Šim lietotājam jau ir piekļuve tavam sarakstam.');
            }

            $collaborators[] = $inviteEmail;
            saveCollaborators($sharesFile, $ownerEmail, $collaborators);
            $shareData['collaborators'] = $collaborators;
            $notice = 'Piekļuve uzdevumu sarakstam piešķirta.';
        } elseif ($action === 'remove') {
            $removeEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
            $collaborators = array_values(array_filter(
                $collaborators,
                static fn ($email): bool => $email !== $removeEmail
            ));

            saveCollaborators($sharesFile, $ownerEmail, $collaborators);
            $shareData['collaborators'] = $collaborators;
            $notice = 'Piekļuve uzdevumu sarakstam noņemta.';
        } else {
            throw new RuntimeException('Nezināma koplietošanas darbība.');
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#315d48">
  <title>Koplietot uzdevumus — To-Do</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <main class="sharing-page">
    <a class="sharing-back" href="dashboard.php">← Atpakaļ pie uzdevumiem</a>
    <h1>Koplietot savu uzdevumu sarakstu</h1>
    <p class="sharing-description">
      Pievieno reģistrēta lietotāja e-pastu, lai viņš varētu skatīt, pievienot,
      rediģēt un dzēst uzdevumus tavā sarakstā. Piekļuve tiek piešķirta uzreiz.
    </p>

    <?php if ($notice !== ''): ?>
      <p class="sharing-message" role="status"><?= escape($notice) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <p class="sharing-message sharing-error" role="alert"><?= escape($error) ?></p>
    <?php endif; ?>

    <form class="sharing-form" method="post">
      <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="invite">
      <label for="invite-email">Uzaicināt pēc e-pasta adreses</label>
      <div class="sharing-input-row">
        <input
          id="invite-email"
          name="email"
          type="email"
          autocomplete="email"
          placeholder="vards@example.com"
          required
        >
        <button class="add-button" type="submit">Uzaicināt</button>
      </div>
      <p class="sharing-hint">Uzaicinātajam jābūt reģistrētam šajā lietotnē.</p>
    </form>

    <section class="collaborator-list" aria-labelledby="collaborators-heading">
      <h2 id="collaborators-heading">Kam ir piekļuve</h2>

      <?php if ($shareData['collaborators'] === []): ?>
        <p class="sharing-hint">Pagaidām nevienam nav piekļuves tavam sarakstam.</p>
      <?php else: ?>
        <?php foreach ($shareData['collaborators'] as $collaboratorEmail): ?>
          <?php if (!is_string($collaboratorEmail)) { continue; } ?>
          <div class="collaborator-row">
            <span><?= escape($collaboratorEmail) ?></span>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="email" value="<?= escape($collaboratorEmail) ?>">
              <button class="remove-collaborator" type="submit">Noņemt piekļuvi</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
