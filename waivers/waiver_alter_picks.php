<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modify Waiver Choices</title>
    <link rel='stylesheet' type='text/css' href='../css/waiver_admin.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>
<body>

<?php
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

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

$waiver_period = null;
$waiver_title  = null;

foreach ([$today, $waiver1, $waiver2, $waiver3] as $d) {
    $d->setTime(0, 0, 0);
}

if ($today <= $waiver1) {
    $waiver_period = 'waiver_1';
    $waiver_title  = 'Round 1';
    $drafted_info = 'Round 1 Waiver Pickup';
} elseif ($today <= $waiver2) {
    $waiver_period = 'waiver_2';
    $waiver_title  = 'Round 2';
    $drafted_info = 'Round 2 Waiver Pickup';
} elseif ($today <= $waiver3) {
    $waiver_period = 'waiver_3';
    $waiver_title  = 'Round 3';
    $drafted_info = 'Round 3 Waiver Pickup';
} else {
    echo "<script>
            alert('Waivers are closed for this season.');
            window.location.href='../draft_setup/draft_info.php';
          </script>";
    exit;
}

$gm = ucfirst(strtolower($_SESSION['gm_name']));

function resequenceWaiverOptions(PDO $pdo, string $gm, string $waiver_period): void
{
    $stmt = $pdo->prepare("SELECT waivers_pk 
                           FROM waiver_draft
                           WHERE gm = :gm 
                             AND waiver_period = :waiver_period
                           ORDER BY waiver_option ASC");
    $stmt->execute(['gm' => $gm, 'waiver_period' => $waiver_period]);
    $picks = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $option = 1;
    $update = $pdo->prepare("UPDATE waiver_draft 
                             SET waiver_option = :option
                             WHERE waivers_pk = :pk
                                AND waiver_period = :waiver_period");
    foreach ($picks as $pk) {
        $update->execute(['option' => $option, 'pk' => $pk, 'waiver_period' => $waiver_period]);
        $option++;
    }
}

if (isset($_POST['drop_picks']) && !empty($_POST['checkmark'])) {
    $checkbox = array_map('trim', $_POST['checkmark']);
    $placeholders = implode(',', array_fill(0, count($checkbox), '?'));
    $stmt = $pdo->prepare("DELETE FROM waiver_draft WHERE waivers_pk IN ($placeholders)");
    $stmt->execute($checkbox);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['round'])) {
    $rounds = $_POST['round'];
    $ids    = $_POST['waiver_id'];

    foreach ($rounds as $i => $newRound) {
        $id = $ids[$i];
        if ($newRound === '' || $newRound === null) continue;

        $stmt = $pdo->prepare("SELECT waiver_option 
                               FROM waiver_draft
                               WHERE waivers_pk = :id 
                                 AND waiver_period = :waiver_period");
        $stmt->execute(['id' => $id, 'waiver_period' => $waiver_period]);
        $current = $stmt->fetchColumn();

        if ($current != $newRound) {
            $update = $pdo->prepare("UPDATE waiver_draft 
                                     SET waiver_option = :newRound,
                                         waiver_bid_time = NOW()
                                     WHERE waivers_pk = :id
                                        AND waiver_period = :waiver_period");
            $update->execute(['newRound' => $newRound, 'id' => $id, 'waiver_period' => $waiver_period]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $res = $pdo->prepare("SELECT waivers_pk, waiver_option
                              FROM waiver_draft
                              WHERE gm = :gm
                                AND waiver_period = :waiver_period
                              ORDER BY waiver_option ASC, waivers_pk ASC");
        $res->execute(['gm' => $gm, 'waiver_period' => $waiver_period]);
        $rows = $res->fetchAll(PDO::FETCH_ASSOC);

        $upd = $pdo->prepare("UPDATE waiver_draft
                              SET waiver_option = :waiver_option,
                                  waiver_bid_time = NOW()
                              WHERE waivers_pk = :pk");

        $i = 1;
        foreach ($rows as $row) {
            $pk = $row['waivers_pk'];
            $current_option = (int)$row['waiver_option'];
            if ($current_option !== $i) {
                $upd->execute(['waiver_option' => $i, 'pk' => $pk]);
            }
            $i++;
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*)
                           FROM waiver_draft
                           WHERE gm = :gm
                             AND waiver_period = :waiver_period");
    $stmt->execute(['gm' => $gm, 'waiver_period' => $waiver_period]);
    $choices_count = (int)$stmt->fetchColumn();

    if ($choices_count > 0) {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    } else {
        header("Location: waiver_picking.php");
        exit();
    }
}

$waiver_check = $pdo->prepare("SELECT waivers_pk, waiver_pick, waiver_bid, waiver_drop, waiver_option
                               FROM waiver_draft
                               WHERE gm = :gm
                                 AND waiver_period = :waiver_period
                               ORDER BY waiver_option");
$waiver_check->execute(['gm' => $gm, 'waiver_period' => $waiver_period]);
$money = new NumberFormatter('en', NumberFormatter::CURRENCY);
?>

<div class="header">
    <h1>
        Modification to <?= htmlspecialchars($gm) ?>'s<br>
        <span style="color:#32cd32;"><?= htmlspecialchars((string)$waiver_title) ?></span>
        Waiver Selections
    </h1>
</div>

<?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

<div class="outer-scroll">
<form method="post" action="">
    <div class="grid-container_waiver_changes">
        <div class="update_rosters">
            <div class="alter_waivers_button">
                <button type="submit" id="buttonSet" name="drop_picks">Modify<br>Waiver Choices</button>
            </div>
            <div class="alter_waivers_reset">
                <button type="reset" id="buttonSet">Reset the form</button>
            </div>
        </div>

        <table class="alter_waiver_picks">
            <tr>
                <th>Waiver Pick</th>
                <th>Bid Amount</th>
                <th>Player to Drop</th>
                <th>Waiver<br>Choice #</th>
                <th>Alter<br>Order</th>
                <th>Drop<br>Choice</th>
            </tr>
            <?php foreach ($waiver_check as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row["waiver_pick"]) ?></td>
                    <td><?= $money->formatCurrency($row['waiver_bid'] ?? 0, 'USD') ?></td>
                    <td><?= htmlspecialchars($row["waiver_drop"]) ?></td>
                    <td><?= htmlspecialchars($row["waiver_option"]) ?></td>
                    <td>
                        <input type="hidden" name="waiver_id[]" value="<?= $row['waivers_pk'] ?>">
                        <input type='text' id='textboxid' name='round[]' value="">
                    </td>
                    <td><input type='checkbox' id='checkItem' name='checkmark[]' value='<?= htmlspecialchars($row['waivers_pk']) ?>'></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</form>
</div>

</body>
</html>