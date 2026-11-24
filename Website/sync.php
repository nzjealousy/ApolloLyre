<?php
require __DIR__ . '/includes/plays.php';
require __DIR__ . '/includes/genres.php';
$user = require_login();

// Pages call this after they load, so the slow requests never delay a page view. At most once an hour per session.
$due = isset($_GET['check']) && spotify_account($user['id']) && ($_SESSION['sync_checked'] ?? 0) < time() - 3600;
if ($due) {
    $_SESSION['sync_checked'] = time();
}
session_write_close(); // release the session lock before the slow part

$imported = 0;
if ($due) {
    $imported = plays_import($user['id']) ?? 0;
    art_backfill($user['id']);
    genres_fill($user['id']);
}

header('Content-Type: application/json');
echo json_encode(['imported' => $imported]);
