<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/stats.php';
$user = require_login();
$uid = $user['id'];
$all = '1970-01-01 00:00:00';

$totals = stats_totals($uid, $all);
$topArtist = stats_top_artists($uid, $all, 1)[0] ?? null;
$week = stats_week($uid);
$tracks = stats_top_tracks($uid, $all, 5);

$st = db()->prepare('SELECT p.playlist_id, p.playlist_name, COUNT(pt.track_id) AS n,
                            (SELECT ta.image_url FROM playlist_track x JOIN track ta ON ta.track_id = x.track_id
                             WHERE x.playlist_id = p.playlist_id AND ta.image_url IS NOT NULL ORDER BY x.position LIMIT 1) AS art
                     FROM playlist p LEFT JOIN playlist_track pt ON pt.playlist_id = p.playlist_id
                     WHERE p.user_id = ? GROUP BY p.playlist_id, p.playlist_name
                     ORDER BY p.created_at DESC, p.playlist_id DESC LIMIT 5');
$st->execute([$uid]);
$playlists = $st->fetchAll();

$page = 'dashboard';
$title = 'Dashboard';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="hero-split">
        <div>
          <h1>Your Music, <span>Organized.</span></h1>
          <p class="muted">Connect. Listen. Discover. All in one place.</p>

          <div class="hero-stats">
            <div class="panel stat-card"><span class="label">Total songs played</span><span class="value"><?= number_format($totals['plays']) ?></span></div>
            <div class="panel stat-card"><span class="label">Listening hours</span><span class="value"><?= number_format($totals['hours'], 1) ?></span></div>
            <div class="panel stat-card">
              <span class="label">Top artist</span>
              <span class="value"><?= $topArtist ? e($topArtist['artist_name']) : 'None yet' ?></span>
<?php if ($topArtist): ?>
              <span class="note"><?= number_format($topArtist['plays']) ?> plays</span>
<?php endif; ?>
            </div>
          </div>
        </div>

        <section class="panel" aria-labelledby="weekly-title">
          <div class="panel-head"><h2 id="weekly-title">Weekly listening activity</h2><span class="muted"><?= date('j M', strtotime('-6 days')) ?> to <?= date('j M') ?></span></div>
<?php if (array_sum(array_column($week, 1)) === 0): ?>
          <p class="muted">No plays in the last 7 days.</p>
<?php else: ?>
          <div class="chart" style="--chart-h:200px" role="img" aria-label="Songs played per day over the last 7 days">
            <?= chart_html($week) ?>
          </div>
<?php endif; ?>
        </section>
      </div>

      <section aria-labelledby="top-tracks-title">
        <div class="section-title"><h2 id="top-tracks-title">Top tracks</h2><a href="library.php">See all</a></div>
<?php if (!$tracks): ?>
        <div class="panel"><p class="muted">No plays yet. Once Spotify is connected, your listening is imported every hour.</p></div>
<?php else: ?>
        <div class="grid-cards">
<?php foreach ($tracks as $t): ?>
          <article class="panel media-card">
            <div class="thumb"<?= art_style($t['art']) ?> aria-hidden="true"></div>
            <h3 class="title"><?= e($t['title']) ?></h3>
            <p class="meta"><?= e($t['artists'] ?? '') ?></p>
            <p class="meta"><?= number_format($t['plays']) ?> plays</p>
          </article>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </section>

      <section aria-labelledby="playlists-title">
        <div class="section-title"><h2 id="playlists-title">Your playlists</h2><a href="playlists.php">See all</a></div>
<?php if (!$playlists): ?>
        <div class="panel"><p class="muted">No playlists yet. <a class="accent" href="playlists.php">Create your first playlist.</a></p></div>
<?php else: ?>
        <div class="grid-cards">
<?php foreach ($playlists as $p): ?>
          <article class="panel media-card">
            <div class="thumb"<?= art_style($p['art']) ?> aria-hidden="true"></div>
            <h3 class="title"><?= e($p['playlist_name']) ?></h3>
            <p class="meta"><?= (int) $p['n'] ?> songs</p>
            <div class="actions"><a class="btn btn-ghost btn-sm" href="playlists.php?id=<?= (int) $p['playlist_id'] ?>" aria-label="Open <?= e($p['playlist_name']) ?>">Open</a></div>
          </article>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </section>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
