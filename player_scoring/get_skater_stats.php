<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../classes/logging_functions.php';
include __DIR__ . '/../classes/fantrax_alert.php';


$timestamp = date('Y-m-d H:i:s'); // For logs

// For the logs determine environment
$environment = '';
if ($dbname === 'RAW_HockeyPool_dev') {
    $environment = 'Dev';
} elseif ($dbname === 'RAW_HockeyPool_pre_prod') {
    $environment = 'PreProd';
} else {
    $environment = 'Prod';
}

// --- FANTRAX JSON FETCH ---
$fantraxUrl = "https://www.fantrax.com/fxpa/req";
$payload = [
    "msgs" => [
        [
            "method" => "getStatsFull",
            "data" => [
                "sportCode" => "NHL",
                // "seasonId" => "31l",
                "scKindId" => "3010",
                "statsType" => "players",
                "newView" => true
            ]
        ]
    ],
    "uiv" => 3,
    "at" => 0,
    "av" => "0.0",
    "dt" => 0,
    // "refUrl" => "https://www.fantrax.com/news/nhl/stats/players;scKindId=3010;seasonId=31l?sortKey=PT&sortDir=1",
    "refUrl" => "https://www.fantrax.com/news/nhl/stats/players;scKindId=3010",
    "tz" => "America/Toronto",
    "v" => "181.0.3"
];

$ch = curl_init($fantraxUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0'
]);
$response = curl_exec($ch);

if ($response === false) {
    die("[$timestamp] ❌ Error: Could not fetch stats from Fantrax in {$environment}.\n");
}

$jsonData = json_decode($response, true);
if (!$jsonData) {
    die("[$timestamp] ❌ Error: Invalid JSON returned from Fantrax in {$environment}.\n");
}

// Check for Fantrax version mismatch
if (isset($jsonData['pageError']['code']) && $jsonData['pageError']['code'] === 'STALE_CLIENT') {
    log_skater_stats("❌ Fantrax STALE_CLIENT error in {$environment}. Update the version number in get_skater_stats.php.");
    send_stale_client_alert('get_skater_stats.php', $environment);
    die("[$timestamp] ❌ Fantrax STALE_CLIENT error in {$environment}.\n");
}

// Skater rows
$rows = $jsonData['responses'][0]['data']['stats']['rows'] ?? [];
$count = 0;
$skipped = 0;

foreach ($rows as $row) {
    $scorer = $row['scorer'] ?? [];
    $stats = $row['stats'] ?? [];

    if (!$scorer || !$stats) continue;

    $playerRaw  = $scorer['name'] ?? '';
    $team       = $scorer['teamShortName'] ?? '';
    $posRaw     = $stats[0] ?? '';
    $gpRaw      = $stats[1] ?? 0;
    $goalsRaw   = $stats[2] ?? 0;
    $assistsRaw = $stats[3] ?? 0;

    if ($playerRaw === '' || $team === '') continue;

    // Normalize player name
    $player = normalizeName($playerRaw);

    // Map positions
    $posClean = strtoupper(trim($posRaw));
    if (strpos($posClean, 'G') !== false) {
        $skipped++;
        continue;
    } elseif (strpos($posClean, 'D') !== false) {
        $position = 'D';
    } else {
        $position = 'F';
    }

    $gp      = is_numeric($gpRaw)      ? (int)$gpRaw      : 0;
    $goals   = is_numeric($goalsRaw)   ? (int)$goalsRaw   : 0;
    $assists = is_numeric($assistsRaw) ? (int)$assistsRaw : 0;

    $sql = "INSERT INTO player_stats (player, team, position, gp, goals, assists, game_date)
            VALUES (:player, :team, :position, :gp, :goals, :assists, NOW())
            ON CONFLICT (player, team, position) DO UPDATE
            SET team = EXCLUDED.team,
                position = EXCLUDED.position,
                gp = EXCLUDED.gp,
                goals = EXCLUDED.goals,
                assists = EXCLUDED.assists,
                game_date = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':player'   => $player,
        ':team'     => $team,
        ':position' => $position,
        ':gp'       => $gp,
        ':goals'    => $goals,
        ':assists'  => $assists
    ]);

    $count++;
}

log_skater_stats("✅ Imported/updated $count skaters from Fantrax in $environment.");
clear_stale_client_flag('get_skater_stats.php');


// Normalize player names function
function normalizeName($name)
{
    $name = mb_convert_encoding($name, 'UTF-8', 'auto');

    if (function_exists('transliterator_transliterate')) {
        $name = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);
    } else {
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    }

    $name = preg_replace('/[^A-Za-z0-9 \'-]/', '', $name);
    return trim($name);
}
