<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

include __DIR__ . '/../db_connections/connection_pdo.php';

// Overall Pool Standings
include 'standing_pts.php';

// Source of Points (goals, assists, wins, SHO, OTL)
include 'source_of_pts.php';

// Forward Points per GM
include 'fwd_pts.php';

// Points by Defencemen per GM
include 'def_pts.php';

// Points by Goalie per GM
include 'goalie_pts.php';

// For Logging
include __DIR__ . '/../classes/logging_functions.php';


$timestamp = date('Y-m-d H:i:s'); // For logs


// Fetch results directly from standings_pts
$allTotals = $standing_pts->fetchAll(PDO::FETCH_ASSOC);

// Fetch results directly from fwd_pts
$fwdTotals = $fwd_points->fetchAll(PDO::FETCH_ASSOC);

// Fetch results directly from def_pts
$defTotals = $def_points->fetchAll(PDO::FETCH_ASSOC);

// Fetch results directly from goalie_pts
$goalieTotals = $goalie_points->fetchAll(PDO::FETCH_ASSOC);

// For the logs
$environment = '';
if ($dbname === 'RAW_HockeyPool_pre_prod') {
    $environment = 'PreProd';
} else {
    $environment = 'Prod';
}

if (empty($allTotals)) {
    throw new RuntimeException('[' . $timestamp .'] get_positional_points.php did not produce any results in ' . $environment);
}

// ---------- Build lookup arrays ----------
$fwdMap = [];
foreach ($fwdTotals as $row) {
    $gm = $row['gm_name'];

    $fwdMap[$gm] = [
        'fwd_pts'     => $row['fwd_points'],
        'fwd_goals'   => $row['fwd_total_goals'],
        'fwd_assists' => $row['fwd_total_assists'],
    ];
}

$defMap    = [];
foreach ($defTotals as $row) {
    $gm = $row['gm_name'];

    $defMap[$gm] = [
        'def_pts'     => $row['def_points'],
        'def_goals'   => $row['def_total_goals'],
        'def_assists' => $row['def_total_assists'],
    ];
}


$goalieMap = [];
foreach ($goalieTotals as $row) {
    $gm = $row['gm_name'];

    $goalieMap[$gm] = [
        'goalie_pts'      => $row['goalie_points'],
        'goalie_goals'    => $row['goalie_total_goals'],
        'goalie_assists'  => $row['goalie_total_assists'],
        'goalie_wins'     => $row['goalie_total_wins'],
        'goalie_otl'      => $row['goalie_total_otl'],
        'goalie_shutouts' => $row['goalie_total_shutouts']
    ];
}


// ---------- Prepare final INSERT/UPDATE ----------
$insert = $pdo->prepare("INSERT INTO daily_positional_totals 
                                     (gm, total_points, fwd_pts, def_pts, goalie_pts, fwd_goals, fwd_assists, def_goals, def_assists, 
                                      goalie_goals, goalie_assists, goalie_wins, goalie_otl, goalie_shutouts, record_date)
                         VALUES 
                             (:gm, :total_points, :fwd_pts, :def_pts, :goalie_pts, :fwd_goals, :fwd_assists, :def_goals, :def_assists, 
                              :goalie_goals, :goalie_assists, :goalie_wins, :goalie_otl, :goalie_shutouts, CURRENT_DATE)
                         ON CONFLICT (gm)
                         DO UPDATE SET
                             total_points    = EXCLUDED.total_points,
                             fwd_pts         = EXCLUDED.fwd_pts,
                             fwd_goals       = EXCLUDED.fwd_goals,
                             fwd_assists     = EXCLUDED.fwd_assists,
                             def_pts         = EXCLUDED.def_pts,
                             def_goals       = EXCLUDED.def_goals,
                             def_assists     = EXCLUDED.def_assists,
                             goalie_pts      = EXCLUDED.goalie_pts,
                             goalie_goals    = EXCLUDED.goalie_goals,
                             goalie_assists  = EXCLUDED.goalie_assists,
                             goalie_wins     = EXCLUDED.goalie_wins,
                             goalie_otl      = EXCLUDED.goalie_otl,
                             goalie_shutouts = EXCLUDED.goalie_shutouts,
                             record_date     = EXCLUDED.record_date");


foreach ($allTotals as $row) {
    $gm = $row['gm_name'];

    $insert->execute([
        'gm'              => $gm,
        'total_points'    => $row['total_points'],
        'fwd_pts'         => $fwdMap[$gm]['fwd_pts'] ?? 0,
        'fwd_goals'       => $fwdMap[$gm]['fwd_goals'] ?? 0,
        'fwd_assists'     => $fwdMap[$gm]['fwd_assists']  ?? 0,
        'def_pts'         => $defMap[$gm]['def_pts'] ?? 0,
        'def_goals'       => $defMap[$gm]['def_goals'] ?? 0,
        'def_assists'     => $defMap[$gm]['def_assists']  ?? 0,
        'goalie_pts'      => $goalieMap[$gm]['goalie_pts'] ?? 0,
        'goalie_goals'    => $goalieMap[$gm]['goalie_goals'] ?? 0,
        'goalie_assists'  => $goalieMap[$gm]['goalie_assists']  ?? 0,
        'goalie_wins'     => $goalieMap[$gm]['goalie_wins']  ?? 0,
        'goalie_otl'      => $goalieMap[$gm]['goalie_otl']  ?? 0,
        'goalie_shutouts' => $goalieMap[$gm]['goalie_shutouts']  ?? 0
    ]);
}


log_daily_pts_change("✅ Snapshot saved for " . count($allTotals) . " GMs in {$environment}.");


?>