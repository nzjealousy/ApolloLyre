<?php
require __DIR__ . '/includes/playlist_sync.php';
$user = require_login();

function owned_playlist(int $id, int $uid): ?array {
    $st = db()->prepare('SELECT playlist_id, playlist_name, description, tag, spotify_playlist_id FROM playlist WHERE playlist_id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    return $st->fetch() ?: null;
}

/** Mirrors the playlist to Spotify; a failure is shown once after the redirect and never undoes the local change. */
function push_note(int $uid, int $pid): void {
    if ($err = playlist_push($uid, $pid)) {
        $_SESSION['pl_note'] = $err;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $do = (string) ($_POST['do'] ?? '');
    if (!csrf_ok()) {
        $error = 'Session expired. Reload the page and try again.';
    } elseif ($do === 'save') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $tag = tags_normalize((string) ($_POST['tags'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            $error = 'Name must be 1 to 80 characters.';
        } elseif (mb_strlen($desc) > 200) {
            $error = 'Description is at most 200 characters.';
        } elseif ($id && owned_playlist($id, $user['id'])) {
            db()->prepare('UPDATE playlist SET playlist_name = ?, description = ?, tag = ? WHERE playlist_id = ?')->execute([$name, $desc, $tag, $id]);
            push_note($user['id'], $id);
            header('Location: playlists.php'); // saving goes back to the list of playlists
            exit;
        } else {
            db()->prepare('INSERT INTO playlist (user_id, playlist_name, description, tag) VALUES (?, ?, ?, ?)')->execute([$user['id'], $name, $desc, $tag]);
            push_note($user['id'], (int) db()->lastInsertId());
            header('Location: playlists.php');
            exit;
        }
    } elseif ($id && owned_playlist($id, $user['id'])) {
        if ($do === 'delete') {
            if ($err = playlist_unfollow($user['id'], $id)) {
                $_SESSION['pl_note'] = $err;
            }
            db()->prepare('DELETE FROM playlist WHERE playlist_id = ?')->execute([$id]);
            header('Location: playlists.php');
            exit;
        }
        if (str_starts_with($do, 'add:')) {
            $st = db()->prepare('INSERT INTO playlist_track (playlist_id, track_id, position)
                SELECT ?, li.track_id, COALESCE((SELECT MAX(position) FROM playlist_track WHERE playlist_id = ?), 0) + 1
                FROM library_item li
                WHERE li.user_id = ? AND li.track_id = ?
                  AND NOT EXISTS (SELECT 1 FROM playlist_track WHERE playlist_id = ? AND track_id = li.track_id)
                LIMIT 1');
            $st->execute([$id, $id, $user['id'], (int) substr($do, 4), $id]);
        } elseif (str_starts_with($do, 'remove:')) {
            db()->prepare('DELETE FROM playlist_track WHERE playlist_id = ? AND track_id = ?')->execute([$id, (int) substr($do, 7)]);
        }
        push_note($user['id'], $id);
        header('Location: playlists.php?id=' . $id);
        exit;
    }
}
$flash = (string) ($_SESSION['pl_note'] ?? '');
unset($_SESSION['pl_note']);

// Cards: the playlist's own tags plus the tags of the songs in it.
$st = db()->prepare('SELECT p.playlist_id, p.playlist_name, p.tag, COUNT(pt.track_id) AS n,
                            (SELECT GROUP_CONCAT(li.tag SEPARATOR ",") FROM playlist_track x
                             JOIN library_item li ON li.track_id = x.track_id AND li.user_id = p.user_id
                             WHERE x.playlist_id = p.playlist_id AND li.tag IS NOT NULL) AS song_tags,
                            (SELECT t2.image_url FROM playlist_track x JOIN track t2 ON t2.track_id = x.track_id
                             WHERE x.playlist_id = p.playlist_id AND t2.image_url IS NOT NULL ORDER BY x.position LIMIT 1) AS art
                     FROM playlist p LEFT JOIN playlist_track pt ON pt.playlist_id = p.playlist_id
                     WHERE p.user_id = ? GROUP BY p.playlist_id, p.playlist_name, p.tag, p.user_id ORDER BY p.created_at DESC, p.playlist_id DESC');
$st->execute([$user['id']]);
$playlists = $st->fetchAll();

$sel = ($i = (int) ($_GET['id'] ?? 0)) ? owned_playlist($i, $user['id']) : null;
// The details panel is only shown for an open playlist, after + New playlist, or when a save failed.
$showDetails = $sel || isset($_GET['new']) || $error !== '';
$songs = [];
if ($sel) {
    // Your library songs plus any songs already in the playlist (imported ones), with whether each is in it.
    $st = db()->prepare('SELECT t.track_id, t.title, t.image_url AS art, li.tag,
                                ' . artists_sql('t.track_id') . ' AS artists,
                                EXISTS (SELECT 1 FROM playlist_track pt WHERE pt.playlist_id = ? AND pt.track_id = t.track_id) AS in_playlist
                         FROM track t
                         LEFT JOIN library_item li ON li.track_id = t.track_id AND li.user_id = ?
                         WHERE li.user_id IS NOT NULL OR t.track_id IN (SELECT track_id FROM playlist_track WHERE playlist_id = ?)
                         ORDER BY t.title');
    $st->execute([$sel['playlist_id'], $user['id'], $sel['playlist_id']]);
    $songs = $st->fetchAll();
}

$page = 'playlists';
$title = 'Playlists';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="page-head">
        <div>
          <h1>Playlists</h1>
          <p>Create, edit and organise your playlists.</p>
        </div>
        <a href="spotify_playlists.php" class="btn btn-ghost">Import from Spotify</a>
      </div>

<?php if ($flash && !$sel): ?>
      <div class="form-error" role="alert"><?= e($flash) ?></div>
<?php endif; ?>

      <div class="split<?= $showDetails ? '' : ' split-single' ?>">

<?php if ($sel): ?>
        <section class="panel song-picker" aria-labelledby="picker-title">
          <div class="panel-head">
            <h2 id="picker-title">Songs</h2>
            <a class="muted" href="playlists.php">Back to playlists</a>
          </div>
<?php if (!$songs): ?>
          <p class="muted">Your library is empty. Search for songs in the bar at the top to add some.</p>
<?php else: ?>
          <form class="picker-scroll" action="playlists.php" method="post" data-scroll-keep>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $sel['playlist_id'] ?>">
            <ul>
<?php foreach ($songs as $s): ?>
              <li class="row">
                <span class="thumb-sm"<?= art_style($s['art']) ?> aria-hidden="true"></span>
                <div class="grow">
                  <strong title="<?= e($s['title']) ?>"><?= e($s['title']) ?></strong>
                  <div class="muted"><?= e($s['artists'] ?? '') ?></div>
                  <?= tag_chips($s['tag']) ?>
                </div>
<?php if ($s['in_playlist']): ?>
                <button type="submit" name="do" value="remove:<?= (int) $s['track_id'] ?>" class="btn btn-ghost btn-sm" aria-label="Remove <?= e($s['title']) ?> from this playlist">Remove</button>
<?php else: ?>
                <button type="submit" name="do" value="add:<?= (int) $s['track_id'] ?>" class="btn btn-primary btn-sm" aria-label="Add <?= e($s['title']) ?> to this playlist">Add</button>
<?php endif; ?>
              </li>
<?php endforeach; ?>
            </ul>
          </form>
<?php endif; ?>
        </section>
<?php else: ?>
        <div class="grid-cards">
          <a href="playlists.php?new=1" class="new-card">+ New playlist</a>

<?php foreach ($playlists as $p): ?>
          <article class="panel media-card">
            <div class="thumb"<?= art_style($p['art']) ?> aria-hidden="true"></div>
            <h2 class="title" title="<?= e($p['playlist_name']) ?>"><?= e($p['playlist_name']) ?></h2>
            <p class="meta"><?= (int) $p['n'] ?> songs</p>
            <?= tag_chips(tags_normalize($p['tag'] . ',' . $p['song_tags'])) ?>
            <div class="actions">
              <!-- TODO: playback needs a Spotify player -->
              <button type="button" class="btn btn-ghost btn-sm" disabled title="Playback is not available yet" aria-label="Play <?= e($p['playlist_name']) ?>">Play</button>
              <a class="btn btn-ghost btn-sm" href="playlists.php?id=<?= (int) $p['playlist_id'] ?>" aria-label="Edit <?= e($p['playlist_name']) ?>">Edit</a>
            </div>
          </article>
<?php endforeach; ?>
        </div>
<?php endif; ?>

<?php if ($showDetails): ?>
        <aside class="panel details-panel" aria-labelledby="details-title">
          <h2 id="details-title"><?= $sel ? 'Playlist details' : 'New playlist' ?></h2>
<?php if ($error || ($flash && $sel)): ?>
          <div class="form-error" role="alert"><?= e($error ?: $flash) ?></div>
<?php endif; ?>
<?php if ($sel && $sel['spotify_playlist_id']): ?>
          <p class="hint">Linked to <a class="accent" href="https://open.spotify.com/playlist/<?= e($sel['spotify_playlist_id']) ?>" target="_blank" rel="noopener">Spotify</a>. Changes here update it.</p>
<?php endif; ?>
          <form id="playlist-form" action="playlists.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $sel ? (int) $sel['playlist_id'] : 0 ?>">

            <div class="field">
              <label for="pl-name">Name</label>
              <input type="text" id="pl-name" name="name" value="<?= e($sel['playlist_name'] ?? '') ?>" placeholder="Playlist name" maxlength="80" required>
            </div>

            <div class="field">
              <label for="pl-desc">Description</label>
              <input type="text" id="pl-desc" name="description" value="<?= e($sel['description'] ?? '') ?>" placeholder="Short description" maxlength="200">
            </div>

            <div class="field">
              <label for="pl-tags">Tags</label>
              <input type="text" id="pl-tags" name="tags" value="<?= e($sel['tag'] ?? '') ?>" placeholder="chill, study" maxlength="255" aria-describedby="pl-tags-hint">
              <span class="hint" id="pl-tags-hint">Separate with commas. Tags from the songs show on the card too.</span>
            </div>

            <div class="form-actions">
              <button type="submit" name="do" value="save" class="btn btn-primary">Save</button>
<?php if (!$sel): ?>
              <a class="btn btn-ghost" href="playlists.php">Cancel</a>
<?php else: ?>
              <button type="submit" name="do" value="delete" class="btn btn-ghost" formnovalidate data-confirm="Delete this playlist<?= $sel['spotify_playlist_id'] ? ' here and on Spotify' : '' ?>? This cannot be undone.">Delete</button>
<?php endif; ?>
            </div>
          </form>
        </aside>
<?php endif; ?>

      </div>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
