<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel='stylesheet' type='text/css' href='../css/trades.css' />
    <title>Select Trade Criteria</title>
</head>

<body>

    <?php
    if (count($_POST['gmName']) !== 2) {
        $message = "You did not select 2 GMs as trading partners.";
        echo "<script>
                alert(" . json_encode($message) . ");
                window.location.href='trade_players_gm_select.php';
              </script>";
        exit;
    }

    include __DIR__ . '/../db_connections/connection_pdo.php';
    include __DIR__ . '/../misc_files/pre_draft_check.php';

    $max_trades = $pdo->query("SELECT max_trades FROM base_numbers;")->fetchColumn();

    $selectedGMs = array_map('trim', $_POST['gmName']);
    [$gm1, $gm2] = $selectedGMs;

    $_SESSION['gm1'] = $gm1;
    $_SESSION['gm2'] = $gm2;

    // Check if either GM exceeded trade limit
    $trades_made = $pdo->prepare("SELECT gm_name, trades_made
                                  FROM gms 
                                  WHERE gm_name IN (:gm1, :gm2);");
    $trades_made->execute(['gm1' => $gm1, 'gm2' => $gm2]);

    foreach ($trades_made as $row) {
        if ($row['trades_made'] >= $max_trades) {
            $gm = $row['gm_name'];
            $message = "The maximum number of trades per GM is $max_trades.\n$gm has reached the trade limit for this season.\n\nThe trade cannot continue.";
            echo "<script>
                    alert(" . json_encode($message) . ");
                    window.location.href='trades_completed.php';
                 </script>";
            exit;
        }
    }
    ?>

    <div class="header">
        <h1 style="color:#FFA500">Select<br>Trade Criteria</h1>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <div class="button-row-trade_criteria">
        <button type="submit" form="trade_form" name="make_trade" class="buttonSet">Make the Trade</button>
        <button type="reset" form="trade_form" class="buttonSet">Reset the Form</button>
    </div>

    <div class="outer-scroll">
    <form id="trade_form" name="display" action="trade_criteria_db_update.php" method="POST">
        <div class="grid-container-trade_criteria">
            <?php
            $query = $pdo->prepare("SELECT player, position, team, current_salary 
                                    FROM salaries 
                                    WHERE gm = :gm AND franchise IS NULL 
                                    ORDER BY position, player;");

            foreach ($selectedGMs as $poolee):
            ?>
                <table class="criteria_width">
                    <tr>
                        <td id="poolee_row" colspan="5"><?= htmlspecialchars($poolee) ?>'s team</td>
                    </tr>
                    <tr>
                        <th>Player</th>
                        <th>Position</th>
                        <th>Team</th>
                        <th>Salary</th>
                        <th>Trade</th>
                    </tr>

                    <?php
                    $query->execute(['gm' => $poolee]);
                    while ($row = $query->fetch()):
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['player']) ?></td>
                            <td><?= htmlspecialchars($row['position']) ?></td>
                            <td><?= htmlspecialchars($row['team']) ?></td>
                            <td>$<?= number_format($row['current_salary'] ?? 0) ?></td>
                            <td><input type="checkbox" name="player_chosen[]" value="<?= htmlspecialchars($row['player']) ?>"></td>
                        </tr>
                    <?php endwhile; ?>

                    <tr>
                        <td colspan="5">
                            <input type='text' id='textboxid' name='round[]' placeholder="Enter Round to Trade">
                            <input type='hidden' name='gm[]' value="<?= htmlspecialchars($poolee) ?>">
                        </td>
                    </tr>
                </table>
            <?php endforeach; ?>
        </div>
    </form>
    </div>

</body>

</html>