<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winning Waiver Bids</title>
    <link rel='stylesheet' type='text/css' href='../css/waivers.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>
<body>

<?php
include __DIR__ . '/../db_connections/connection_pdo.php';

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

include __DIR__ . '/../misc_files/pre_draft_check.php';
include __DIR__ . '/find_winners.php';
include __DIR__ . '/popups_for_waivers.php';

$waiver_date_stmt = $pdo->query("SELECT (GREATEST(
                                             CASE WHEN waiver1 <= CURRENT_DATE THEN waiver1 ELSE NULL END,
                                             CASE WHEN waiver2 <= CURRENT_DATE THEN waiver2 ELSE NULL END,
                                             CASE WHEN waiver3 <= CURRENT_DATE THEN waiver3 ELSE NULL END
                                        ) + INTERVAL '1 day')::date AS waiver_effective_date
                                 FROM base_numbers;");
$waiver_effective_date = $waiver_date_stmt->fetchColumn();
?>

<div class="header">
    <h1>
        <span style="color:#32cd32;"><?= htmlspecialchars((string)$waiver_title) ?></span><br>
        Waiver Winners
    </h1>
</div>

<?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

<?php
$get_waivers_updated_check = $pdo->query("SELECT waivers_updated FROM base_numbers");
$get_waivers_updated_check->execute();
$waivers_updated_check = $get_waivers_updated_check->fetchColumn();

if ($waivers_updated_check !== null) {
    $date = new DateTime($waivers_updated_check);
    $date->setTimezone(new DateTimeZone('America/Toronto'));
    $waivers_updated = $date->format('F j, Y \a\t H\hi');
} else {
    $waivers_updated = 'Pending Update';
}
?>

<div class="grid-container_waiver_results">
    <div class="table-scroll">
    <table class="waiver_wins">
        <tr>
            <th>Waiver Pickup</th>
            <th>Bid</th>
            <th>Player to Drop</th>
            <th>GM</th>
        </tr>

        <?php foreach ($waiver_winners as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['waiver_pick']) ?></td>
                <td>$<?= number_format(htmlspecialchars($row['waiver_bid'])) ?></td>
                <td><?= htmlspecialchars($row['waiver_drop']) ?></td>
                <td><?= htmlspecialchars($row['gm']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <div class="update_rosters">
        <?php if ($isPrivileged): ?>
            <!-- <button type="submit" id="buttonSet" name="save">Update<br>Rosters</button> -->
        <?php endif; ?>
    </div>

    <div>
        <strong>DB Updated with Waiver Winners on: </strong><?= htmlspecialchars($waivers_updated) ?>
    </div>
</div>

</body>
</html>