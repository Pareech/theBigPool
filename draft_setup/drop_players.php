<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drop Player(s)</title>
    <link rel='stylesheet' type='text/css' href='../css/drop.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <?php
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

$poolee = $_POST['poolee'];
?>

    <div class="grid-container-top">
        <div class="item_top">
            <h1>Drop Player(s)
                From<br><?php echo htmlspecialchars($poolee) ?>'s
                Roster</h1>
        </div>
    </div>

    <?php
include __DIR__ . '/../misc_files/nav_bar_links.php';

$result = $pdo->prepare("SELECT player, team, position, current_salary, franchise, salary_retained, waiver_bid
                         FROM salaries
                         WHERE gm = :gm
                         ORDER BY position, player;");
$result->execute(['gm' => $poolee]);
?>

    <form name="display" action="" method="POST">
        <input type="hidden" name="poolee"
            value="<?php echo htmlspecialchars($poolee) ?>">

        <div class="grid-container">

            <div class="button-row">
                <div class="submitButton">
                    <button type="submit" class="button" name="save" id="buttonSet">Drop Player(s) From Roster</button>
                </div>
                <div class="resetButton">
                    <button type="reset" class="reset" id="buttonSet">Reset the form</button>
                </div>
            </div>

            <div class="playerList">
                <table>
                    <tr>
                        <th>Player</th>
                        <th>Team</th>
                        <th>Position</th>
                        <th>Salary</th>
                        <th>Drop(s)</th>
                    </tr>

                    <?php
                $money = new NumberFormatter('en', NumberFormatter::CURRENCY);

foreach ($result as $row) {
    $no_check = "enabled";
    $style = "style='block'";
    if (is_null($row['franchise'])) {
        $salary = $money->formatCurrency($row['current_salary'] ?? 0, 'USD');
    } else {
        $salary = '<span style="color:#008000;"><strong>Franchise</strong></span>';
        $no_check = "none";
        $style = "style='display:none'";
    }
    echo "<tr>";
    echo    "<td>" . htmlspecialchars($row["player"]) . "</td>";
    echo    "<td>" . htmlspecialchars($row["team"]) . "</td>";
    echo    "<td>" . htmlspecialchars($row["position"]) . "</td>";
    echo    "<td>" . $salary . "</td>";
    echo    "<td><input type='checkbox' id='checkItem' name='checkmark[]' . $style . $no_check value='" . $row['player'] . "'></td>";
    echo "</tr>";
}
?>
                </table>
            </div>

        </div>
    </form>

    <?php
if (isset($_POST['save'])) {

    if (!empty($_POST['checkmark'])) {
        $poolee = $_POST['poolee'];
        $checkbox = $_POST['checkmark'];
        $checkboxSize = count($checkbox);

        for ($i = 0; $i < $checkboxSize; ++$i) {
            $del_player = $checkbox[$i];

            $position_query = $pdo->prepare("SELECT position FROM salaries WHERE gm= :gm AND player= :player;");
            $position_query->execute(['gm' => $poolee, 'player' => $del_player]);

            foreach ($position_query as $row) {
                $position = $row['position'];
            }

            $update = "UPDATE salaries
                       SET drafted = NULL,
                           gm = NULL,
                           salary_retained = NULL,
                           waiver_bid = NULL,
                           latest_pick = NULL
                       WHERE player = :player;";
            $pdo->prepare($update)->execute(['player' => $del_player]);
        }

        include __DIR__ . '/../misc_files/last_db_update.php';

        echo
        "<script>
            alert('$poolee\\'s roster has been updated.');
            window.location.href='../gm_listings/gm_info.php?gm=$poolee';
         </script>";
    } else {
        echo
        "<script>
            alert('You did not choose any player to be dropped.\\nNo changes were made to your roster.');
            window.location.href='../gm_listings/gm_info.php?gm=$_POST[poolee]';
         </script>";
    }
}
?>

</body>

</html>