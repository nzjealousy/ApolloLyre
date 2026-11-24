<?php
require __DIR__ . '/includes/history.php';
$user = require_login();

$error = '';
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(300);
    $files = $_FILES['history'] ?? null;
    if (!csrf_ok()) {
        $error = 'Session expired. Reload the page and try again.';
    } elseif (!$files || !is_array($files['name']) || $files['name'][0] === '') {
        $error = 'Choose at least one file.';
    } else {
        $found = $skipped = $new = $read = 0;
        for ($i = 0, $n = min(count($files['name']), 20); $i < $n; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $error = 'One file could not be uploaded (' . $files['name'][$i] . '). Files can be up to 40 MB each.';
                continue;
            }
            $name = strtolower($files['name'][$i]);
            $contents = [];
            if (str_ends_with($name, '.json')) {
                $contents[] = (string) file_get_contents($files['tmp_name'][$i]);
            } elseif (str_ends_with($name, '.zip')) {
                $zip = new ZipArchive();
                if ($zip->open($files['tmp_name'][$i]) === true) {
                    for ($z = 0; $z < $zip->numFiles; $z++) {
                        $stat = $zip->statIndex($z);
                        if (preg_match('~(^|/)Streaming_History_Audio[^/]*\.json$~i', $stat['name']) && $stat['size'] <= 200 * 1024 * 1024) {
                            $contents[] = (string) $zip->getFromIndex($z);
                        }
                    }
                    $zip->close();
                }
            }
            foreach ($contents as $json) {
                $read++;
                [$entries, $skip] = history_parse($json);
                $skipped += $skip;
                $found += count($entries);
                $new += history_import($user['id'], $entries);
            }
        }
        $result = compact('read', 'found', 'skipped', 'new');
        if ($read === 0 && !$error) {
            $error = 'No Spotify history files found. Use the Streaming_History_Audio files, or the zip Spotify sent you.';
        }
    }
}

$page = 'analytics';
$title = 'Import listening history';
require __DIR__ . '/includes/app_top.php';
?>
      <div class="page-head">
        <div>
          <h1>Import listening history</h1>
          <p>Add your older plays to your analytics.</p>
        </div>
        <a href="analytics.php" class="btn btn-ghost">Back to analytics</a>
      </div>

<?php if ($error): ?>
      <div class="form-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($result && $result['read'] > 0): ?>
      <div class="panel" role="status">
        <h2>Import finished</h2>
        <p><?= number_format($result['new']) ?> new plays added.
          <?= number_format($result['found'] - $result['new']) ?> were already saved and were ignored.
          <?= number_format($result['skipped']) ?> rows were skipped (plays under 30 seconds, podcasts, or rows without a Spotify song id).</p>
        <p class="muted">Covers and artist details for imported songs fill in over the next syncs.</p>
      </div>
<?php endif; ?>

      <div class="split split-even">
        <section class="panel" aria-labelledby="how-title">
          <h2 id="how-title">Get your history from Spotify</h2>
          <p class="muted">Spotify's API only returns your last 50 plays, so older history comes from a data download.</p>
          <ol class="steps-list">
            <li>Open <a class="accent" href="https://www.spotify.com/account/privacy/" target="_blank" rel="noopener">spotify.com/account/privacy</a>.</li>
            <li>Under "Download your data", tick <strong>Extended streaming history</strong> and request it.</li>
            <li>Confirm the email Spotify sends. The download can take up to 30 days to arrive.</li>
            <li>Upload the zip, or the <code>Streaming_History_Audio</code> files from it, here.</li>
          </ol>
        </section>

        <section class="panel" aria-labelledby="upload-title">
          <h2 id="upload-title">Upload</h2>
          <form action="import_history.php" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field">
              <label for="history-files">History files (.zip or .json, up to 20 files)</label>
              <input type="file" id="history-files" name="history[]" accept=".zip,.json,application/json,application/zip" multiple required>
              <span class="hint">Importing twice is safe: plays that are already saved are never added again.</span>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Import history</button>
            </div>
          </form>
        </section>
      </div>
<?php require __DIR__ . '/includes/app_bottom.php'; ?>
