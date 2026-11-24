<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/stats.php';
require __DIR__ . '/includes/genres.php';
$user = require_login();
$uid = $user['id'];

$periods = ['7d' => ['7 days', 7], '30d' => ['30 days', 30], '12m' => ['12 months', 365]];
$period = array_key_exists($_GET['period'] ?? '', $periods) ? $_GET['period'] : '30d';
$days = $periods[$period][1];
$since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

$totals = stats_totals($uid, $since);
$avgMinutes = $totals['hours'] * 60 / $days;
$months = stats_months($uid);
$genres = stats_genres($uid, $since, 5);
$artists = stats_top_artists($uid, $since, 5);
$timeOfDay = stats_time_of_day($uid, $since);
$empty = $totals['plays'] === 0;

$page = 'analytics';
$title = 'Listening Analytics';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="page-head">
        <div>
          <h1>Listening Analytics</h1>
          <p>Your listening habits, summarised. <a class="accent" href="import_history.php">Import older history</a></p>
        </div>
        <nav class="pills" aria-label="Time period">
<?php foreach ($periods as $key => [$label]): ?>
          <a class="pill" href="analytics.php?period=<?= $key ?>"<?= $period === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
        </nav>
      </div>

      <div class="grid-stats">
        <div class="panel stat-card"><span class="label">Total songs played</span><span class="value"><?= number_format($totals['plays']) ?></span></div>
        <div class="panel stat-card"><span class="label">Listening hours</span><span class="value"><?= number_format($totals['hours'], 1) ?></span></div>
        <div class="panel stat-card"><span class="label">Unique artists</span><span class="value"><?= number_format($totals['artists']) ?></span></div>
        <div class="panel stat-card"><span class="label">Avg. daily minutes</span><span class="value"><?= number_format($avgMinutes) ?></span></div>
      </div>

<?php if ($empty): ?>
      <div class="panel"><p class="muted">No plays in this period yet. Once Spotify is connected, your listening is imported every hour.</p></div>
<?php endif; ?>

      <div class="split stretch">
        <section class="panel" aria-labelledby="over-time-title">
          <h2 id="over-time-title">Listening over time</h2>
          <div class="chart" style="--chart-h:240px" role="img" aria-label="Songs played per month over the last 12 months">
            <?= chart_html($months) ?>
          </div>
        </section>

        <section class="panel" aria-labelledby="genres-title">
          <h2 id="genres-title">Top genres</h2>
<?php if (!$genres): ?>
          <p class="muted"><?= genres_configured() ? 'No genre data for this period yet. Genres are looked up from Last.fm during the hourly sync.' : 'Genres come from Last.fm. Add a free API key as lastfm_key in includes/config.php to turn them on.' ?></p>
<?php else: $maxGenre = (int) $genres[0]['plays']; foreach ($genres as $g): ?>
          <div class="meter"><?= e($g['genre']) ?><i style="--v:<?= bar_pct((int) $g['plays'], $maxGenre) ?>"></i></div>
<?php endforeach; endif; ?>
        </section>
      </div>

      <div class="split split-even stretch">
        <section class="panel" aria-labelledby="artists-title">
          <h2 id="artists-title">Top artists</h2>
<?php if (!$artists): ?>
          <p class="muted">No plays in this period.</p>
<?php else: ?>
          <ol>
<?php foreach ($artists as $i => $a): ?>
            <li class="row">
              <span class="muted"><?= $i + 1 ?></span>
              <span class="thumb-sm thumb-round"<?= art_style($a['art']) ?> aria-hidden="true"></span>
              <strong class="grow"><?= e($a['artist_name']) ?></strong>
              <span class="muted"><?= number_format($a['plays']) ?> plays</span>
            </li>
<?php endforeach; ?>
          </ol>
<?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="time-title">
          <h2 id="time-title">Listening by time of day</h2>
<?php if ($empty): ?>
          <p class="muted">No plays in this period.</p>
<?php else: ?>
          <div class="chart" style="--chart-h:200px" role="img" aria-label="Songs played by time of day">
            <?= chart_html($timeOfDay, ['Morning' => 'Morn', 'Afternoon' => 'Aft', 'Evening' => 'Eve']) ?>
          </div>
<?php endif; ?>
        </section>
      </div>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
