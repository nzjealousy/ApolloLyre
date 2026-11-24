<?php
require __DIR__ . '/includes/db.php';
$user = require_login();

$views = ['all' => 'All', 'recent' => 'Recently added', 'artists' => 'Artists', 'albums' => 'Albums'];
$view = array_key_exists($_GET['view'] ?? '', $views) ? $_GET['view'] : 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $item = (int) ($_POST['item'] ?? 0);
    if (($_POST['action'] ?? '') === 'remove') {
        db()->prepare('DELETE FROM library_item WHERE library_item_id = ? AND user_id = ?')->execute([$item, $user['id']]);
    } elseif (($_POST['action'] ?? '') === 'tags') {
        db()->prepare('UPDATE library_item SET tag = ? WHERE library_item_id = ? AND user_id = ?')
            ->execute([tags_normalize((string) ($_POST['tags'] ?? '')), $item, $user['id']]);
    }
    header('Location: library.php?view=' . $view);
    exit;
}

$order = ['all' => 'title ASC', 'recent' => 'saved_at DESC', 'artists' => 'artists ASC, title ASC', 'albums' => 'album_name ASC, title ASC'][$view];
$st = db()->prepare('SELECT * FROM (
                         SELECT li.library_item_id, li.tag, li.saved_at, t.title, t.album_name, t.duration_ms,
                                t.image_url AS art,
                                ' . artists_sql('t.track_id') . ' AS artists
                         FROM library_item li JOIN track t ON t.track_id = li.track_id
                         WHERE li.user_id = ?
                     ) x ORDER BY ' . $order . ' LIMIT 200');
$st->execute([$user['id']]);
$songs = $st->fetchAll();

$page = 'library';
$title = 'Music Library';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="page-head">
        <div>
          <h1>Music Library</h1>
          <p>Your saved songs. Find more with the search bar at the top.</p>
        </div>
      </div>

      <nav class="pills" aria-label="Filter songs">
<?php foreach ($views as $key => $label): ?>
        <a class="pill" href="library.php?view=<?= $key ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
      </nav>

<?php if (!$songs): ?>
      <div class="panel">
        <p class="muted">No songs yet. Search for a song in the bar at the top to add it to your library.</p>
      </div>
<?php else: ?>
      <div class="table-wrap">
        <table class="data-table library-table">
          <caption class="sr-only">Songs in your library</caption>
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Title</th>
              <th scope="col">Album</th>
              <th scope="col">Date added</th>
              <th scope="col">Time</th>
              <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($songs as $i => $s): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <div class="cell-title">
                  <span class="thumb-sm"<?= art_style($s['art']) ?> aria-hidden="true"></span>
                  <div>
                    <strong title="<?= e($s['title']) ?>"><?= e($s['title']) ?></strong>
                    <div class="muted"><?= e($s['artists'] ?? '') ?></div>
                    <?= tag_chips($s['tag']) ?>
                    <details class="tag-edit">
                      <summary>Edit tags</summary>
                      <form action="library.php?view=<?= $view ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="tags">
                        <input type="hidden" name="item" value="<?= (int) $s['library_item_id'] ?>">
                        <label class="sr-only" for="tags-<?= (int) $s['library_item_id'] ?>">Tags for <?= e($s['title']) ?></label>
                        <input type="text" id="tags-<?= (int) $s['library_item_id'] ?>" name="tags" value="<?= e($s['tag'] ?? '') ?>" placeholder="chill, study" maxlength="255">
                        <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                      </form>
                    </details>
                  </div>
                </div>
              </td>
              <td class="muted"><?= e($s['album_name']) ?></td>
              <td class="muted"><?= e(date('j M Y', strtotime($s['saved_at']))) ?></td>
              <td class="muted"><?= fmt_duration((int) $s['duration_ms']) ?></td>
              <td>
                <form action="library.php?view=<?= $view ?>" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="item" value="<?= (int) $s['library_item_id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Remove this song from your library?" aria-label="Remove <?= e($s['title']) ?>">Remove</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
