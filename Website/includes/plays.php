<?php
require_once __DIR__ . '/spotify.php';

/**
 * Saves the user's newest Spotify plays into play_history. Returns how many were new, or null if Spotify refused.
 * Asks Spotify only for plays after the latest one already saved, and the table's unique key
 * (user, track, time) stops any play from being stored twice.
 */
function plays_import(int $uid): ?int {
    $query = ['limit' => 50];
    $st = db()->prepare('SELECT MAX(played_at) FROM play_history WHERE user_id = ?');
    $st->execute([$uid]);
    if ($latest = $st->fetchColumn()) {
        $query['after'] = (strtotime($latest) - 1) * 1000; // one second of overlap, duplicates are ignored
    }
    $res = spotify_api($uid, '/me/player/recently-played', $query);
    return $res === null ? null : plays_save($uid, $res['items'] ?? []);
}

/** Stores Spotify "recently played" items. Returns how many were new. */
function plays_save(int $uid, array $items): int {
    $pdo = db();
    $zone = new DateTimeZone(date_default_timezone_get());
    $insert = $pdo->prepare('INSERT IGNORE INTO play_history (user_id, track_id, played_at) VALUES (?, ?, ?)');
    $new = 0;
    foreach (array_reverse($items) as $item) {
        $t = $item['track'] ?? null;
        if (!$t || empty($t['id']) || empty($item['played_at'])) {
            continue; // local files and episodes have no Spotify track id
        }
        $when = (new DateTime($item['played_at']))->setTimezone($zone)->format('Y-m-d H:i:s');
        try {
            db_transaction(function () use ($insert, $uid, $t, $when, &$new) {
                $insert->execute([$uid, spotify_save_track($t), $when]);
                $new += $insert->rowCount();
            });
        } catch (Throwable $ex) {
            // skip this play, the next sync tries again
        }
    }
    return $new;
}

/**
 * Fills in cover art, real artists and duration for songs that came from a history import (or lack art),
 * one song at a time because Spotify no longer allows batch lookups. Up to 25 songs, 20 seconds, per run.
 */
function art_backfill(int $uid): void {
    $st = db()->prepare('SELECT t.spotify_track_id FROM track t
                         WHERE t.image_url IS NULL
                           AND (t.track_id IN (SELECT track_id FROM library_item WHERE user_id = ?)
                                OR t.track_id IN (SELECT track_id FROM play_history WHERE user_id = ?))
                         ORDER BY t.track_id DESC LIMIT 25');
    $st->execute([$uid, $uid]);
    $started = time();
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $sid) {
        if (time() - $started > 20) {
            return;
        }
        $r = spotify_call($uid, 'GET', '/tracks/' . $sid);
        if ($r === null || in_array($r[0], [401, 403, 429], true)) {
            return; // not allowed or rate limited: try again at the next sync
        }
        if ($r[0] === 200 && isset($r[1]['id'])) {
            try {
                db_transaction(fn() => spotify_save_track($r[1]));
            } catch (Throwable $ex) {
                // try this song again at the next sync
            }
        }
    }
}
