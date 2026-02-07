<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$panelId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['panel_id'] ?? '');
if ($panelId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing panel_id']);
    exit;
}

$configPath = $APP_CONFIG['cache_path'] . '/panel_' . $panelId . '_config.json';
if (!is_file($configPath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Panel not found']);
    exit;
}

$config = json_decode((string)file_get_contents($configPath), true) ?? [];
$url = $config['url'] ?? '';

$cacheFile = $APP_CONFIG['cache_path'] . '/panel_' . $panelId . '_data.json';
$cacheTtl = (int)$APP_CONFIG['cache_ttl'];

if (is_file($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age <= $cacheTtl) {
        echo (string)file_get_contents($cacheFile);
        exit;
    }
}

function fetch_html(string $url, string $userAgent): ?string
{
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => [
            'Accept-Language: en-US,en;q=0.9',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer: https://crex.com/',
        ],
    ]);
    $html = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $status >= 400) {
        return null;
    }

    return $html;
}

function extract_text_nodes(DOMXPath $xpath, string $query): array
{
    $nodes = $xpath->query($query);
    $results = [];
    if ($nodes) {
        foreach ($nodes as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
            if ($text !== '') {
                $results[] = $text;
            }
        }
    }
    return $results;
}

function unique_values(array $values): array
{
    $unique = [];
    foreach ($values as $value) {
        if (!in_array($value, $unique, true)) {
            $unique[] = $value;
        }
    }
    return $unique;
}

function find_team_names(DOMXPath $xpath): array
{
    $raw = extract_text_nodes($xpath, '//h1|//h2|//h3');
    $names = [];
    foreach ($raw as $text) {
        $clean = trim($text);
        if (strlen($clean) > 0 && strlen($clean) < 25 && preg_match('/^[A-Za-z\s\.\&\-]+$/', $clean)) {
            $lower = strtolower($clean);
            if (strpos($lower, 'live') !== false || strpos($lower, 'scorecard') !== false || strpos($lower, 'match') !== false) {
                continue;
            }
            $names[] = $clean;
        }
    }
    return array_slice(unique_values($names), 0, 2);
}

function find_scores(DOMXPath $xpath): array
{
    $texts = extract_text_nodes($xpath, '//span|//div|//p');
    $scores = [];
    foreach ($texts as $text) {
        if (preg_match('/^\d{1,3}-\d{1,2}$/', $text)) {
            $scores[] = $text;
        }
    }
    return array_slice(unique_values($scores), 0, 2);
}

function find_overs(DOMXPath $xpath): array
{
    $texts = extract_text_nodes($xpath, '//span|//div|//p');
    $overs = [];
    foreach ($texts as $text) {
        if (preg_match('/^\d{1,2}\.\d$/', $text)) {
            $overs[] = $text;
        }
    }
    return array_slice(unique_values($overs), 0, 2);
}

function find_rate(DOMXPath $xpath, string $label): string
{
    $nodes = $xpath->query("//*[contains(translate(text(),'crr','CRR'),'$label')]");
    if ($nodes && $nodes->length > 0) {
        foreach ($nodes as $node) {
            $sibling = $node->nextSibling;
            while ($sibling && trim($sibling->textContent ?? '') === '') {
                $sibling = $sibling->nextSibling;
            }
            if ($sibling) {
                if (preg_match('/\d+(?:\.\d+)?/', $sibling->textContent ?? '', $matches)) {
                    return $matches[0];
                }
            }
        }
    }

    $texts = extract_text_nodes($xpath, '//*[contains(text(),"' . $label . '")]');
    foreach ($texts as $text) {
        if (preg_match('/' . $label . '\s*[:]?\s*(\d+(?:\.\d+)?)/i', $text, $matches)) {
            return $matches[1];
        }
    }
    return '';
}

function find_required_text(DOMXPath $xpath): string
{
    $nodes = $xpath->query("//*[contains(translate(.,'NEEDRUNS','needruns'),'need') and contains(translate(.,'NEEDRUNS','needruns'),'runs')]");
    if ($nodes) {
        foreach ($nodes as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
            if (preg_match('/need\s+\d+\s+runs\s+in\s+\d+\s+balls/i', $text, $matches)) {
                return $matches[0];
            }
        }
    }
    return '';
}

function find_partnership(DOMXPath $xpath): string
{
    $texts = extract_text_nodes($xpath, '//*[contains(text(),"Partnership") or contains(text(),"PARTNERSHIP")]');
    foreach ($texts as $text) {
        if (preg_match('/(\d+\s*\(\d+\))/i', $text, $matches)) {
            return $matches[1];
        }
    }
    return '';
}

function parse_table_rows(DOMXPath $xpath): array
{
    $rows = $xpath->query('//table//tr');
    $result = [];
    if ($rows) {
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell->nodeType === XML_ELEMENT_NODE) {
                    $text = trim(preg_replace('/\s+/', ' ', $cell->textContent ?? ''));
                    if ($text !== '') {
                        $cells[] = $text;
                    }
                }
            }
            if (count($cells) >= 2) {
                $result[] = $cells;
            }
        }
    }
    return $result;
}

function extract_batsmen(array $rows): array
{
    $batsmen = [];
    foreach ($rows as $cells) {
        $name = $cells[0] ?? '';
        if ($name === '' || !preg_match('/[A-Za-z]/', $name)) {
            continue;
        }
        $numeric = array_filter($cells, static fn($cell) => preg_match('/^\d+(?:\.\d+)?$/', $cell));
        if (count($numeric) >= 4) {
            $batsmen[] = [
                'name' => $name,
                'runs' => $cells[1] ?? '',
                'balls' => $cells[2] ?? '',
                'fours' => $cells[3] ?? '',
                'sixes' => $cells[4] ?? '',
                'sr' => $cells[5] ?? '',
            ];
        }
        if (count($batsmen) >= 2) {
            break;
        }
    }
    return $batsmen;
}

function extract_bowler(array $rows): array
{
    foreach ($rows as $cells) {
        $name = $cells[0] ?? '';
        $overs = $cells[1] ?? '';
        if ($name !== '' && preg_match('/[A-Za-z]/', $name) && preg_match('/^\d{1,2}\.\d$/', $overs)) {
            return [
                'name' => $name,
                'overs' => $overs,
                'runs' => $cells[2] ?? '',
                'wickets' => $cells[3] ?? '',
                'economy' => $cells[4] ?? '',
            ];
        }
    }
    return [];
}

function extract_balls(DOMXPath $xpath): array
{
    $texts = extract_text_nodes($xpath, '//span|//div|//p');
    $tokens = [];
    foreach ($texts as $text) {
        if (preg_match_all('/\b(0|1|2|3|4|5|6|W|wd|nb)\b/i', $text, $matches)) {
            foreach ($matches[1] as $token) {
                $tokens[] = strtolower($token);
            }
        }
    }
    $tokens = array_slice($tokens, -12);
    return $tokens;
}

function extract_status(DOMXPath $xpath): string
{
    $keywords = ['Review', 'DRS', 'Stumping'];
    foreach ($keywords as $keyword) {
        $nodes = $xpath->query("//*[contains(text(),'$keyword')]");
        if ($nodes && $nodes->length > 0) {
            $text = trim(preg_replace('/\s+/', ' ', $nodes->item(0)->textContent ?? ''));
            if ($text !== '') {
                return $text;
            }
        }
    }
    return '';
}

$html = fetch_html($url, $APP_CONFIG['default_user_agent']);
if ($html === null) {
    if (is_file($cacheFile)) {
        echo (string)file_get_contents($cacheFile);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unable to fetch live data', 'data' => []]);
    exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$teams = find_team_names($xpath);
$scores = find_scores($xpath);
$overs = find_overs($xpath);

$rows = parse_table_rows($xpath);
$batsmen = extract_batsmen($rows);
$bowler = extract_bowler($rows);

$data = [
    'ok' => true,
    'panel_id' => $panelId,
    'last_updated' => time(),
    'teams' => [
        [
            'name' => $teams[0] ?? '',
            'score' => $scores[0] ?? '',
            'overs' => $overs[0] ?? '',
        ],
        [
            'name' => $teams[1] ?? '',
            'score' => $scores[1] ?? '',
            'overs' => $overs[1] ?? '',
        ],
    ],
    'crr' => find_rate($xpath, 'CRR'),
    'rrr' => find_rate($xpath, 'RRR'),
    'required' => find_required_text($xpath),
    'partnership' => find_partnership($xpath),
    'status' => extract_status($xpath),
    'batsmen' => $batsmen,
    'bowler' => $bowler,
    'balls' => extract_balls($xpath),
];

file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_SLASHES));

echo json_encode($data, JSON_UNESCAPED_SLASHES);
