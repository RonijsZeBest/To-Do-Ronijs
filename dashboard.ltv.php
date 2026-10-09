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
  <title>Manas</title>
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <nav aria-label="Galvenā">
        <a class="nav-item active" href="dashboard.php">Manas</a>
        <a class="nav-item" href="logout.php">Izlogoties</a>
      </nav>
      <div class="sidebar-card"><span class="sidebar-card-icon">✦</span><strong>Rāda savu progresu.</strong><p>Mažiņš solis veido lielāku.</p></div>
    </aside>
    <main class="main-content">
      <header class="topbar">
        <div><p class="eyebrow">Jūsu diena</p><h1>Labu dienu, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>.</h1></div>
        <div class="profile" aria-label="Jūsu profils"><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
      </header>
      <section class="hero">
        <div><p class="hero-label">Šodien</p><h2>Ko ir nepieciešams veikt?</h2><p>Uzturiet svarīgās lietas redzamās un pārvietojieties vienu uzdevumu vienā reizē.</p></div>
        <div class="progress-ring" aria-label="Uzdevumu progress">
          <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="ring-track" cx="60" cy="60" r="50"></circle><circle class="ring-value" id="progress-ring" cx="60" cy="60" r="50"></circle></svg>
          <div><strong id="progress-count">0</strong><span>uzdevumi</span></div>
        </div>
      </section>
      <section class="task-section" aria-labelledby="tasks-heading">
        <div class="section-heading">
          <div><p class="eyebrow">Saraksts</p><h2 id="tasks-heading">Manas</h2></div>
          <div class="filters" role="group" aria-label="Filtrēt">
            <button class="filter active" type="button" data-filter="all">Visas</button>
            <button class="filter" type="button" data-filter="active">Aktīvās</button>
            <button class="filter" type="button" data-filter="completed">Pabeigti</button>
          </div>
        </div>
        <form id="task-form" class="task-form">
          <input id="task-input" name="task" type="text" maxlength="160" placeholder="Pievienot uzdevumu…" autocomplete="off" required>
          <button class="add-button" type="submit"><span>+</span> Pievienot</button>
        </form>
        <div id="task-list" class="task-list" aria-live="polite"></div>
        <p id="empty-state" class="empty-state" hidden>Jūsu saraksts ir tukšs. Pievienojiet kaut ko, lai sāktu darbus.</p>
      </section>
    </main>
  </div>
  <template id="task-template">
    <article class="task-item">
      <button class="task-check" type="button" aria-label="Atzīmēt uzdevumu"></button>
      <div class="task-content"><p class="task-title"></p><time class="task-date"></time></div>
      <button class="task-delete" type="button" aria-label="Dzēst uzdevumu">×</button>
    </article>
  </template>
  <script src="dashboard.js"></script>
</body>
</html>
