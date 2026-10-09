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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#315d48">
  <title>My Tasks — Daily</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="dashboard.php"><span class="brand-mark">✓</span><span>daily</span></a>
      <nav aria-label="Main navigation">
        <a class="nav-item active" href="dashboard.php">My tasks</a>
        <a class="nav-item" href="logout.php">Sign out</a>
      </nav>
      <div class="sidebar-card"><span class="sidebar-card-icon">✦</span><strong>Make progress visible.</strong><p>Small steps create a calmer day.</p></div>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <div><p class="eyebrow">Your day</p><h1>Good to see you, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
        <div class="profile" aria-label="Signed in user"><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
      </header>

      <section class="hero">
        <div><p class="hero-label">Today</p><h2>What needs your attention?</h2><p>Keep the important things in sight and move forward one task at a time.</p></div>
        <div class="progress-ring" aria-label="Task progress">
          <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="ring-track" cx="60" cy="60" r="50"></circle><circle class="ring-value" id="progress-ring" cx="60" cy="60" r="50"></circle></svg>
          <div><strong id="progress-count">0</strong><span>tasks</span></div>
        </div>
      </section>

      <section class="task-section" aria-labelledby="tasks-heading">
        <div class="section-heading">
          <div><p class="eyebrow">Saraksts</p><h2 id="tasks-heading">Mani darbi</h2></div>
          <div class="filters" role="group" aria-label="Filter tasks">
            <button class="filter active" type="button" data-filter="all">Visi</button>
            <button class="filter" type="button" data-filter="active">Aktīvi</button>
            <button class="filter" type="button" data-filter="completed">Pabeigti</button>
          </div>
        </div>
        <form id="task-form" class="task-form">
          <input id="task-input" name="task" type="text" maxlength="160" placeholder="Add a new task…" autocomplete="off" required>
          <button class="add-button" type="submit"><span>+</span> Pievienot uzdevumu</button>
        </form>
        <div id="task-list" class="task-list" aria-live="polite"></div>
        <p id="empty-state" class="empty-state" hidden>Jūsu saraksts ir tukšs. Pievienojiet kaut ko, lai sāktu darbus.</p>
      </section>
    </main>
  </div>

  <template id="task-template">
    <article class="task-item">
      <button class="task-check" type="button" aria-label="Mark task complete"></button>
      <div class="task-content"><p class="task-title"></p><time class="task-date"></time></div>
      <button class="task-delete" type="button" aria-label="Delete task">×</button>
    </article>
  </template>
  <script src="dashboard.js"></script>
</body>
</html>
