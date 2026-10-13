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
  <title>My Tasks — To-Do</title>
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="dashboard.php">To-Do</a>

      <nav>
        <a href="dashboard.php">My tasks</a>
        <a href="logout.php">Sign out</a>
      </nav>
    </aside>

    <main class="main-content">
      <header>
        <h1><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h1>
      </header>

      <section>
        <h2>What needs your attention?</h2>
        <div id="task-list"></div>
      </section>
    </main>
  </div>

  <script src="dashboard.js"></script>
</body>
</html>
