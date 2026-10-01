<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<?php
if (!empty($_GET['player'])) {
    $player = $_GET['player'];
}

if (!empty($_POST['search'])) {
    $player = $_POST['search'];
} elseif (empty($_GET['player']) and empty($_POST['search'])) {
    echo "<script>
        alert('You need to enter at least part of a player\\'s name.');
        window.history.back();
     </script>";
    exit();
}
unset($_GET['player']);
unset($_POST['search']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Search Result</title>
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/player_available.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="grid-container-top">
        <div class="title">
            <h1>Player(s) Matching<br><span
                    style='color:#FFFFFF'><?php echo htmlspecialchars($player) ?></span>
                in their name</h1>
        </div>
        <div class="draft_status">
            <?php
            include __DIR__ . '/../db_connections/connection_pdo.php';
            include __DIR__ . '/../draft_state/round_npick.php';
            ?>
        </div>
    </div>

    <?php
    include __DIR__ . '/../misc_files/nav_bar_links.php';
    include __DIR__ . '/../misc_files/header_row_sticky.js';

    $find = $pdo->prepare("SELECT player, team, current_salary, position, drafted, gm 
                       FROM salaries 
                       WHERE player ILIKE :player 
                       ORDER BY player ASC;");
    $find->execute(['player' => '%' . $player . '%']);
    ?>

    <form method='post' action='../draft_day/draft_player_db_update.php'>
        <input type="hidden" name="picking_now"
            value="<?= htmlspecialchars($picking_now) ?>">
        <div class="grid-container-bottom">

            <div class="button-row-draft">
                <button type="submit" class="buttonSet" name="save">Draft Player</button>
                <button type="reset" class="buttonSet">Reset the Form</button>
            </div>

            <div class="forTable">
                <table class="bottom_search" id="player_filter">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">Player</th>
                            <th onclick="sortTable(1)">Team</th>
                            <th onclick="sortTable(2)">Position</th>
                            <th onclick="sortTable(3)">Salary</th>
                            <th onclick="sortTable(4)">Status</th>
                            <th style="text-decoration:none;">Draft Player</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $find->fetch()):
                            $salary = "$" . number_format($row['current_salary'] ?? 0);
                            $isDrafted = !empty($row['gm']);
                            $status = $isDrafted ? "Drafted by {$row['gm']}" : "";
                            $radioAttr = $isDrafted ? "disabled style='display:none'" : "enabled";

                            if ($salary == "$0") {
                                $salary = "-";
                                $status = "No Contract";
                                $radioAttr = "disabled style='display:none'";
                            }
                        ?>
                            <tr>
                                <td class="bottom_search">
                                    <?= htmlspecialchars($row['player']) ?>
                                </td>
                                <td class="bottom_search">
                                    <?= htmlspecialchars($row['team'] ?? '') ?>
                                </td>
                                <td class="bottom_search">
                                    <?= htmlspecialchars($row['position'] ?? '') ?>
                                </td>
                                <td class="bottom_search"><?= $salary ?>
                                </td>
                                <td class="bottom_search"><?= $status ?>
                                </td>
                                <td class="bottom_search">
                                    <input type="radio" name="radioChoice"
                                        <?= $radioAttr ?>
                                        value="<?= htmlspecialchars($row['player']) ?>">
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

</body>

</html>