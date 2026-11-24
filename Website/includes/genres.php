<?php
require_once __DIR__ . '/spotify.php';

/** Spotify no longer returns genres, so artist tags come from Last.fm (free API key in includes/config.php). */
function genres_configured(): bool {
    return app_config()['lastfm_key'] !== '';
}

/** Top 3 tags (named by at least 30% of the most-used tag's votes) for an artist. Null when Last.fm could not be reached. */
function lastfm_tags(string $artist): ?array {
    [$status, $json] = spotify_http('GET', 'https://ws.audioscrobbler.com/2.0/?' . http_build_query([
        'method' => 'artist.gettoptags', 'artist' => $artist, 'autocorrect' => 1,
        'api_key' => app_config()['lastfm_key'], 'format' => 'json',
    ]));
    if ($status !== 200 || !is_array($json)) {
        return null;
    }
    if (isset($json['error'])) {
        return in_array((int) $json['error'], [6], true) ? [] : null; // 6 = artist not found
    }
    return genres_pick($json['toptags']['tag'] ?? []);
}

/** Picks genre names from a Last.fm tag list. */
function genres_pick(array $tags): array {
    $out = [];
    foreach ($tags as $t) {
        if (count($out) >= 3 || (int) ($t['count'] ?? 0) < 30 || !is_string($t['name'] ?? null)) {
            continue;
        }
        $name = mb_strtolower(trim($t['name']));
        if ($name !== '' && mb_strlen($name) <= 100) {
            $out[$name] = $name;
        }
    }
    return array_values($out);
}

/** Saves an artist's genres; an empty string marks "looked up, none found" so it is not asked for again. */
function genres_store(int $artistId, array $genres): void {
    $ins = db()->prepare('INSERT IGNORE INTO artist_genre (artist_id, genre) VALUES (?, ?)');
    foreach ($genres ?: [''] as $g) {
        $ins->execute([$artistId, $g]);
    }
}

/** Looks up genres for up to 40 played artists that have none yet, within 20 seconds. */
function genres_fill(int $uid): void {
    if (!genres_configured()) {
        return;
    }
    $st = db()->prepare('SELECT a.artist_id, a.artist_name FROM artist a
                         WHERE NOT EXISTS (SELECT 1 FROM artist_genre g WHERE g.artist_id = a.artist_id)
                           AND a.artist_id IN (SELECT ta.artist_id FROM track_artist ta
                                               JOIN play_history ph ON ph.track_id = ta.track_id WHERE ph.user_id = ?)
                         LIMIT 40');
    $st->execute([$uid]);
    $started = time();
    foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $id => $name) {
        if (time() - $started > 20) {
            return;
        }
        $tags = lastfm_tags($name);
        if ($tags === null) {
            return; // Last.fm unreachable or key refused: try again at the next sync
        }
        genres_store((int) $id, $tags);
    }
}
