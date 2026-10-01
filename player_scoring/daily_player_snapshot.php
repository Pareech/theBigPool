<?php
// daily_player_snapshot.php
// Snapshots every active player's cumulative stats before games start.
// Run by cron at the same time as daily_positional_points.php:
//   01 16 * * MON-FRI  php /path/to/player_scoring/daily_player_snapshot.php
//   01 12 * * SAT,SUN  php /path/to/player_scoring/daily_player_snapshot.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../classes/logging_functions.php';

$timestamp = date('Y-m-d H:i:s');

$environment = '';
if ($dbname === 'RAW_HockeyPool_pre_prod') {
    $environment = 'PreProd';
} elseif ($dbname === 'RAW_HockeyPool_dev') {
    $environment = 'Dev';
} else {
    $environment = 'Prod';
}

// ------------------------------------------------------------------
// Fetch every player currently rostered (salaries) joined to their
// live stats in player_stats.  Waiver pickups are included as-is —
// the delta math in daily_player_pts.php already handles subtraction.
// ------------------------------------------------------------------
$rows = $pdo->query("
    SELECT
        s.gm,
        s.player,
        s.team,
        s.position,
        COALESCE(ps.goals,    0) AS goals,
        COALESCE(ps.assists,  0) AS assists,
        COALESCE(ps.wins,     0) AS wins,
        COALESCE(ps.shutouts, 0) AS shutouts,
        COALESCE(ps.otl,      0) AS otl
    FROM salaries s
    LEFT JOIN player_stats ps
        ON  LOWER(TRIM(s.player))   = LOWER(TRIM(ps.player))
        AND s.position              = ps.position
        AND TRIM(ps.team) ILIKE '%' || TRIM(s.team) || '%'
    WHERE s.gm IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    log_daily_pts_change("[{$timestamp}] ⚠️  daily_player_snapshot: no rows found in {$environment}.");
    exit;
}

$insert = $pdo->prepare("
    INSERT INTO daily_player_snapshot
        (gm, player, team, position, goals, assists, wins, shutouts, otl, record_date)
    VALUES
        (:gm, :player, :team, :position, :goals, :assists, :wins, :shutouts, :otl, CURRENT_DATE)
    ON CONFLICT (gm, player, position) DO UPDATE SET
        team        = EXCLUDED.team,
        goals       = EXCLUDED.goals,
        assists     = EXCLUDED.assists,
        wins        = EXCLUDED.wins,
        shutouts    = EXCLUDED.shutouts,
        otl         = EXCLUDED.otl,
        record_date = EXCLUDED.record_date
");

$count = 0;
foreach ($rows as $row) {
    $insert->execute([
        ':gm'       => $row['gm'],
        ':player'   => $row['player'],
        ':team'     => $row['team'],
        ':position' => $row['position'],
        ':goals'    => $row['goals'],
        ':assists'  => $row['assists'],
        ':wins'     => $row['wins'],
        ':shutouts' => $row['shutouts'],
        ':otl'      => $row['otl'],
    ]);
    $count++;
}

log_daily_pts_change("✅ daily_player_snapshot: saved {$count} player snapshots in {$environment}.");
