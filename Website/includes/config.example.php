<?php
// Copy this file to config.php and fill in the values. config.php is gitignored.
// Spotify dashboard: https://developer.spotify.com/dashboard -> your app -> Settings.
// Add the redirect URI below to the app exactly as written. Spotify needs https and 127.0.0.1
// (not "localhost"); XAMPP serves it with a self-signed certificate, so accept the browser warning once.
return [
    'client_id' => '',
    'client_secret' => '',
    'redirect_uri' => 'https://127.0.0.1/apollo_lyre/spotify_callback.php',
    'lastfm_key' => '', // free key from last.fm/api/account/create, used for artist genres
];
