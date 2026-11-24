<?php
// Expects $page (nav key) and $title before it is included. Opens <main>; app_bottom.php closes it.
require_once __DIR__ . '/db.php';
$user = require_login();
$initial = strtoupper(mb_substr($user['username'], 0, 1));
$nav = [
    ['dashboard', 'Dashboard', '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>'],
    ['library', 'Music Library', '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>'],
    ['playlists', 'Playlists', '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>'],
    ['analytics', 'Listening Analytics', '<path d="M5 20V11M12 20V4M19 20v-6"/>'],
    ['profile', 'Profile', '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>'],
    ['logout', 'Log out', '<path d="M9 21H5V3h4"/><path d="M16 17l5-5-5-5M21 12H9"/>'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body class="app" data-page="<?= e($page) ?>">

  <aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="dashboard.php" aria-label="Apollo Lyre dashboard">
      <img src="Assets/apollo-lyre-icon.png" width="703" height="539" alt="">
      <span>Apollo Lyre</span>
    </a>

    <nav aria-label="Main">
      <ul>
<?php foreach ($nav as [$key, $label, $icon]): ?>
        <li><a href="<?= $key ?>.php" data-nav="<?= $key ?>"<?= $key === 'logout' ? ' data-confirm-link="Log out of Apollo Lyre?" data-confirm-label="Log out"' : '' ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg><?= $label ?></a></li>
<?php endforeach; ?>
      </ul>
    </nav>
  </aside>

  <div class="main">
    <div class="topbar">
      <button type="button" class="btn-icon menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>

      <form class="topbar-search" action="search.php" method="get" role="search">
        <label class="sr-only" for="q">Search Spotify for songs and artists</label>
        <input type="search" id="q" name="q" value="<?= $page === 'search' ? e((string) ($_GET['q'] ?? '')) : '' ?>" placeholder="Search Spotify for songs and artists" autocomplete="off" autocapitalize="none" spellcheck="false" enterkeyhint="search">
      </form>

      <a class="user-chip" href="profile.php">
        <span class="avatar" aria-hidden="true"><?= e($initial) ?></span>
        <span><?= e($user['username']) ?></span>
      </a>
    </div>

    <main>
