<?php
require __DIR__ . '/includes/spotify.php';
require_login();

if (!spotify_configured()) {
    header('Location: profile.php?spotify=config');
    exit;
}

$_SESSION['spotify_state'] = bin2hex(random_bytes(16));
$c = app_config();
header('Location: https://accounts.spotify.com/authorize?' . http_build_query([
    'response_type' => 'code',
    'client_id' => $c['client_id'],
    'redirect_uri' => $c['redirect_uri'],
    'scope' => 'user-read-recently-played playlist-modify-private playlist-modify-public playlist-read-private playlist-read-collaborative',
    'state' => $_SESSION['spotify_state'],
]));
exit;
