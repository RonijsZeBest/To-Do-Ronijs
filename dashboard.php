<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['user'])) {
    header('Location: index.html');
    exit;
}

$user = $_SESSION['user'];
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="lv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#315d48">
  <title>Mani uzdevumi — To-Do</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="dashboard.php" aria-label="To-Do sākumlapa">
        <span>To-Do</span>
      </a>

      <nav aria-label="Galvenā navigācija">
        <a class="nav-item active" href="dashboard.php">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 3v4M16 3v4M4 9h16"/></svg>
          Mani uzdevumi
        </a>
        <a class="nav-item" href="share.php">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 10.7 6.6-4.4m-6.6 7 6.6 4.4"/></svg>
          Koplietot sarakstu
        </a>
        <a class="nav-item" href="logout.php">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
          Izrakstīties
        </a>
      </nav>

    </aside>

    <main class="main-content">
      <header class="topbar">
        <div class="account-menu">
          <button
            id="profile-button"
            class="profile"
            type="button"
            aria-label="Lietotāja profils"
            aria-expanded="false"
            aria-controls="profile-options"
          >
            <span><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
          </button>
          <div id="profile-options" class="profile-options" hidden>
            <button
              id="delete-account-button"
              class="delete-account-button"
              type="button"
              data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >
              Dzēst kontu
            </button>
          </div>
        </div>
      </header>

      <section class="task-section" aria-labelledby="tasks-heading">
        <div class="task-list-controls">
          <div class="task-list-picker">
            <label for="task-list-select">Uzdevumu saraksts</label>
            <select id="task-list-select" aria-label="Izvēlies uzdevumu sarakstu">
              <option value="<?= htmlspecialchars(strtolower($user['email']), ENT_QUOTES, 'UTF-8') ?>">Mans saraksts</option>
            </select>
          </div>
          <button id="create-list-button" class="create-list-button" type="button">
            + Jauns saraksts
          </button>
        </div>

        <dialog id="create-list-dialog" class="create-list-dialog">
          <form id="create-list-form">
            <h2>Izveidot uzdevumu sarakstu</h2>
            <p class="sharing-hint">Šis saraksts būs redzams tikai tev.</p>
            <label for="new-list-name">Saraksta nosaukums</label>
            <input id="new-list-name" name="name" type="text" maxlength="80" required>
            <div class="dialog-actions">
              <button id="cancel-create-list" class="dialog-cancel" type="button">Atcelt</button>
              <button class="add-button" type="submit">Izveidot</button>
            </div>
          </form>
        </dialog>

        <div class="section-heading">
          <div>
            <h2 id="tasks-heading">Mani uzdevumi</h2>
          </div>
          <div class="filters" role="group" aria-label="Filtrēt uzdevumus">
            <button class="filter active" type="button" data-filter="all">Visi</button>
            <button class="filter" type="button" data-filter="active">Aktīvi</button>
            <button class="filter" type="button" data-filter="completed">Pabeigti</button>
          </div>
        </div>

        <form id="task-form" class="task-form">
          <div class="task-field">
            <label for="task-input">Uzdevums</label>
            <input id="task-input" name="task" type="text" maxlength="160" placeholder="Ieraksti uzdevumu…" autocomplete="off" required>
          </div>
          <div class="task-field">
            <label for="task-assignee">Atbildīgais</label>
            <input id="task-assignee" name="assignee" type="text" maxlength="80" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="task-field">
            <label for="task-due">Termiņš</label>
            <input id="task-due" name="due_at" type="date" lang="lv-LV">
          </div>
          <div class="task-field">
            <label for="task-priority">Prioritāte</label>
            <select id="task-priority" name="priority">
              <option value="low">Zema</option>
              <option value="medium" selected>Vidēja</option>
              <option value="high">Augsta</option>
            </select>
          </div>
          <button class="add-button" type="submit"><span>+</span> Pievienot uzdevumu</button>
        </form>

        <div class="task-table-wrap">
          <table class="task-table">
            <thead><tr><th>#</th><th>Uzdevums</th><th>Statuss</th><th>Atbildīgais</th><th>Termiņš</th><th>Prioritāte</th><th></th></tr></thead>
            <tbody id="task-list" aria-live="polite"></tbody>
          </table>
        </div>
        <p id="empty-state" class="empty-state" hidden>Uzdevumu saraksts ir tukšs. Pievieno uzdevumu, lai sāktu!</p>
      </section>
    </main>
  </div>

  <template id="task-template">
    <tr class="task-item">
      <td class="task-number"></td>
      <td><span class="task-title"></span></td>
      <td><span class="task-status"></span></td>
      <td><span class="task-assignee"></span></td>
      <td><time class="task-date"></time></td>
      <td><span class="task-priority"></span></td>
      <td>
        <button class="task-check" type="button" aria-label="Atzīmēt uzdevumu kā pabeigtu"></button>
        <button class="task-edit" type="button" aria-label="Rediģēt uzdevumu">Rediģēt</button>
        <button class="task-save" type="button" hidden>Saglabāt</button>
        <button class="task-cancel" type="button" hidden>Atcelt</button>
        <button class="task-delete" type="button" aria-label="Dzēst uzdevumu">×</button>
      </td>
    </tr>
  </template>

  <script src="dashboard.js?v=8"></script>
</body>
</html>
