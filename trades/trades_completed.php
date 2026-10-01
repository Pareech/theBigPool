<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Trades</title>
    <link rel='stylesheet' type='text/css' href='../css/trades.css' />
    <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
</head>
<body>

<div class="header">
    <h1>Completed Trades</h1>
    <?php
    include __DIR__ . '/../db_connections/connection_pdo.php';
    include __DIR__ . '/../misc_files/pre_draft_check.php';
    ?>
</div>

<?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

<?php
$trades_made = $pdo->prepare("SELECT trade_numb, trade_done FROM trades_completed ORDER BY trade_numb;");
$trades_made->execute();
?>

<div class="div-completed_trades">
    <table class="completed_trades">
        <tr>
            <th class='two_sided_border'> </th>
            <th id="text_alignment_completed_trades">Trade Results</th>
        </tr>

        <?php
        foreach ($trades_made as $row) {
            echo
            "<tr>
                <td class='td_trade_number'>" . $row["trade_numb"] . "</td>
                <td>" . $row['trade_done'] . "</td>
            </tr>";
        }
        ?>
    </table>
</div>

</body>
</html>