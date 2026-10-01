<!-- Verify if the season has started (post draft day) -->

<?php
$get_season_status = $pdo->query("SELECT season_start, season_end FROM base_numbers;");
$get_season_status->execute();

foreach ($get_season_status as $row) {
    $season_start = $row['season_start'];
    $season_end = $row['season_end'];
}

$today = date('Y-m-d');

if ($today < $season_start || $today >= $season_end) {
    $round = '';
    $pick_number = 'Pre-Season';
    $picking_now = '';
} else {
    $pick_number = NULL;
}
?>