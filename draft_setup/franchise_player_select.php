<?php
include __DIR__ . '/../misc_files/auth_check.php';
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

// Get franchise due date
$franchise_due = $pdo->query("SELECT franchise_due FROM base_numbers")->fetchColumn();

// Normalize both to date only
$today = new DateTime('today');
$franchise_due_dt = (new DateTime($franchise_due))->setTime(0, 0);

// Format only for popup
$franchise_due_fmt = $franchise_due_dt->format("F jS, Y");

if ($today > $franchise_due_dt) {
    echo "<script>
        alert('The period to select a Franchise player closed.\\n'
            + 'The deadline for submission was $franchise_due_fmt.\\n\\n'
            + 'You will need to reach out to the Commish to find out\\n'
            + 'how to proceed.');
        window.location.href='../draft_setup/draft_info.php';
    </script>";
    exit;
}

// Helper function for JavaScript alert + redirect
function jsAlertRedirect($message, $url)
{
    echo "<script>
            alert(`$message`);
            window.location.href='$url';
          </script>";
    exit();
}

// Get logged-in GM name (first letter capitalized)
$poolee = ucfirst(strtolower($_SESSION['gm_name']));

// Initialize variables
$player = null;

// Format currency
$money = new NumberFormatter('en', NumberFormatter::CURRENCY);

// Fetch existing franchise player (if any)
$franchise_date_query = $pdo->prepare("SELECT player, franchise_date 
                                       FROM salaries 
                                       WHERE gm = :gm 
                                         AND drafted = 'Franchise'");
$franchise_date_query->execute(['gm' => $poolee]);
$franchise_info = $franchise_date_query->fetch(PDO::FETCH_ASSOC);

$franchise_date = $franchise_info['franchise_date'] ?? 0;
$player = $franchise_info['player'] ?? null;

// Verify if GM is eligible to select a new franchise player
$current_date = strtotime("now");
if (strtotime($franchise_date) > $current_date) {
    $franchise_date_formatted = date("F jS, Y", strtotime($franchise_date));
    jsAlertRedirect(
        "You are not eligible to select a new franchise player\\nuntil after $franchise_date_formatted.",
        "../gm_listings/gm_info.php?gm=$poolee"
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Designate a Franchise Player</title>
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/drop.css' />
    <link rel='icon' type='image/x-icon' href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="grid-container-top">
        <div class="item_top">
            <h1>Franchise Tag
                For<br><?php echo htmlspecialchars($poolee); ?>'s
                Roster</h1>
        </div>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <?php
    // Remove existing franchise designation if it exists
    $get_franchise = $pdo->prepare("SELECT COUNT(player) AS player 
                                FROM salaries 
                                WHERE franchise IS NOT NULL 
                                  AND gm = :gm");
$get_franchise->execute(['gm' => $poolee]);
$has_franchise = $get_franchise->fetchColumn();

if ($has_franchise != 0 && $player) {
    $remove_franchise = $pdo->prepare("UPDATE salaries 
                                       SET franchise = NULL, 
                                           franchise_date = NULL, 
                                           drafted = NULL
                                       WHERE gm = :gm AND player = :player");
    $remove_franchise->execute(['gm' => $poolee, 'player' => $player]);
}

// Fetch all players for the GM
$result = $pdo->prepare("SELECT player, team, position, current_salary 
                             FROM salaries 
                             WHERE gm = :gm 
                                AND current_salary > 0
                             ORDER BY position, player, current_salary, team");
$result->execute(['gm' => $poolee]);
?>

    <div class="outer-scroll">
        <form name="display" action="" method="POST">
            <input type="hidden" name="poolee"
                value="<?php echo $poolee ?>">

            <div class="grid-container">
                <div class="button-row">
                    <div class="submitButton">
                        <button type="submit" id="buttonSet" name="save">Designate Franchise</button>
                    </div>

                    <div class="resetButton">
                        <button type="reset" id="buttonSet">Reset the form</button>
                    </div>
                </div>

                <div class="playerList">
                    <table>
                        <tr>
                            <th>Player</th>
                            <th>Team</th>
                            <th>Position</th>
                            <th>Salary</th>
                            <th>Franchise</th>
                        </tr>
                        <?php foreach ($result as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row["player"]) ?>
                            </td>
                            <td><?= htmlspecialchars($row["team"]) ?>
                            </td>
                            <td><?= htmlspecialchars($row["position"]) ?>
                            </td>
                            <td><?= $money->formatCurrency($row['current_salary'] ?? 0, 'USD') ?>
                            </td>
                            <td><input type="radio" id="radioItem" name="radioChoice"
                                    value="<?= htmlspecialchars($row['player']) ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <?php
// Update DB when form submitted
if (isset($_POST['save'])) {
    $franchise_player = $_POST['radioChoice'] ?? null;

    if (!$franchise_player) {
        jsAlertRedirect(
            'You did not designate a franchise player. No changes were made to your roster.',
            "../gm_listings/gm_info.php?gm=$poolee"
        );
    }

    $start_date = date("d-M-Y", strtotime("now"));

    // Franchise end date = 2 years from now, June 30
    $get_end_date = $pdo->prepare("SELECT make_date(EXTRACT(YEAR FROM :franchise_end::date)::int + 2, 6, 30);");
    $get_end_date->execute(['franchise_end' => $start_date]);
    $franchise_endDate = date("d-M-Y", strtotime($get_end_date->fetchColumn()));

    $update_salaries = $pdo->prepare("UPDATE salaries
                                      SET franchise = 'x',
                                          franchise_date = :franchise_end,
                                          drafted = 'Franchise',
                                          latest_pick = NULL
                                      WHERE player = :franchise_player");
    $update_salaries->execute([
        'franchise_end' => $franchise_endDate,
        'franchise_player' => $franchise_player
    ]);

    // Update last DB update timestamp
    include __DIR__ . '/../misc_files/last_db_update.php';

    jsAlertRedirect(
        "$poolee's roster has been updated.\\n$franchise_player has been tagged as Franchise.\\nMoney values have been adjusted accordingly.",
        "../gm_listings/gm_info.php?gm=$poolee"
    );
}
?>

</body>

</html>