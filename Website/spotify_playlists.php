<?php
require __DIR__ . '/includes/playlist_sync.php';
$user = require_login();
$uid = $user['id'];

$connected = (bool) spotify_account($uid);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && $connected) {
    $sid = (string) ($_POST['spotify_id'] ?? '');
    $st = db()->prepare('SELECT 1 FROM playlist WHERE user_id = ? AND spotify_playlist_id = ?');
    $st->execute([$uid, $sid]);
    if (!preg_match('/^[A-Za-z0-9]{10,40}$/', $sid)) {
        $error = 'That playlist id is not valid.';
    } elseif ($st->fetch()) {
        $error = 'That playlist is already imported.';
    } else {
        $meta = spotify_call($uid, 'GET', "/playlists/$sid");
        $items = $meta !== null && $meta[0] === 200 ? spotify_playlist_items_call($uid, 'GET', $sid, ['limit' => 100]) : null;
        if ($meta === null || $meta[0] !== 200) {
            $error = spotify_failure($meta);
        } elseif ($items === null || $items[0] !== 200) {
            $error = spotify_failure($items);
        } else {
            $pid = playlist_import_save($uid, (string) ($meta[1]['name'] ?? ''), (string) ($meta[1]['description'] ?? ''), $sid, $items[1]['items'] ?? []);
            header('Location: playlists.php?id=' . $pid);
            exit;
        }
    }
}

$lists = [];
$imported = [];
if ($connected) {
    $res = spotify_call($uid, 'GET', '/me/playlists', ['limit' => 50]);
    if ($res === null || $res[0] !== 200) {
        $error = $error ?: spotify_failure($res);
    } else {
        $lists = $res[1]['items'] ?? [];
        $st = db()->prepare('SELECT spotify_playlist_id FROM playlist WHERE user_id = ? AND spotify_playlist_id IS NOT NULL');
        $st->execute([$uid]);
        $imported = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    }
}

$page = 'playlists';
$title = 'Import from Spotify';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="page-head">
        <div>
          <h1>Import from Spotify</h1>
          <p>Copy a Spotify playlist into Apollo Lyre. Up to 100 songs are imported.</p>
        </div>
        <a href="playlists.php" class="btn btn-ghost">Back to playlists</a>
      </div>

<?php if (!$connected): ?>
      <?php connect_panel('Connect your Spotify account to import playlists.'); ?>
<?php else: ?>
<?php if ($error): ?>
      <div class="form-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if (!$lists && !$error): ?>
      <div class="panel"><p class="muted">No playlists found in your Spotify account.</p></div>
<?php elseif ($lists): ?>
      <div class="panel">
        <ul>
<?php foreach ($lists as $p): if (!is_array($p) || empty($p['id'])) { continue; } $count = $p['tracks']['total'] ?? $p['items']['total'] ?? null; ?>
          <li class="row">
            <div class="grow">
              <strong title="<?= e($p['name'] ?? '') ?>"><?= e($p['name'] ?? '') ?></strong>
              <div class="muted"><?= $count === null ? '' : (int) $count . ' songs' ?></div>
            </div>
<?php if (isset($imported[$p['id']])): ?>
            <button type="button" class="btn btn-ghost btn-sm" disabled>Imported</button>
<?php else: ?>
            <form action="spotify_playlists.php" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="spotify_id" value="<?= e($p['id']) ?>">
              <button type="submit" class="btn btn-ghost btn-sm" aria-label="Import <?= e($p['name'] ?? '') ?>">Import</button>
            </form>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
