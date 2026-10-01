<?php
// Block browser access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../classes/logging_functions.php';
include __DIR__ . '/../classes/fantrax_alert.php';


$timestamp = date('Y-m-d H:i:s');

// Determine environment
$environment = '';
if ($dbname === 'RAW_HockeyPool_pre_prod') {
    $environment = 'PreProd';
} elseif ($dbname === 'RAW_HockeyPool_dev') {
    $environment = 'Dev';
} else {
    $environment = 'Prod';
}

// --------- FANTRAX GOALIE ENDPOINT ----------
$fantraxUrl = "https://www.fantrax.com/fxpa/req";

$payload = [
    "msgs" => [
        [
            "method" => "getStatsFull",
            "data" => [
                "sportCode" => "NHL",
                // "seasonId" => "31l",
                "scKindId" => "3030",   // GOALIE STATS
                "statsType" => "players",
                "newView" => true
            ]
        ]
    ],
    "uiv" => 3,
    "at" => 0,
    "av" => "0.0",
    "dt" => 0,
    // "refUrl" => "https://www.fantrax.com/news/nhl/stats/players;scKindId=3030;seasonId=31l?sortKey=PT&sortDir=1",
    "refUrl" => "https://www.fantrax.com/news/nhl/stats/players;scKindId=3030",
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
    die("[$timestamp] ❌ Error: Could not fetch Fantrax goalie stats in {$environment}.\n");
}

$jsonData = json_decode($response, true);
if (!$jsonData) {
    die("[$timestamp] ❌ Error: Invalid JSON returned from Fantrax in {$environment}.\n");
}

// Check for Fantrax version mismatch
if (isset($jsonData['pageError']['code']) && $jsonData['pageError']['code'] === 'STALE_CLIENT') {
    log_goalie_stats("❌ Fantrax STALE_CLIENT error in {$environment}. Update the version number in get_goalie_stats.php.");
    send_stale_client_alert('get_goalie_stats.php', $environment);
    die("[$timestamp] ❌ Fantrax STALE_CLIENT error in {$environment}.\n");
}

$rows = $jsonData['responses'][0]['data']['stats']['rows'] ?? [];

$count = 0;

foreach ($rows as $row) {

    if (!isset($row['scorer']) || !isset($row['stats'])) {
        continue;
    }

    $scorer = $row['scorer'];
    $stats  = $row['stats'];

    // Extract basic info
    $player = normalizeName($scorer['name'] ?? '');
    $team   = $scorer['teamShortName'] ?? '';

    if ($player === '' || $team === '') {
        continue;
    }

    // Hard-code goalie position
    $position = 'G';

    // Extract stats from index positions
    $gp   = isset($stats[1]) ? (int)$stats[1] : 0;
    $wins = isset($stats[2]) ? (int)$stats[2] : 0;
    $otl  = isset($stats[4]) ? (int)$stats[4] : 0;
    $so   = isset($stats[7]) ? (int)$stats[7] : 0;

    // Fantrax does NOT include goalie goals or assists
    $goals   = 0;
    $assists = 0;

    // Insert / update
    $sql = "INSERT INTO player_stats (player, team, position, gp, wins, otl, shutouts, goals, assists, game_date)
            VALUES (:player, :team, :position, :gp, :wins, :otl, :shutouts, :goals, :assists, NOW())
            ON CONFLICT (player, team, position) DO UPDATE
            SET team = EXCLUDED.team,
                position = EXCLUDED.position,
                gp = EXCLUDED.gp,
                wins = EXCLUDED.wins,
                otl = EXCLUDED.otl,
                shutouts = EXCLUDED.shutouts,
                goals = EXCLUDED.goals,
                assists = EXCLUDED.assists,
                game_date = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':player'   => $player,
        ':team'     => $team,
        ':position' => $position,
        ':gp'       => $gp,
        ':wins'     => $wins,
        ':otl'      => $otl,
        ':shutouts' => $so,
        ':goals'    => $goals,
        ':assists'  => $assists
    ]);

    $count++;
}

log_goalie_stats("✅ Imported/updated {$count} goalie records from Fantrax in {$environment}.");
clear_stale_client_flag('get_goalie_stats.php');


// --------- NAME NORMALIZER ----------
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