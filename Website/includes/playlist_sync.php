<?php
require_once __DIR__ . '/spotify.php';

const SPOTIFY_HINT_RECONNECT = 'Reconnect Spotify on your profile to allow playlists.';

/** Spotify track URIs of a playlist, in order (Spotify takes 100 per request, so the first 100). */
function playlist_uris(int $pid): array {
    $st = db()->prepare('SELECT t.spotify_track_id FROM playlist_track pt JOIN track t ON t.track_id = pt.track_id
                         WHERE pt.playlist_id = ? ORDER BY pt.position, t.track_id LIMIT 100');
    $st->execute([$pid]);
    return array_map(fn($id) => 'spotify:track:' . $id, $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Tries the newer "/items" path first and the older "/tracks" path if Spotify does not know it. */
function spotify_playlist_items_call(int $uid, string $method, string $spotifyId, array $query = [], ?array $body = null): ?array {
    $r = spotify_call($uid, $method, "/playlists/$spotifyId/items", $query, $body);
    if ($r !== null && in_array($r[0], [404, 405], true)) {
        $r = spotify_call($uid, $method, "/playlists/$spotifyId/tracks", $query, $body);
    }
    return $r;
}

/** Message for a failed Spotify call. */
function spotify_failure(?array $r): string {
    if ($r === null) {
        return 'Spotify is not reachable or the connection expired. ' . SPOTIFY_HINT_RECONNECT;
    }
    return in_array($r[0], [401, 403], true) ? 'Spotify refused this. ' . SPOTIFY_HINT_RECONNECT : 'Spotify returned an error (' . $r[0] . ').';
}

/**
 * Makes the Spotify playlist match the local one, creating it (private) the first time.
 * Returns an error message, or null when it worked or Spotify is not connected.
 */
function playlist_push(int $uid, int $pid): ?string {
    if (!spotify_account($uid)) {
        return null;
    }
    $st = db()->prepare('SELECT playlist_name, description, spotify_playlist_id FROM playlist WHERE playlist_id = ? AND user_id = ?');
    $st->execute([$pid, $uid]);
    $pl = $st->fetch();
    if (!$pl) {
        return null;
    }
    $details = ['name' => $pl['playlist_name'], 'description' => (string) $pl['description']];
    $sid = $pl['spotify_playlist_id'];

    if ($sid) {
        $r = spotify_call($uid, 'PUT', "/playlists/$sid", [], $details);
        if ($r !== null && $r[0] === 404) {
            $sid = null; // it was deleted on Spotify, so make a new one
        } elseif ($r === null || $r[0] >= 300) {
            return spotify_failure($r);
        }
    }
    if (!$sid) {
        $r = spotify_call($uid, 'POST', '/me/playlists', [], $details + ['public' => false]);
        if ($r === null || $r[0] >= 300 || empty($r[1]['id'])) {
            return spotify_failure($r);
        }
        $sid = $r[1]['id'];
        db()->prepare('UPDATE playlist SET spotify_playlist_id = ? WHERE playlist_id = ?')->execute([$sid, $pid]);
    }

    $r = spotify_playlist_items_call($uid, 'PUT', $sid, [], ['uris' => playlist_uris($pid)]);
    return $r !== null && $r[0] < 300 ? null : spotify_failure($r);
}

/** Creates a local playlist from Spotify playlist data. $items are Spotify playlist items. Returns the new playlist id. */
function playlist_import_save(int $uid, string $name, string $desc, string $sid, array $items): int {
    $pdo = db();
    return db_transaction(function () use ($pdo, $uid, $name, $desc, $sid, $items) {
        $pdo->prepare('INSERT INTO playlist (user_id, playlist_name, description, spotify_playlist_id) VALUES (?, ?, ?, ?)')
            ->execute([$uid, mb_substr($name !== '' ? $name : 'Spotify playlist', 0, 80), mb_substr($desc, 0, 200), $sid]);
        $pid = (int) $pdo->lastInsertId();
        $add = $pdo->prepare('INSERT IGNORE INTO playlist_track (playlist_id, track_id, position) VALUES (?, ?, ?)');
        $pos = 0;
        foreach ($items as $it) {
            $t = $it['item'] ?? $it['track'] ?? null; // newer responses use "item", older ones "track"
            if (!is_array($t) || empty($t['id']) || ($t['type'] ?? 'track') !== 'track' || !empty($t['is_local'])) {
                continue; // episodes and local files have no usable Spotify track
            }
            $add->execute([$pid, spotify_save_track($t), ++$pos]);
        }
        return $pid;
    });
}

/** Removes the linked Spotify playlist from the user's account ("deleting" a playlist on Spotify is unfollowing it). */
function playlist_unfollow(int $uid, int $pid): ?string {
    if (!spotify_account($uid)) {
        return null;
    }
    $st = db()->prepare('SELECT spotify_playlist_id FROM playlist WHERE playlist_id = ? AND user_id = ?');
    $st->execute([$pid, $uid]);
    $sid = $st->fetchColumn();
    if (!$sid) {
        return null;
    }
    $r = spotify_call($uid, 'DELETE', "/playlists/$sid/followers");
    // 404 means it is already gone from Spotify, which is what we want.
    return $r !== null && ($r[0] < 300 || $r[0] === 404) ? null : 'Deleted here, but it could not be removed from Spotify. ' . spotify_failure($r);
}
