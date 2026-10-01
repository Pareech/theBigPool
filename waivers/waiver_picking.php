<?php
include_once __DIR__ . '/../misc_files/auth_check.php';
include_once __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

$gm = ucfirst(strtolower($_SESSION['gm_name']));

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

$today = $today->format('Y-m-d');
$waiver1 = new DateTime($waiver_data['waiver1']);
$waiver2 = new DateTime($waiver_data['waiver2']);
$waiver3 = new DateTime($waiver_data['waiver3']);

$max_waiver_slots = (int)$waiver_data['max_waiver_slots'];
$max_waivers      = (int)$waiver_data['max_waivers'];

$waiver1 = $waiver1->format('Y-m-d');
$waiver2 = $waiver2->format('Y-m-d');
$waiver3 = $waiver3->format('Y-m-d');

$waiver_period = null;
$waiver_title  = null;

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

// Existing waiver count for GM in this period
$stmt = $pdo->prepare("SELECT COUNT(gm) 
                       FROM waiver_draft 
                       WHERE gm = :gm AND waiver_period = :waiver_period");
$stmt->execute(['gm' => $gm, 'waiver_period' => $waiver_period]);
$already_picked = $stmt->fetchColumn();

// Determine which waiver numbers are already taken
$stmt = $pdo->prepare("SELECT waiver_option 
                       FROM waiver_draft
                       WHERE gm = :gm 
                         AND waiver_period = :waiver_period
                       ORDER BY waiver_option");
$stmt->execute(['gm' => $gm, 'waiver_period' => $waiver_period]);
$existing_numbers = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Build available waiver slots for this GM
if (empty($existing_numbers)) {
    $available_numbers = range(1, $max_waiver_slots);
} else {
    $available_numbers = array_diff(range(1, $max_waiver_slots), $existing_numbers);
    sort($available_numbers);
}

// Salary + waiver budget calculations
$stmt = $pdo->prepare("SELECT current_salary FROM salaries WHERE salary_retained = :gm");
$stmt->execute(['gm' => $gm]);
$salary_retained_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$salary = array_sum(array_column($salary_retained_rows, 'current_salary'));

$stmt = $pdo->prepare("SELECT COUNT(salary_retained) FROM salaries WHERE salary_retained = :gm");
$stmt->execute(['gm' => $gm]);
$count_waivers = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT cap_number + waiver_number - :salary
                          - (SELECT SUM(current_salary) FROM salaries WHERE gm = :gm AND franchise IS NULL AND waiver_bid IS NULL)
                          - (SELECT COALESCE(SUM(waiver_bid), 0) FROM salaries WHERE gm = :gm)
                       AS waiver_cash
                       FROM base_numbers");
$stmt->execute(['salary' => $salary, 'gm' => $gm]);
$waiver_cash = $stmt->fetchColumn();

// Redirect if not eligible
if ($already_picked >= $max_waiver_slots) {
    echo "<script>
            alert('You have already made your maximum of $max_waiver_slots waiver draft selections for this period.');
            window.location.href='waiver_gm_picks.php';
          </script>";
    exit;
}
if ($waiver_cash <= 0) {
    echo "<script>
            alert('You have spent your entire waiver budget.\\nYou cannot afford any player.');
            window.location.href='../gm_listings/gm_info.php?gm=$gm';
          </script>";
    exit;
}
if ($count_waivers >= $max_waivers) {
    echo "<script>
            alert('You have selected the maximum of $max_waivers waiver pickups for this season. You will be returned to your status page.');
            window.location.href='../gm_listings/gm_info.php?gm=$gm';
          </script>";
    exit;
}

// Get player lists
$stmt = $pdo->prepare("SELECT player, team, position 
                       FROM salaries 
                       WHERE gm = :gm AND franchise_date IS NULL 
                       ORDER BY position, player");
$stmt->execute(['gm' => $gm]);
$gm_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT s.player, s.team, s.position
                       FROM salaries s
                       WHERE s.gm IS NULL
                         AND NOT EXISTS (
                             SELECT 1
                             FROM waiver_draft w
                             WHERE w.gm = :gm
                               AND w.waiver_pick = s.player
                         ) 
                       ORDER BY s.player");
$stmt->execute([':gm' => $gm]);
$player_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GM's Waiver Picks, Drops & Bids</title>
    <link rel='stylesheet' type='text/css' href='../css/waivers_picking.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>
<body>

    <div class="header">
        <h1><?= htmlspecialchars($gm) ?>'s Waiver Selections for <?php echo $waiver_title ?></h1>
        <h2>Max Bid Allowed: <a style='color:#FF0000'>$<?= number_format($waiver_cash) ?></a></h2>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <form method="post" action="waiver_selections_validate.php" onsubmit="return validateWaivers()">
        <input type="hidden" name="gm" value="<?= htmlspecialchars($gm) ?>">
        <input type="hidden" name="waiver_period" value="<?= htmlspecialchars((string)$waiver_period) ?>">

        <!-- Buttons at top on mobile, beside rows on desktop -->
        <div class="waiver-buttons-top">
            <button type="submit" name="save" class="waiver-btn">Submit<br>Waiver Bid(s)</button>
            <button type="reset" class="waiver-btn">Reset the form</button>
        </div>

        <!-- Header Row -->
        <div class="waiver-header">
            <h2>Waiver Choice</h2>
            <h2>Waiver Bid</h2>
            <h2>Player to Drop</h2>
        </div>

        <div class="grid-container_waivers">
            <div class="waivers-and-buttons">
                <div class="waiver-rows">
                    <?php foreach ($available_numbers as $index => $num):
                        $row_num = $index + 1; ?>
                        <div class="waiver-row">
                            <div class="waiver-choice">
                                <input type="text" list="player_search" name="waiver<?= $row_num ?>" id="waiver<?= $row_num ?>" />
                                <input type="hidden" name="waiver_number<?= $row_num ?>" value="<?= $num ?>">
                            </div>
                            <div class="waiver-bid">
                                <input type="text" placeholder="Waiver Bid" name="bid<?= $row_num ?>" id="bid<?= $row_num ?>" />
                            </div>
                            <div class="player-drop">
                                <input type="text" list="gm_players" name="player_drop<?= $row_num ?>" id="player_drop<?= $row_num ?>" />
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop buttons beside rows -->
                <div class="waiver-buttons-desktop">
                    <button type="submit" name="save" class="waiver-btn">Submit<br>Waiver Bid(s)</button>
                    <button type="reset" class="waiver-btn">Reset the form</button>
                </div>
            </div>

            <datalist id="player_search">
                <option value=""></option>
                <?php foreach ($player_list as $row): ?>
                    <option value="<?= htmlspecialchars($row['player']) ?>">
                        <?= htmlspecialchars($row['player']) ?> (<?= htmlspecialchars($row['position']) ?>)
                    </option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <datalist id="gm_players">
            <option value=""></option>
            <?php foreach ($gm_list as $row): ?>
                <option value="<?= htmlspecialchars($row['player']) ?>">
                    <?= htmlspecialchars($row['player']) ?> (<?= htmlspecialchars($row['position']) ?>)
                </option>
            <?php endforeach; ?>
        </datalist>
    </form>

    <script>
        function validateWaivers() {
            const totalRows = <?= count($available_numbers) ?>;
            for (let i = 1; i <= totalRows; i++) {
                const waiver = document.getElementById("waiver" + i).value.trim();
                const bid = document.getElementById("bid" + i).value.trim();
                const drop = document.getElementById("player_drop" + i).value.trim();
                if (waiver !== "" && (bid === "" || drop === "")) {
                    alert("Bid " + i + ": You must enter both a Waiver Bid and a Player to Drop when selecting a Waiver Pick.");
                    return false;
                }
            }
            return true;
        }
    </script>

</body>
</html>