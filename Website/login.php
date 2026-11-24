<?php
require __DIR__ . '/includes/db.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $st = db()->prepare('SELECT user_id, password_hash FROM users WHERE name = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    if (!csrf_ok()) {
        $error = 'Session expired. Reload the page and try again.';
    } elseif ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $row['user_id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Wrong username or password.';
    }
}

$page = 'login';
$title = 'Log in';
require __DIR__ . '/includes/auth_top.php';
?>
      <h1 id="login-title">Welcome back!</h1>
      <p class="lead">Log in to manage your music.</p>

<?php if (isset($_GET['registered']) && !$error): ?>
      <p class="status" role="status">Account created. Log in to continue.</p>
<?php endif; ?>
<?php if ($error): ?>
      <div class="form-error" id="login-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

      <form id="login-form" action="login.php" method="post" aria-labelledby="login-title">
        <?= csrf_field() ?>
        <div class="field">
          <label for="login-username">Username</label>
          <input type="text" id="login-username" name="username" value="<?= e($username) ?>" autocomplete="username" required>
        </div>

        <div class="field">
          <label for="login-password">Password</label>
          <input type="password" id="login-password" name="password" autocomplete="current-password" minlength="8" aria-describedby="login-password-hint" required>
          <span class="hint" id="login-password-hint">At least 8 characters.</span>
        </div>

        <button type="submit" class="btn btn-primary">Log in</button>
      </form>

      <p class="hint auth-switch">New here? <a class="accent" href="register.php">Create account</a></p>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
