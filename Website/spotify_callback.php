<?php
require __DIR__ . '/includes/spotify.php';
$user = require_login();

$state = (string) ($_GET['state'] ?? '');
$expected = $_SESSION['spotify_state'] ?? '';
unset($_SESSION['spotify_state']);

$result = 'error';
if ($expected !== '' && hash_equals($expected, $state) && isset($_GET['code']) && spotify_configured()) {
    $tok = spotify_token([
        'grant_type' => 'authorization_code',
        'code' => (string) $_GET['code'],
        'redirect_uri' => app_config()['redirect_uri'],
    ]);
    if ($tok) {
        [$status, $me] = spotify_http('GET', 'https://api.spotify.com/v1/me', ['Authorization: Bearer ' . $tok['access_token']]);
        if ($status === 200 && isset($me['id'])) {
            spotify_store($user['id'], $me['id'], $tok, $tok['refresh_token'] ?? null);
            $result = 'connected';
            unset($_SESSION['sync_checked']); // sync again right away with the new connection
        }
    }
}

header('Location: profile.php?spotify=' . $result);
exit;
