<?php
require_once __DIR__ . '/db.php';

/** All stats take the user id and a "since" datetime string (plays at or after it). */

function stats_totals(int $uid, string $since): array {
    $st = db()->prepare('SELECT COUNT(*) AS plays, COALESCE(SUM(t.duration_ms), 0) AS ms
                         FROM play_history ph JOIN track t ON t.track_id = ph.track_id
                         WHERE ph.user_id = ? AND ph.played_at >= ?');
    $st->execute([$uid, $since]);
    $row = $st->fetch();
    $st = db()->prepare('SELECT COUNT(DISTINCT ta.artist_id) FROM play_history ph
                         JOIN track_artist ta ON ta.track_id = ph.track_id
                         WHERE ph.user_id = ? AND ph.played_at >= ?');
    $st->execute([$uid, $since]);
    return ['plays' => (int) $row['plays'], 'hours' => $row['ms'] / 3600000, 'artists' => (int) $st->fetchColumn()];
}

function stats_top_artists(int $uid, string $since, int $limit): array {
    $st = db()->prepare('SELECT a.artist_name, COUNT(*) AS plays,
                                (SELECT t.image_url FROM track_artist ta2
                                 JOIN track t ON t.track_id = ta2.track_id
                                 JOIN play_history ph2 ON ph2.track_id = t.track_id
                                 WHERE ta2.artist_id = a.artist_id AND ph2.user_id = ? AND ph2.played_at >= ? AND t.image_url IS NOT NULL
                                 GROUP BY t.track_id ORDER BY COUNT(*) DESC LIMIT 1) AS art
                         FROM play_history ph
                         JOIN track_artist ta ON ta.track_id = ph.track_id
                         JOIN artist a ON a.artist_id = ta.artist_id
                         WHERE ph.user_id = ? AND ph.played_at >= ?
                         GROUP BY a.artist_id, a.artist_name ORDER BY plays DESC, a.artist_name LIMIT ' . $limit);
    $st->execute([$uid, $since, $uid, $since]);
    return $st->fetchAll();
}

function stats_top_tracks(int $uid, string $since, int $limit): array {
    $st = db()->prepare('SELECT t.title, COUNT(*) AS plays,
                                t.image_url AS art,
                                ' . artists_sql('t.track_id') . ' AS artists
                         FROM play_history ph JOIN track t ON t.track_id = ph.track_id
                         WHERE ph.user_id = ? AND ph.played_at >= ?
                         GROUP BY t.track_id, t.title ORDER BY plays DESC, t.title LIMIT ' . $limit);
    $st->execute([$uid, $since]);
    return $st->fetchAll();
}

function stats_genres(int $uid, string $since, int $limit): array {
    $st = db()->prepare('SELECT g.genre, COUNT(*) AS plays
                         FROM play_history ph
                         JOIN track_artist ta ON ta.track_id = ph.track_id
                         JOIN artist_genre g ON g.artist_id = ta.artist_id
                         WHERE ph.user_id = ? AND ph.played_at >= ? AND g.genre <> ""
                         GROUP BY g.genre ORDER BY plays DESC, g.genre LIMIT ' . $limit);
    $st->execute([$uid, $since]);
    return $st->fetchAll();
}

/** Plays per day for the last 7 days, oldest first: [['Mon', 3], ...]. */
function stats_week(int $uid): array {
    $st = db()->prepare('SELECT DATE(played_at) AS d, COUNT(*) FROM play_history
                         WHERE user_id = ? AND played_at >= ? GROUP BY DATE(played_at)');
    $st->execute([$uid, date('Y-m-d', strtotime('-6 days'))]);
    $counts = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $out = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $out[] = [date('D', strtotime($day)), (int) ($counts[$day] ?? 0)];
    }
    return $out;
}

/** Plays per month for the last 12 months, oldest first: [['Jan', 12], ...]. */
function stats_months(int $uid): array {
    $start = date('Y-m-01', strtotime('first day of -11 months'));
    $st = db()->prepare('SELECT DATE_FORMAT(played_at, "%Y-%m") AS m, COUNT(*) FROM play_history
                         WHERE user_id = ? AND played_at >= ? GROUP BY m');
    $st->execute([$uid, $start]);
    $counts = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $out = [];
    for ($i = 11; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i months");
        $out[] = [date('M', $ts), (int) ($counts[date('Y-m', $ts)] ?? 0)];
    }
    return $out;
}

/** Plays by time of day. Morning 05-10, Noon 11-13, Afternoon 14-17, Evening 18-21, Night 22-23, Late 00-04. */
function stats_time_of_day(int $uid, string $since): array {
    $st = db()->prepare('SELECT HOUR(played_at) AS h, COUNT(*) FROM play_history
                         WHERE user_id = ? AND played_at >= ? GROUP BY h');
    $st->execute([$uid, $since]);
    $byHour = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $buckets = ['Morning' => [5, 10], 'Noon' => [11, 13], 'Afternoon' => [14, 17], 'Evening' => [18, 21], 'Night' => [22, 23], 'Late' => [0, 4]];
    $out = [];
    foreach ($buckets as $label => [$from, $to]) {
        $n = 0;
        for ($h = $from; $h <= $to; $h++) {
            $n += (int) ($byHour[$h] ?? 0);
        }
        $out[] = [$label, $n];
    }
    return $out;
}

/** Bar height as a CSS percentage of the tallest bar ("0%" when there are no plays at all). */
function bar_pct(int $n, int $max): string {
    return ($max > 0 && $n > 0 ? max(3, (int) round($n / $max * 100)) : 0) . '%';
}

/** Chart bars as HTML. $short maps a label to a short phone label. */
function chart_html(array $data, array $short = []): string {
    $max = max(array_column($data, 1) ?: [0]);
    $html = '';
    foreach ($data as [$label, $n]) {
        $text = isset($short[$label]) ? '<span class="full">' . e($label) . '</span><span class="short">' . e($short[$label]) . '</span>' : e($label);
        $html .= '<div title="' . e($label) . ': ' . $n . ' plays"><i style="--v:' . bar_pct($n, $max) . '"></i>' . $text . '</div>';
    }
    return $html;
}
