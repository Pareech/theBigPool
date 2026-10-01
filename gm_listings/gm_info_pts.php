<link rel='stylesheet' type='text/css' href='../css/gm_info_pts.css?v=1' />

<?php

function ordinal_suffix($num)
{
    if ($num % 100 >= 11 && $num % 100 <= 13) {
        return $num . '<sup>th</sup>';
    }

    switch ($num % 10) {
        case 1:
            return $num . '<sup>st</sup>';
        case 2:
            return $num . '<sup>nd</sup>';
        case 3:
            return $num . '<sup>rd</sup>';
        default:
            return $num . '<sup>th</sup>';
    }
}

//Get GM Points / Standings info
include __DIR__ . '/../player_scoring/standing_pts.php';
$stmt = $standing_pts;
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// build gm_order
$gm_order = [];
foreach ($rows as $r) {
    $gm_order[] = $r['gm_name'];
}

$gm_param = $_GET['gm'];

$gm_position_index = array_search(strtolower($gm_param), array_map('strtolower', $gm_order));
$gm_position = $gm_position_index + 1;

// pull totals
$gm_total_points = null;
$gm_avg_pts      = null;

foreach ($rows as $r) {
    if ($r['gm_name'] === $gm_param) {
        $gm_total_points = $r['total_points'];
        $gm_avg_pts      = $r['avg_pts'];
        $gm_pts_behind   = $r['points_behind'];
    }
}
// End of GM Points / Standings Info section

// Daily change: current total minus this morning's snapshot
$get_daily_change = $pdo->prepare("SELECT total_points FROM daily_positional_totals WHERE gm = :gm");
$get_daily_change->execute(['gm' => $gm_param]);
$snapshot_pts = $get_daily_change->fetchColumn();

$daily_change = $gm_total_points - (int)$snapshot_pts;
$daily_change_display = ($daily_change > 0 ? '+' : '') . $daily_change;

$gm_total_points = htmlspecialchars($gm_total_points);
$gm_avg_pts      = htmlspecialchars($gm_avg_pts);
$gm_position     = ordinal_suffix($gm_position);

if (htmlspecialchars($gm_pts_behind) != 0) {
    $gm_pts_behind = htmlspecialchars($gm_pts_behind);
} else {
    $gm_pts_behind = '-';
}
?>


<!-- Verify the draft status -->
<table class="info">
    <tr>
        <td id="points"> <?= $gm_total_points ?> </td>
        <td id="titles"> RAW Points </td>
    </tr>
    <tr>
        <td id="points"><?= $gm_avg_pts ?></td>
        <td id="titles"> Points per Game </td>
    </tr>
    <tr>
        <td id="points"><?= $gm_position ?></td>
        <td id="titles"> Place </td>
    </tr>
    <tr>
        <td id="points"><?= $gm_pts_behind ?></td>
        <td id="titles"> Points Behind Leader </td>
    </tr>
    <tr>
        <td id="points"><?= $daily_change_display ?></td>
        <td id="titles"> Daily Change </td>
    </tr>
</table>