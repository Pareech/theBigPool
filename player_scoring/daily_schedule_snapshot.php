<?php
// daily_schedule_snapshot.php
// Fetches today's NHL schedule and saves the playing teams to daily_schedule table.
// Run by cron at the same time as daily_positional_points.php:
//   00 16 * * MON-FRI  php /path/to/player_scoring/daily_schedule_snapshot.php
//   00 12 * * SAT,SUN  php /path/to/player_scoring/daily_schedule_snapshot.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../classes/logging_functions.php';

$timestamp = date('Y-m-d H:i:s');
$today     = (new DateTime('now', new DateTimeZone('America/Toronto')))->format('Y-m-d');

$environment = '';
if ($dbname === 'RAW_HockeyPool_pre_prod') {
    $environment = 'PreProd';
} elseif ($dbname === 'RAW_HockeyPool_dev') {
    $environment = 'Dev';
} else {
    $environment = 'Prod';
}

// Map salaries.team abbreviations to NHL API abbreviations
$team_map = [
    'LA'  => 'LAK',
    'NJ'  => 'NJD',
    'SJ'  => 'SJS',
    'TB'  => 'TBL',
];
// Reverse map: NHL API abbrev -> salaries.team abbrev
$reverse_map = array_flip($team_map);

// ── Fetch today's schedule from NHL API ──────────────────────────────
$ch = curl_init("https://api-web.nhle.com/v1/schedule/" . $today);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0']);
$response   = curl_exec($ch);
$curl_error = curl_errno($ch);

if ($curl_error || !$response) {
    log_daily_pts_change("[{$timestamp}] ❌ daily_schedule_snapshot: could not fetch NHL schedule in {$environment}.");
    exit;
}

$schedule_data = json_decode($response, true);
if (!$schedule_data) {
    log_daily_pts_change("[{$timestamp}] ❌ daily_schedule_snapshot: invalid JSON from NHL API in {$environment}.");
    exit;
}

// ── Extract today's playing teams ────────────────────────────────────
$teams_today = [];

foreach ($schedule_data['gameWeek'] ?? [] as $day) {
    if ($day['date'] !== $today) {
        continue;
    }
    foreach ($day['games'] ?? [] as $game) {
        $away_api = $game['awayTeam']['abbrev'] ?? '';
        $home_api = $game['homeTeam']['abbrev'] ?? '';

        // Store both the NHL API abbrev AND the salaries abbrev
        // so the filter in gm_daily.php matches regardless of which version is stored
        if ($away_api) {
            $teams_today[] = $away_api;
            $away_sal = $reverse_map[$away_api] ?? null;
            if ($away_sal) $teams_today[] = $away_sal;
        }
        if ($home_api) {
            $teams_today[] = $home_api;
            $home_sal = $reverse_map[$home_api] ?? null;
            if ($home_sal) $teams_today[] = $home_sal;
        }
    }
}

$teams_today = array_unique($teams_today);

if (empty($teams_today)) {
    log_daily_pts_change("[{$timestamp}] ⚠️  daily_schedule_snapshot: no games found for {$today} in {$environment}.");
    exit;
}

// ── Clear old entries and today's existing entries, then re-insert ───
$delete = $pdo->prepare("DELETE FROM daily_schedule WHERE game_date <= :game_date");
$delete->execute(['game_date' => $today]);

$insert = $pdo->prepare("INSERT INTO daily_schedule (team, game_date)
                         VALUES (:team, :game_date)
                         ON CONFLICT (team, game_date) DO NOTHING");

foreach ($teams_today as $team) {
    $insert->execute([
        'team'      => $team,
        'game_date' => $today
    ]);
}

log_daily_pts_change("✅ daily_schedule_snapshot: saved " . count($teams_today) . " teams for {$today} in {$environment}.");
