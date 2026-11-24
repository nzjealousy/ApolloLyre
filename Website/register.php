<?php
require __DIR__ . '/includes/db.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirmpassword'] ?? '');

    if (!csrf_ok()) {
        $error = 'Session expired. Reload the page and try again.';
    } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 30) {
        $error = 'Name must be 3 to 30 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $st = db()->prepare('SELECT 1 FROM users WHERE name = ? OR email = ?');
        $st->execute([$username, $email]);
        if ($st->fetch()) {
            $error = 'That name or email is already registered.';
        } else {
            $st = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $st->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}

$page = 'register';
$title = 'Sign up';
require __DIR__ . '/includes/auth_top.php';
?>
      <h1 id="register-title">Create your account</h1>
      <p class="lead">You'll connect to Spotify after you log in.</p>

<?php if ($error): ?>
      <div class="form-error" id="register-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

      <form id="register-form" action="register.php" method="post" aria-labelledby="register-title">
        <?= csrf_field() ?>
        <div class="field">
          <label for="reg-username">Name</label>
          <input type="text" id="reg-username" name="username" value="<?= e($username) ?>" placeholder="Your name" autocomplete="username" minlength="3" maxlength="30" required>
        </div>

        <div class="field">
          <label for="reg-email">Email</label>
          <input type="email" id="reg-email" name="email" value="<?= e($email) ?>" placeholder="name@example.com" autocomplete="email" maxlength="120" required>
        </div>

        <div class="field">
          <label for="reg-password">Password</label>
          <input type="password" id="reg-password" name="password" autocomplete="new-password" minlength="8" aria-describedby="reg-password-hint" required>
          <span class="hint" id="reg-password-hint">At least 8 characters.</span>
        </div>

        <div class="field">
          <label for="reg-confirm">Confirm password</label>
          <input type="password" id="reg-confirm" name="confirmpassword" autocomplete="new-password" minlength="8" required>
        </div>

        <button type="submit" class="btn btn-primary">Create account</button>
      </form>

      <p class="hint auth-switch">Already have an account? <a class="accent" href="login.php">Log in</a></p>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
