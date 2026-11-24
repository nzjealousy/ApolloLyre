<?php
require __DIR__ . '/includes/db.php';
$user = require_login();

$error = $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_ok()) {
        $error = 'Session expired. Reload the page and try again.';
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM users WHERE user_id = ?')->execute([$user['id']]);
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    } elseif ($action === 'disconnect') {
        db()->prepare('DELETE FROM spotify_account WHERE user_id = ?')->execute([$user['id']]);
        header('Location: profile.php?spotify=disconnected');
        exit;
    } elseif ($action === 'save') {
        $name = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 30) {
            $error = 'Name must be 3 to 30 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $error = 'Enter a valid email address.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            $st = db()->prepare('SELECT 1 FROM users WHERE (name = ? OR email = ?) AND user_id <> ?');
            $st->execute([$name, $email, $user['id']]);
            if ($st->fetch()) {
                $error = 'That name or email is already registered.';
            } else {
                db()->prepare('UPDATE users SET name = ?, email = ? WHERE user_id = ?')->execute([$name, $email, $user['id']]);
                if ($password !== '') {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                header('Location: profile.php?saved=1');
                exit;
            }
        }
    }
}
if (isset($_GET['saved'])) {
    $ok = 'Changes saved.';
}
$spotifyMsg = [
    'connected' => ['Spotify connected.', false],
    'disconnected' => ['Spotify disconnected.', false],
    'error' => ['Spotify connection failed. Try again.', true],
    'config' => ['Spotify is not configured yet. Add the Client ID and secret to includes/config.php.', true],
][$_GET['spotify'] ?? ''] ?? null;

$listeners = db()->prepare('SELECT name AS username FROM users WHERE user_id <> ? ORDER BY created_at DESC LIMIT 5');
$listeners->execute([$user['id']]);
$listeners = $listeners->fetchAll();

$page = 'profile';
$title = 'Profile';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="page-head">
        <div>
          <h1>Profile</h1>
          <p>Manage your account and Spotify connection.</p>
        </div>
      </div>

      <div class="stack profile-body">

        <section class="panel" aria-label="Your profile">
          <div class="profile-head">
            <span class="avatar avatar-lg" aria-hidden="true"><?= e($initial) ?></span>
            <div class="grow">
              <h2><?= e($user['username']) ?></h2>
              <p class="muted"><?= e($user['email']) ?></p>
              <p class="muted">Joined <?= e(date('j F Y', strtotime($user['created_at']))) ?></p>
            </div>
          </div>
        </section>

        <div class="split split-even">

          <section class="panel" aria-labelledby="account-title">
            <h2 id="account-title">Account details</h2>
<?php if ($error): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
<?php elseif ($ok): ?>
            <p class="status" role="status"><?= e($ok) ?></p>
<?php endif; ?>
            <form id="account-form" action="profile.php" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save">
              <div class="field">
                <label for="acc-username">Display name</label>
                <input type="text" id="acc-username" name="username" value="<?= e($user['username']) ?>" autocomplete="username" minlength="3" maxlength="30" required>
              </div>

              <div class="field">
                <label for="acc-email">Email</label>
                <input type="email" id="acc-email" name="email" value="<?= e($user['email']) ?>" autocomplete="email" maxlength="120" required>
              </div>

              <div class="field">
                <label for="acc-password">New password</label>
                <input type="password" id="acc-password" name="password" autocomplete="new-password" minlength="8" aria-describedby="acc-password-hint">
                <span class="hint" id="acc-password-hint">Leave blank to keep current password.</span>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
              </div>
            </form>

            <form action="profile.php" method="post" data-confirm="Delete your account and all its data? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <div class="form-actions">
                <button type="submit" class="btn btn-ghost">Delete account</button>
              </div>
            </form>
          </section>

          <div class="stack">

            <section class="panel" aria-labelledby="spotify-title">
              <h2 id="spotify-title">Spotify connection</h2>
              <p class="hint"><a class="accent" href="import_history.php">Import older listening history</a> from a Spotify data download.</p>
              <p class="status">Status: <strong class="accent"><?= $user['spotify_connected'] ? 'Connected' : 'Not connected' ?></strong></p>
<?php if ($spotifyMsg): ?>
              <p class="<?= $spotifyMsg[1] ? 'form-error' : 'status' ?>" role="<?= $spotifyMsg[1] ? 'alert' : 'status' ?>"><?= e($spotifyMsg[0]) ?></p>
<?php endif; ?>
<?php if ($user['spotify_connected']): ?>
              <form action="profile.php" method="post" data-confirm="Disconnect Spotify? Your saved songs stay in your library.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disconnect">
                <button type="submit" class="btn btn-ghost btn-sm">Disconnect</button>
              </form>
<?php else: ?>
              <a class="btn btn-ghost btn-sm" href="spotify_connect.php">Connect Spotify</a>
<?php endif; ?>
            </section>

            <section class="panel" aria-labelledby="listeners-title">
              <h2 id="listeners-title">Discover listeners</h2>
<?php if (!$listeners): ?>
              <p class="muted">No other listeners yet.</p>
<?php else: ?>
              <ul>
<?php foreach ($listeners as $l): ?>
                <li class="row">
                  <span class="avatar" aria-hidden="true"><?= e(strtoupper(mb_substr($l['username'], 0, 1))) ?></span>
                  <span class="grow"><?= e($l['username']) ?></span>
                </li>
<?php endforeach; ?>
              </ul>
<?php endif; ?>
            </section>

          </div>
        </div>
      </div>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
