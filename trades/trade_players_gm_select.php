<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select GM Trading Partners</title>
    <link rel='stylesheet' type='text/css' href='../css/trades.css' />
    <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
</head>
<body>

<div class="header">
    <h1>Select Trading<br>Partners</h1>
    <?php
    include __DIR__ . '/../db_connections/connection_pdo.php';
    include __DIR__ . '/../misc_files/pre_draft_check.php';
    ?>
</div>

<?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

<?php
$traders = ("SELECT gm_name FROM gms ORDER BY gm_name;");
?>

<div class="outer-scroll">
<form method="post" action="trade_criteria.php">
    <div class="grid-container-gm_select">
    <div class="button-row-gm_select">
        <div class="submitButton-gm_select">
            <button type="submit" class="buttonSet" name="save">Create Trade</button>
        </div>
        <div class="resetButton-gm_select">
            <button type="reset" class="buttonSet">Reset the form</button>
        </div>
    </div>

        <div class="gmSelect">
            <table class="table-gm_select">
                <tr>
                    <th>GM</th>
                    <th>Trading Partners</th>
                </tr>

                <?php
                foreach ($pdo->query($traders) as $row) {
                    echo "<tr class='gm_select_height'>";
                    echo    "<td>" . $row["gm_name"] . "</td>";
                    echo    "<td><input type='checkbox' id='checkItem' name='gmName[]' value='" . $row["gm_name"] . "'></td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>
    </div>
</form>
</div>

</body>
</html>