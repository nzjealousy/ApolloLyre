<?php
require_once __DIR__ . '/db.php';

function spotify_configured(): bool {
    $c = app_config();
    return $c['client_id'] !== '' && $c['client_secret'] !== '' && $c['redirect_uri'] !== '';
}

/** One HTTP call; returns [status, decoded JSON or null]. */
function spotify_http(string $method, string $url, array $headers = [], ?array $form = null, ?string $json = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($form !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    if ($json !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    }
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, is_string($body) ? json_decode($body, true) : null];
}

/** Token endpoint call (authorization_code or refresh_token grant). */
function spotify_token(array $params): ?array {
    $c = app_config();
    [$status, $json] = spotify_http('POST', 'https://accounts.spotify.com/api/token', [
        'Authorization: Basic ' . base64_encode($c['client_id'] . ':' . $c['client_secret']),
        'Content-Type: application/x-www-form-urlencoded',
    ], $params);
    return $status === 200 && isset($json['access_token']) ? $json : null;
}

function spotify_account(int $uid): ?array {
    $st = db()->prepare('SELECT * FROM spotify_account WHERE user_id = ?');
    $st->execute([$uid]);
    return $st->fetch() ?: null;
}

/** Saves tokens for a user. $refresh may be null on a refresh grant, which keeps the stored one. */
function spotify_store(int $uid, string $spotifyUser, array $tok, ?string $refresh): void {
    db()->prepare('INSERT INTO spotify_account (user_id, spotify_user_id, access_token, refresh_token, token_expires_at)
                   VALUES (?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE spotify_user_id = VALUES(spotify_user_id), access_token = VALUES(access_token),
                       refresh_token = VALUES(refresh_token), token_expires_at = VALUES(token_expires_at)')
        ->execute([$uid, $spotifyUser, $tok['access_token'], $refresh, date('Y-m-d H:i:s', time() + (int) ($tok['expires_in'] ?? 3600))]);
}

/** Any call to the Spotify Web API as this user, refreshing the token when it is about to expire. Returns [status, JSON] or null. */
function spotify_call(int $uid, string $method, string $path, array $query = [], ?array $body = null): ?array {
    $acc = spotify_account($uid);
    if (!$acc) {
        return null;
    }
    if (strtotime($acc['token_expires_at']) < time() + 60) {
        $tok = spotify_token(['grant_type' => 'refresh_token', 'refresh_token' => $acc['refresh_token']]);
        if (!$tok) {
            db()->prepare('DELETE FROM spotify_account WHERE user_id = ?')->execute([$uid]);
            return null;
        }
        $acc['refresh_token'] = $tok['refresh_token'] ?? $acc['refresh_token'];
        $acc['access_token'] = $tok['access_token'];
        spotify_store($uid, $acc['spotify_user_id'], $tok, $acc['refresh_token']);
    }
    $url = 'https://api.spotify.com/v1' . $path . ($query ? '?' . http_build_query($query) : '');
    return spotify_http($method, $url, ['Authorization: Bearer ' . $acc['access_token']], null, $body === null ? null : json_encode($body));
}

/** GET on the Spotify Web API as this user. JSON on success, null on any failure. */
function spotify_api(int $uid, string $path, array $query = []): ?array {
    $r = spotify_call($uid, 'GET', $path, $query);
    return $r !== null && $r[0] === 200 ? $r[1] : null;
}

/** Picks the ~300px cover from a Spotify album object (images come largest first). */
function spotify_pick_image(array $album): ?string {
    $imgs = $album['images'] ?? [];
    $url = $imgs[1]['url'] ?? $imgs[0]['url'] ?? null;
    return is_string($url) && strlen($url) <= 255 && str_starts_with($url, 'https://') ? $url : null;
}

/** Placeholder Spotify id for an artist known only by name (history exports have no artist ids). */
function artist_name_id(string $name): string {
    return 'name:' . md5(mb_strtolower($name));
}

/** Saves a Spotify track and its artists (no library entry). Run inside a transaction. Returns the local track_id. */
function spotify_save_track(array $t): int {
    $pdo = db();
    $pdo->prepare('INSERT INTO track (spotify_track_id, title, album_name, duration_ms, image_url) VALUES (?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE title = VALUES(title), album_name = VALUES(album_name), duration_ms = VALUES(duration_ms),
                       image_url = COALESCE(VALUES(image_url), image_url)')
        ->execute([$t['id'], mb_substr($t['name'], 0, 255), mb_substr($t['album']['name'] ?? '', 0, 255), (int) ($t['duration_ms'] ?? 0), spotify_pick_image($t['album'] ?? [])]);
    $st = $pdo->prepare('SELECT track_id FROM track WHERE spotify_track_id = ?');
    $st->execute([$t['id']]);
    $trackId = (int) $st->fetchColumn();

    foreach ($t['artists'] ?? [] as $a) {
        // An artist created by name from a history import takes over the real Spotify id instead of becoming a second row.
        $st = $pdo->prepare('SELECT 1 FROM artist WHERE spotify_artist_id = ?');
        $st->execute([$a['id']]);
        if (!$st->fetch()) {
            $pdo->prepare('UPDATE artist SET spotify_artist_id = ? WHERE spotify_artist_id = ?')->execute([$a['id'], artist_name_id($a['name'])]);
        }
        $pdo->prepare('INSERT INTO artist (spotify_artist_id, artist_name) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE artist_name = VALUES(artist_name)')
            ->execute([$a['id'], mb_substr($a['name'], 0, 255)]);
        $st = $pdo->prepare('SELECT artist_id FROM artist WHERE spotify_artist_id = ?');
        $st->execute([$a['id']]);
        $pdo->prepare('INSERT IGNORE INTO track_artist (track_id, artist_id) VALUES (?, ?)')->execute([$trackId, (int) $st->fetchColumn()]);
    }
    return $trackId;
}

/** Adds a Spotify track (as returned by the API) to a user's library. Returns the local track_id. */
function library_add_track(int $uid, array $t): int {
    return db_transaction(function () use ($uid, $t) {
        $trackId = spotify_save_track($t);
        db()->prepare('INSERT INTO library_item (user_id, track_id)
                       SELECT ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM library_item WHERE user_id = ? AND track_id = ?)')
            ->execute([$uid, $trackId, $uid, $trackId]);
        return $trackId;
    });
}
