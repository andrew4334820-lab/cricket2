<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$panelId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['panel_id'] ?? '');
if ($panelId === '') {
    http_response_code(400);
    echo 'Missing panel_id';
    exit;
}

$configPath = $APP_CONFIG['cache_path'] . '/panel_' . $panelId . '_config.json';
if (!is_file($configPath)) {
    http_response_code(404);
    echo 'Panel not found';
    exit;
}

$baseUrl = app_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Cricket Panel</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="panel-body">
    <div class="panel-canvas">
        <div class="panel-background"></div>
        <header class="panel-header">
            <div class="team-block" id="team-a">
                <div class="team-flag" id="team-a-flag">A</div>
                <div class="team-details">
                    <div class="team-name" id="team-a-name">Team A</div>
                    <div class="team-score">
                        <span id="team-a-score">0-0</span>
                        <span class="team-overs" id="team-a-overs">0.0 ov</span>
                    </div>
                </div>
            </div>
            <div class="status-box" id="match-status">Live</div>
            <div class="team-block team-block-right" id="team-b">
                <div class="team-details">
                    <div class="team-name" id="team-b-name">Team B</div>
                    <div class="team-score">
                        <span id="team-b-score">0-0</span>
                        <span class="team-overs" id="team-b-overs">0.0 ov</span>
                    </div>
                </div>
                <div class="team-flag" id="team-b-flag">B</div>
            </div>
        </header>

        <section class="metrics-bar">
            <div class="metric">
                <span class="metric-label">CRR</span>
                <span class="metric-value" id="metric-crr">0.00</span>
            </div>
            <div class="metric">
                <span class="metric-label">RRR</span>
                <span class="metric-value" id="metric-rrr">0.00</span>
            </div>
            <div class="metric">
                <span class="metric-label">Partnership</span>
                <span class="metric-value" id="metric-partnership">0 (0)</span>
            </div>
            <div class="metric trophy">
                <span class="metric-label">Required</span>
                <span class="metric-value" id="metric-required">Need 0 runs in 0 balls</span>
            </div>
        </section>

        <section class="player-cards">
            <div class="player-card" id="batsman-1">
                <img src="assets/images/player.svg" alt="Batsman" class="player-image">
                <div class="player-info">
                    <div class="player-name" id="batsman-1-name">Batsman 1</div>
                    <div class="player-stats" id="batsman-1-stats">0 (0) · 0x4 · 0x6 · SR 0.0</div>
                </div>
            </div>
            <div class="player-card" id="batsman-2">
                <img src="assets/images/player.svg" alt="Batsman" class="player-image">
                <div class="player-info">
                    <div class="player-name" id="batsman-2-name">Batsman 2</div>
                    <div class="player-stats" id="batsman-2-stats">0 (0) · 0x4 · 0x6 · SR 0.0</div>
                </div>
            </div>
            <div class="player-card bowler" id="bowler-1">
                <img src="assets/images/player.svg" alt="Bowler" class="player-image">
                <div class="player-info">
                    <div class="player-name" id="bowler-1-name">Bowler</div>
                    <div class="player-stats" id="bowler-1-stats">0.0-0-0 · Econ 0.0</div>
                </div>
            </div>
        </section>

        <section class="ball-strip" id="ball-strip">
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
            <div class="ball" data-value="0">0</div>
        </section>

        <footer class="panel-footer">
            Live data sourced from publicly available sources. Not affiliated with Crex.
        </footer>
    </div>

    <script>
        window.PANEL_CONFIG = {
            panelId: <?php echo json_encode($panelId); ?>,
            fetchUrl: <?php echo json_encode($baseUrl . '/fetch.php?panel_id=' . $panelId); ?>
        };
    </script>
    <script src="assets/js/panel.js"></script>
</body>
</html>
