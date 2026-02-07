<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$generated = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['crex_url'] ?? '');
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid Crex match URL.';
    } elseif (stripos($url, 'crex.com/scoreboard/') === false) {
        $error = 'URL must be a Crex live match scoreboard URL.';
    } else {
        $panelId = bin2hex(random_bytes(6));
        $config = [
            'panel_id' => $panelId,
            'url' => $url,
            'created_at' => time(),
            'team_a_name' => trim($_POST['team_a_name'] ?? ''),
            'team_b_name' => trim($_POST['team_b_name'] ?? ''),
            'team_a_flag' => trim($_POST['team_a_flag'] ?? ''),
            'team_b_flag' => trim($_POST['team_b_flag'] ?? ''),
        ];
        $configPath = $APP_CONFIG['cache_path'] . '/panel_' . $panelId . '_config.json';
        if (!is_dir($APP_CONFIG['cache_path'])) {
            mkdir($APP_CONFIG['cache_path'], 0775, true);
        }
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
        $generated = [
            'panel_id' => $panelId,
            'panel_url' => app_base_url() . '/panel.php?panel_id=' . $panelId,
            'fetch_url' => app_base_url() . '/fetch.php?panel_id=' . $panelId,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cricket Live Panel Generator</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard">
    <main class="dashboard-container">
        <header class="dashboard-header">
            <h1>Live Cricket OBS Panel</h1>
            <p>Paste a Crex live match URL to generate a real-time OBS browser source.</p>
        </header>

        <form method="post" class="dashboard-form">
            <label for="crex_url">Crex Live Match URL</label>
            <input type="url" id="crex_url" name="crex_url" placeholder="https://crex.com/scoreboard/.../live" required>
            <div class="form-grid">
                <div class="form-field">
                    <label for="team_a_name">Team A Name (optional)</label>
                    <input type="text" id="team_a_name" name="team_a_name" placeholder="Team A display name">
                </div>
                <div class="form-field">
                    <label for="team_b_name">Team B Name (optional)</label>
                    <input type="text" id="team_b_name" name="team_b_name" placeholder="Team B display name">
                </div>
                <div class="form-field">
                    <label for="team_a_flag">Team A Flag URL (optional)</label>
                    <input type="url" id="team_a_flag" name="team_a_flag" placeholder="https://example.com/team-a.png">
                </div>
                <div class="form-field">
                    <label for="team_b_flag">Team B Flag URL (optional)</label>
                    <input type="url" id="team_b_flag" name="team_b_flag" placeholder="https://example.com/team-b.png">
                </div>
            </div>
            <button type="submit">Generate OBS Panel</button>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
            <?php endif; ?>
        </form>

        <?php if ($generated): ?>
            <section class="dashboard-result">
                <h2>Panel Ready</h2>
                <div class="result-row">
                    <span>Panel ID</span>
                    <strong><?php echo htmlspecialchars($generated['panel_id'], ENT_QUOTES); ?></strong>
                </div>
                <div class="result-row">
                    <span>OBS Browser Source URL</span>
                    <input type="text" readonly value="<?php echo htmlspecialchars($generated['panel_url'], ENT_QUOTES); ?>">
                </div>
                <div class="result-row">
                    <span>Fetch Endpoint</span>
                    <input type="text" readonly value="<?php echo htmlspecialchars($generated['fetch_url'], ENT_QUOTES); ?>">
                </div>
                <ol class="instructions">
                    <li>Open OBS Studio and add a new Browser Source.</li>
                    <li>Paste the OBS Browser Source URL above.</li>
                    <li>Set width to 1280 and height to 720.</li>
                    <li>Enable "Refresh browser when scene becomes active".</li>
                </ol>
            </section>
        <?php endif; ?>

        <footer class="dashboard-footer">
            <p>Live data sourced from publicly available sources. Not affiliated with Crex.</p>
        </footer>
    </main>
</body>
</html>
