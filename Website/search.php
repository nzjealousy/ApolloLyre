<?php
require __DIR__ . '/includes/spotify.php';
$user = require_login();

$connected = (bool) spotify_account($user['id']);
$q = trim((string) ($_GET['q'] ?? ''));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && $connected) {
    $sid = (string) ($_POST['spotify_id'] ?? '');
    // Re-fetch the track from Spotify instead of trusting form fields.
    $track = preg_match('/^[A-Za-z0-9]{10,30}$/', $sid) ? spotify_api($user['id'], '/tracks/' . $sid) : null;
    if ($track && isset($track['id'], $track['name'])) {
        library_add_track($user['id'], $track);
        header('Location: search.php?q=' . urlencode($q) . '&added=' . urlencode($track['name']));
        exit;
    }
    $error = 'Could not add that song. Try again.';
}

$results = [];
$inLibrary = [];
if ($connected && $q !== '') {
    $res = spotify_api($user['id'], '/search', ['q' => $q, 'type' => 'track', 'limit' => 10]);
    if ($res === null) {
        $error = $error ?: 'Spotify search failed. Reconnect Spotify on your profile and try again.';
        $connected = (bool) spotify_account($user['id']);
    } else {
        $results = $res['tracks']['items'] ?? [];
        $st = db()->prepare('SELECT t.spotify_track_id FROM library_item li JOIN track t ON t.track_id = li.track_id WHERE li.user_id = ?');
        $st->execute([$user['id']]);
        $inLibrary = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    }
}

$page = 'search';
$title = 'Search';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="page-head">
        <div>
          <h1><?= $q !== '' ? 'Results for "' . e($q) . '"' : 'Search Spotify' ?></h1>
          <p>Songs from Spotify. Add them to your library.</p>
        </div>
        <a href="library.php" class="btn btn-ghost">Back to library</a>
      </div>

<?php if (!$connected): ?>
      <?php connect_panel('Connect your Spotify account to search and add songs.'); ?>
<?php else: ?>
<?php if ($error): ?>
      <div class="form-error" role="alert"><?= e($error) ?></div>
<?php elseif (isset($_GET['added'])): ?>
      <p class="status" role="status">Added "<?= e((string) $_GET['added']) ?>" to your library.</p>
<?php endif; ?>

<?php if ($q === ''): ?>
      <div class="panel"><p class="muted">Type a song or artist in the search bar at the top and press Enter.</p></div>
<?php elseif (!$results && !$error): ?>
      <div class="panel"><p class="muted">No songs found for "<?= e($q) ?>".</p></div>
<?php elseif ($results): ?>
      <div class="table-wrap">
        <table class="data-table library-table">
          <caption class="sr-only">Spotify search results</caption>
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Title</th>
              <th scope="col">Album</th>
              <th scope="col">Time</th>
              <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($results as $n => $t): $ms = (int) $t['duration_ms']; ?>
            <tr>
              <td><?= $n + 1 ?></td>
              <td>
                <div class="cell-title">
                  <span class="thumb-sm"<?= art_style(spotify_pick_image($t['album'] ?? [])) ?> aria-hidden="true"></span>
                  <div>
                    <strong title="<?= e($t['name']) ?>"><?= e($t['name']) ?></strong>
                    <div class="muted"><?= e(implode(', ', array_column($t['artists'], 'name'))) ?></div>
                  </div>
                </div>
              </td>
              <td class="muted"><?= e($t['album']['name'] ?? '') ?></td>
              <td class="muted"><?= fmt_duration($ms) ?></td>
              <td>
<?php if (isset($inLibrary[$t['id']])): ?>
                <button type="button" class="btn btn-ghost btn-sm" disabled>Added</button>
<?php else: ?>
                <form action="search.php?q=<?= e(urlencode($q)) ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="spotify_id" value="<?= e($t['id']) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" aria-label="Add <?= e($t['name']) ?>">Add</button>
                </form>
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
