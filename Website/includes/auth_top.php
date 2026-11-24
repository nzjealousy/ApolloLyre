<?php
// Expects $page ("login" or "register") and $title. Opens the card; auth_bottom.php closes it.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body class="auth-layout" data-page="<?= e($page) ?>">

  <aside class="auth-aside">
    <a class="auth-logo" href="index.php" aria-label="Apollo Lyre home">
      <img src="Assets/apollo-lyre-icon.png" width="703" height="539" alt="">
      <span>Apollo Lyre</span>
    </a>
    <p class="auth-tagline">Your listening, neatly kept.</p>
    <p class="auth-blurb">A music manager built on the Spotify Web API. Save songs, build playlists and see your listening at a glance.</p>
  </aside>

  <main class="auth-main">
    <div class="auth-card">
      <nav class="auth-tabs" aria-label="Account">
        <a href="login.php" data-nav="login">Log in</a>
        <a href="register.php" data-nav="register">Sign up</a>
      </nav>

