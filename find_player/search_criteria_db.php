<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<?php
if (empty($_POST['position'])) {
    echo "<script>
            alert('You need to choose a position.');
            window.history.back();
        </script>";
    exit();
}

include __DIR__ . '/../db_connections/connection_pdo.php';

$position = $_SESSION['position'] = $_POST['position'];

if ($position == 'D') {
    $title = 'Defencemen';
} elseif ($position == 'F') {
    $title = 'Forwards';
} else {
    $title = 'Goalies';
}

if (empty($_POST['salary_min'])) {
    $get_salary_min = $pdo->prepare("SELECT MIN(current_salary) FROM salaries WHERE position = :position AND gm IS NULL AND current_salary > 0;");
    $get_salary_min->execute(['position' => $position]);
    $salary_min = $get_salary_min->fetchColumn();
} else {
    $salary_min = $_POST['salary_min'];
}

if (empty($_POST['salary_max'])) {
    $get_salary_max = $pdo->prepare("SELECT MAX(current_salary) FROM salaries WHERE position = :position AND gm IS NULL;");
    $get_salary_max->execute(['position' => $position]);
    $salary_max = $get_salary_max->fetchColumn();
} else {
    $salary_max = $_POST['salary_max'];
}

$money_title = new NumberFormatter('en_US', NumberFormatter::DECIMAL);
$salary_title = "<font color='#D76E00'>" . $title . " Between</font>";
$salary_range = '$' . $money_title->formatCurrency((int)$salary_min, "USD") . "<font color='#D76E00'> and </font> $" . $money_title->formatCurrency((int)$salary_max, "USD");
$fulltitle = $salary_title . "<br>" . "<font color='#dab786'>" . $salary_range . "</font>";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undrafted Players</title>
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/player_available.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="grid-container-top">
        <div class="title">
            <h1><?php echo $fulltitle ?></h1>
        </div>
        <div class="draft_status">
            <?php
            include __DIR__ . '/../draft_state/round_npick.php';
            ?>
        </div>
    </div>

    <?php
    include __DIR__ . '/../misc_files/nav_bar_links.php';
    include __DIR__ . '/../misc_files/header_row_sticky.js';

    $get_range = $pdo->prepare("SELECT player, team, current_salary 
                            FROM salaries 
                            WHERE position = :position AND gm IS NULL AND current_salary >= :sal_min AND current_salary <= :sal_max
                            ORDER BY current_salary ASC, player ASC;");
    $get_range->execute(['position' => $position, 'sal_min' => $salary_min, 'sal_max' => $salary_max]);

    $money = new NumberFormatter('en', NumberFormatter::CURRENCY);
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
                <table class="bottom_draft" id="player_filter">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">Player</th>
                            <th onclick="sortTable(1)">Team</th>
                            <th onclick="sortTable(2)">Salary</th>
                            <th style='text-decoration:none;'>Draft Player</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($get_range as $row): ?>
                            <tr>
                                <td class="bottom_searchCriteria">
                                    <?= htmlspecialchars($row['player']) ?>
                                </td>
                                <td class="bottom_searchCriteria">
                                    <?= htmlspecialchars($row['team']) ?>
                                </td>
                                <td class="bottom_searchCriteria">
                                    <?= $money->formatCurrency((int)$row['current_salary'], 'USD') ?>
                                </td>
                                <td class="bottom_searchCriteria"><input type="radio" name="radioChoice"
                                        value="<?= htmlspecialchars($row['player']) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

</body>

</html>