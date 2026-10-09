<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['user'])) {
    header('Location: index.html');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="lv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#315d48">
  <title>Mani uzdevumi</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="dashboard.php" aria-label="Daily sākums">
        <span class="brand-mark">✓</span>
        <span>daily</span>
      </a>

      <nav aria-label="Galvenā navigācija">
        <a class="nav-item active" href="dashboard.php">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 3v4M16 3v4M4 9h16"/></svg>
          Mani uzdevumi
        </a>
        <a class="nav-item" href="index.html">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
          Atgriezt
        </a>
      </nav>


    <main class="main-content">
      <header class="topbar">
        <div>
          <h1>Sveiks, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>.</h1>
        </div>
        <button class="profile" type="button" aria-label="Ieļautu lietotājs">
          <span><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
      </header>

      <section class="task-section" aria-labelledby="tasks-heading">
        <div class="section-heading">
          <div>
            <p class="eyebrow">Saraksts</p>
            <h2 id="tasks-heading">Mani uzdevumi</h2>
          </div>
          <div class="filters" role="group" aria-label="Filtrēt uzdevumus">
            <button class="filter active" type="button" data-filter="all">Visi</button>
            <button class="filter" type="button" data-filter="active">Aktīvi</button>
            <button class="filter" type="button" data-filter="completed">Pabeigti</button>
          </div>
        </div>

        <form id="task-form" class="task-form">
          <input id="task-input" name="task" type="text" maxlength="160" placeholder="Pievaddā jaunu uzdevumu…" autocomplete="off" required>
          <input id="task-assignee" name="assignee" type="text" maxlength="80" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Uzdevuma veicinājs">
          <input id="task-due" name="due_at" type="datetime-local" aria-label="Uzdevuma termiņš">
          <label class="priority-field"><input name="priority" type="checkbox" value="1"><span>Prioritāte</span></label>
          <button class="add-button" type="submit"><span>+</span> Pievieno uzdevumu</button>
        </form>

        <div class="task-table-wrap">
          <table class="task-table">
            <thead><tr><th>#</th><th>Uzdevums</th><th>Stats</th><th>Vadītājs</th><th>Termiņš</th><th>Prioritāte</th><th></th></tr></thead>
            <tbody id="task-list" aria-live="polite"></tbody>
          </table>
        </div>
        <p id="empty-state" class="empty-state" hidden>Jūsu saraksts ir tukšs. Pievienojiet kaut ko, lai sāktu darbus.</p>
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
      <td><button class="task-check" type="button" aria-label="Pabeidziet uzdevumu"></button><button class="task-delete" type="button" aria-label="Dzēš uzdevumu">×</button></td>
    </tr>
  </template>

  <script src="dashboard.js?v=2"></script>
</body>
</html>
