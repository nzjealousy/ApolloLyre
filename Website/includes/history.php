<?php
require_once __DIR__ . '/spotify.php';

const HISTORY_MIN_MS = 30000; // Spotify itself counts a play from 30 seconds

/**
 * Reads one Spotify "Extended streaming history" JSON file.
 * Returns [entries, skipped]: entries are plays of Spotify songs of 30 seconds or more; skipped counts the rest
 * (short plays, podcasts, and old-format rows that carry no Spotify track id).
 */
function history_parse(string $json): array {
    $rows = json_decode($json, true);
    if (!is_array($rows)) {
        return [[], 0];
    }
    $entries = [];
    $skipped = 0;
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $ok = preg_match('/^spotify:track:([A-Za-z0-9]{22})$/', (string) ($r['spotify_track_uri'] ?? ''), $m)
            && (int) ($r['ms_played'] ?? 0) >= HISTORY_MIN_MS
            && trim((string) ($r['master_metadata_track_name'] ?? '')) !== ''
            && ($ts = strtotime((string) ($r['ts'] ?? ''))) !== false;
        if (!$ok) {
            $skipped++;
            continue;
        }
        $entries[] = [
            'id' => $m[1],
            'name' => mb_substr(trim($r['master_metadata_track_name']), 0, 255),
            'album' => mb_substr(trim((string) ($r['master_metadata_album_album_name'] ?? '')), 0, 255),
            'artist' => mb_substr(trim((string) ($r['master_metadata_album_artist_name'] ?? '')), 0, 255),
            'ms' => (int) $r['ms_played'],
            'ts' => $ts,
        ];
    }
    return [$entries, $skipped];
}

/**
 * Saves parsed history entries. Songs and artists are created from the names in the file (no Spotify calls),
 * the sync fills in covers and real artists later. Returns the number of plays that were new.
 * Plays already saved, by the live sync or an earlier import, are ignored by the table's unique key.
 */
function history_import(int $uid, array $entries): int {
    if (!$entries) {
        return 0;
    }
    $pdo = db();
    $zone = new DateTimeZone(date_default_timezone_get());
    $tracks = [];
    foreach ($entries as $e) {
        $tracks[$e['id']] ??= $e + ['max' => 0];
        $tracks[$e['id']]['max'] = max($tracks[$e['id']]['max'], $e['ms']);
    }

    return db_transaction(function () use ($pdo, $uid, $entries, $tracks, $zone) {
        // songs we already know
        $trackIds = [];
        foreach (array_chunk(array_keys($tracks), 500) as $chunk) {
            $st = $pdo->prepare('SELECT spotify_track_id, track_id FROM track WHERE spotify_track_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $st->execute($chunk);
            $trackIds += $st->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        $newTrack = $pdo->prepare('INSERT INTO track (spotify_track_id, title, album_name, duration_ms) VALUES (?, ?, ?, ?)');
        $findArtist = $pdo->prepare('SELECT artist_id FROM artist WHERE artist_name = ? LIMIT 1');
        $newArtist = $pdo->prepare('INSERT IGNORE INTO artist (spotify_artist_id, artist_name) VALUES (?, ?)');
        $link = $pdo->prepare('INSERT IGNORE INTO track_artist (track_id, artist_id) VALUES (?, ?)');
        $artists = [];
        foreach ($tracks as $id => $t) {
            if (isset($trackIds[$id])) {
                continue;
            }
            $newTrack->execute([$id, $t['name'], $t['album'], $t['max']]); // the longest play is a fair stand-in for the length
            $trackIds[$id] = (int) $pdo->lastInsertId();
            if ($t['artist'] === '') {
                continue;
            }
            $key = mb_strtolower($t['artist']);
            if (!isset($artists[$key])) {
                $findArtist->execute([$t['artist']]);
                $aid = $findArtist->fetchColumn();
                if (!$aid) {
                    $newArtist->execute([artist_name_id($t['artist']), $t['artist']]);
                    $findArtist->execute([$t['artist']]);
                    $aid = $findArtist->fetchColumn();
                }
                $artists[$key] = (int) $aid;
            }
            $link->execute([$trackIds[$id], $artists[$key]]);
        }

        // plays, 500 per statement
        $new = 0;
        foreach (array_chunk($entries, 500) as $chunk) {
            $values = [];
            $args = [];
            foreach ($chunk as $e) {
                $values[] = '(?, ?, ?)';
                array_push($args, $uid, $trackIds[$e['id']], (new DateTime('@' . $e['ts']))->setTimezone($zone)->format('Y-m-d H:i:s'));
            }
            $st = $pdo->prepare('INSERT IGNORE INTO play_history (user_id, track_id, played_at) VALUES ' . implode(',', $values));
            $st->execute($args);
            $new += $st->rowCount();
        }
        return $new;
    });
}
