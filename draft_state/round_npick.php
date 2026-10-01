<link rel='stylesheet' type='text/css' href='../css/round_npick.css' />

<?php
// Get Info to Calculate Round Number
$rounds_completed = $pdo->query("SELECT numb_rds, draft_players FROM base_numbers;")->fetch(PDO::FETCH_ASSOC);

$numb_rds = $rounds_completed['numb_rds'];
$picks_per_gm = $rounds_completed['draft_players'];

// This will allow number of GMs to be updated dynamically
$numb_gms = $pdo->query("SELECT count(gm_name) FROM gms")->fetchColumn();

// Get Pick Number for the Round
$count_picks = $pdo->query("SELECT count(drafted) AS picks FROM salaries WHERE gm IS NOT NULL;")->fetchColumn();

//Find latest pick
$latest_pick = $pdo->query("SELECT player, gm
                            FROM salaries
                            WHERE latest_pick = 'x'")->fetch(PDO::FETCH_ASSOC);

$latest_player = $latest_pick['player'] ?? '';
$latest_gm = $latest_pick['gm'] ?? '';

if ($latest_gm == '' OR $latest_gm == '') {
    $last_pick = '';
} else {
    $last_pick = $latest_player . " by " . $latest_gm;
}


if ($count_picks % $numb_gms == 0) {
    $pick_number = 1;
} else {
    $pick_number = ($count_picks % $numb_gms) + 1;
}

$round = (int)($count_picks / $numb_gms) % (int)$numb_rds + 1;
$choose_round = 'round_' . $round; //Used to create rounds for $gm_picking variable

$gm_picking = ("SELECT draft_pos, $choose_round 
                FROM (SELECT draft_pos, $choose_round, row_number() over(order by draft_pos ASC) AS rn
                      FROM draft_order
                     ) AS picking
                WHERE picking.rn = $pick_number
                ORDER BY draft_pos ASC;");

foreach ($pdo->query($gm_picking) as $row) {
    $picking_now = $row[$choose_round];
}

if ($count_picks >= $picks_per_gm * $numb_gms) {
    $round = 'Draft';
    $pick_number = 'Complete';
    $picking_now = ' ';
    $last_pick = ' ';
}

// Verify if the season has started (post draft day)
$get_season_status = $pdo->query("SELECT season_start, season_end FROM base_numbers;");
$get_season_status->execute();

$row = $get_season_status->fetch(PDO::FETCH_ASSOC);
$season_start = $row['season_start'];
$season_end   = $row['season_end'];

$today = date('Y-m-d');

if ($today < $season_start || $today >= $season_end) {
    $round = '';
    $pick_number = 'Pre-Season';
    $picking_now = '';
    $last_pick = '';
}
?>

<!-- Verify the draft status -->
<table class="info">
    <tr>
        <td id="heading">GM Now Drafting</td>
        <td id="draft_info"> <?php echo htmlspecialchars($picking_now); ?> </td>
    </tr>
    <tr>
        <td id="heading">Round</td>
        <td id="draft_info"> <?php echo htmlspecialchars($round); ?> </td>
    </tr>
    <tr>
        <td id="heading">Pick</td>
        <td id="draft_info"> <?php echo htmlspecialchars($pick_number); ?> </td>
    </tr>
    <tr>
        <td id="heading">Last Player Chosen</td>
        <td id="latest_pick"> <?php echo htmlspecialchars($last_pick ?? ''); ?> </td>
    </tr>
</table>