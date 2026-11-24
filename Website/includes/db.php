<?php
declare(strict_types=1);

// Play times and charts use this zone. Change it if you are elsewhere.
date_default_timezone_set('Asia/Kuala_Lumpur');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Inline style for a cover-art thumbnail, or "" when there is no (safe) image URL. */
function art_style(?string $url): string {
    return $url !== null && preg_match('~^https://[A-Za-z0-9./_-]+$~', $url) ? " style=\"background-image:url('" . $url . "')\"" : '';
}

/** "  Chill ,study,CHILL " becomes "CHILL, study": trimmed, distinct, 8 tags of 24 characters at most. Null when empty. */
function tags_normalize(string $raw): ?string {
    $tags = [];
    foreach (explode(',', $raw) as $tag) {
        $tag = trim(preg_replace('/\s+/u', ' ', $tag));
        if ($tag !== '') {
            $tags[mb_strtolower($tag)] = mb_substr($tag, 0, 24);
        }
    }
    return $tags ? implode(', ', array_slice(array_values($tags), 0, 8)) : null;
}

/** Chips for a stored tag string. */
function tag_chips(?string $tag): string {
    $html = '';
    foreach (array_filter(array_map('trim', explode(',', (string) $tag))) as $t) {
        $html .= '<span class="chip">' . e($t) . '</span>';
    }
    return $html === '' ? '' : '<div class="tags">' . $html . '</div>';
}

/** Settings from includes/config.php (gitignored), with empty defaults. */
function app_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        $cfg = (is_file($file) ? require $file : []) + ['client_id' => '', 'client_secret' => '', 'redirect_uri' => '', 'lastfm_key' => ''];
    }
    return $cfg;
}

/** 185000 becomes "3:05". */
function fmt_duration(int $ms): string {
    return intdiv($ms, 60000) . ':' . str_pad((string) (intdiv($ms, 1000) % 60), 2, '0', STR_PAD_LEFT);
}

/** SQL for the comma-separated artist names of a track; $trackIdSql is the track id column in the surrounding query. */
function artists_sql(string $trackIdSql): string {
    return '(SELECT GROUP_CONCAT(a.artist_name ORDER BY a.artist_name SEPARATOR ", ")
             FROM track_artist ta JOIN artist a ON a.artist_id = ta.artist_id WHERE ta.track_id = ' . $trackIdSql . ')';
}

/** The "connect Spotify first" box shown on pages that need a Spotify connection. */
function connect_panel(string $text): void {
    echo '<div class="panel"><p class="muted">' . e($text) . '</p><p><a class="btn btn-primary" href="spotify_connect.php">Connect Spotify</a></p></div>';
}

/** Runs $fn inside a database transaction; rolls back and rethrows if it fails. Returns what $fn returns. */
function db_transaction(callable $fn): mixed {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // XAMPP defaults: root with no password. Change here for another server.
            $pdo = new PDO('mysql:host=localhost;dbname=apollo_lyre;charset=utf8mb4', 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('Database unavailable. Start MySQL in XAMPP and check the apollo_lyre database.');
        }
    }
    return $pdo;
}

function csrf_field(): string {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

function csrf_ok(): bool {
    return hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''));
}

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        $user = null;
        if (isset($_SESSION['uid'])) {
            $st = db()->prepare('SELECT u.user_id AS id, u.name AS username, u.email, u.created_at,
                        EXISTS (SELECT 1 FROM spotify_account s WHERE s.user_id = u.user_id) AS spotify_connected
                 FROM users u WHERE u.user_id = ?');
            $st->execute([$_SESSION['uid']]);
            $user = $st->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['uid']);
            }
        }
    }
    return $user;
}

function require_login(): array {
    $user = current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    return $user;
}
