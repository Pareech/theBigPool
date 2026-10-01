<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GM's Waiver Picks</title>
    <link rel='stylesheet' type='text/css' href='../css/waivers.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>
<body>

<?php
$gm = ucfirst(strtolower($_SESSION['gm_name']));
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

// Check if season has started
$season_started = $pdo->query("SELECT season_start FROM base_numbers")->fetchColumn();

$today = new DateTime();
$season_started = new DateTime($season_started);
$post_draft = clone $season_started;
$post_draft->modify('+1 day');

if ($today <= $post_draft) {
    $post_draft_fmt = $post_draft->format("F jS, Y");
    echo "<script>
        alert('The season has not started. Waivers are currently closed.\\n\\n'
            + 'Waivers will be available as of $post_draft_fmt after\\n'
            + 'the draft has been completed.');
        window.location.href='../draft_setup/draft_info.php';
    </script>";
    exit;
}

// Select the Correct Waiver Period Logic Start
$stmt = $pdo->query("SELECT waiver1, waiver2, waiver3, max_waiver_slots, max_waivers FROM base_numbers");
$waiver_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$waiver_data) {
    throw new RuntimeException('Could not fetch waiver period data from base_numbers.');
}

$waiver1 = new DateTime($waiver_data['waiver1']);
$waiver2 = new DateTime($waiver_data['waiver2']);
$waiver3 = new DateTime($waiver_data['waiver3']);

$max_waiver_slots = (int)$waiver_data['max_waiver_slots'];
$max_waivers      = (int)$waiver_data['max_waivers'];

$today   = $today->format('Y-m-d');
$waiver1 = $waiver1->format('Y-m-d');
$waiver2 = $waiver2->format('Y-m-d');
$waiver3 = $waiver3->format('Y-m-d');

$waiver_period = null;
$waiver_title  = null;

if ($today <= $waiver1) {
    $waiver_period = 'waiver_1';
    $waiver_title  = 'Round 1';
} elseif ($today <= $waiver2) {
    $waiver_period = 'waiver_2';
    $waiver_title  = 'Round 2';
} elseif ($today <= $waiver3) {
    $waiver_period = 'waiver_3';
    $waiver_title  = 'Round 3';
} else {
    echo "<script>
        alert('Waivers are closed for this season.');
        window.location.href='../draft_setup/draft_info.php';
    </script>";
    exit;
}
?>

<div class="header">
    <h1>
        <?= htmlspecialchars($gm) ?>'s
        <span style="color:#32cd32;"><?= htmlspecialchars((string)$waiver_title) ?></span>
        <br> Waiver Selections
    </h1>
</div>

<?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

<?php
$gm_waiver_picks = $pdo->prepare("SELECT waiver_pick, waiver_bid, waiver_drop, waiver_option
                                  FROM waiver_draft
                                  WHERE gm = :gm_picks
                                    AND waiver_period = :waiver_period
                                  ORDER BY waiver_option");
$gm_waiver_picks->execute([':gm_picks' => $gm, ':waiver_period' => $waiver_period]);
?>

<div class="div_table_waivers">
    <div class="table-scroll">
    <table class="waiver_wins">
        <tr>
            <th>Waiver Pickup</th>
            <th>Bid</th>
            <th>Player Dropping</th>
            <th>Waiver Choice #</th>
        </tr>

        <?php foreach ($gm_waiver_picks as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['waiver_pick']) ?></td>
                <td>$<?= number_format($row['waiver_bid']) ?></td>
                <td><?= htmlspecialchars($row['waiver_drop']) ?></td>
                <td><?= htmlspecialchars($row['waiver_option']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>

<p style="text-align:center;">
    *If there's an issue with your picks, reach out to Ian
</p>

</body>
</html>