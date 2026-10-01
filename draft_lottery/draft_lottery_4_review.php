<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Lottery Draft Order Review</title>
    <link rel='stylesheet' type='text/css' href='../css/draft_lottery.css' />
    <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
</head>

<body>

    <div class="header">
        <h1>Draft Lottery Results<br>Round 1 Drafting Order</h1>
        <?php
    include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';
?>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <?php
// Verify if Draft Done
$check_draft_time = $pdo->query("SELECT lotto_date FROM base_numbers;")->fetchColumn();

if ($check_draft_time == null) {
    $message = "The Lottery Draft has not been done.\nYou will now be redirected to run the draft lottery.";
    echo "<script>
            alert(" . json_encode($message) . ");
            window.location.href='draft_lottery_1_setDraftOrder.php';
          </script>";
    exit();
}

$original_spot = 0;
$drafting_order = $pdo->query("SELECT gm_name, draft_weight, original_order, lottery_order FROM draft_lotto ORDER BY lottery_order;");

// Get Lottery Draw and Time
$lotto_info = $pdo->query("SELECT lotto_date, lotto_time FROM base_numbers;");

foreach ($lotto_info as $row) {
    if (!empty($row['lotto_date'])) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $row['lotto_date']);
        $lotto_date = $dateObj ? $dateObj->format('F jS, Y') : 'Invalid Date';
    } else {
        $lotto_date = '';
    }
    $lotto_time = $row['lotto_time'] ?? '';
}
?>

    <div class="div_table_lottery">
        <div class="table-scroll">
            <table>
                <tr>
                    <th>GM</th>
                    <th>Pre Lotto<br>Draft Spot</th>
                    <th>Final Draft<br>Position</th>
                    <th>Draft<br>Movement</th>
                </tr>

                <?php
        foreach ($drafting_order as $row) {
            $original_spot++;

            echo "<tr>";

            if ($row['original_order'] != $original_spot) {
                $change_spot = (int)$row['original_order'] - $original_spot;

                if (ABS($change_spot) == 1) {
                    $change_spot_words = ABS($change_spot) . " Spot";
                } else {
                    $change_spot_words = ABS($change_spot) . " Spots";
                }

                if ($change_spot > 0) {
                    echo "<td id='td_change_up'>" . $row['gm_name'] . "</td>";
                    echo "<td id='td_change_up'>" . $row['original_order'] . "</td>";
                    echo "<td id='td_change_up'>" . $original_spot;
                    echo "<td id='td_change_up'> Up " . $change_spot_words . "</td>";
                } else {
                    echo "<td id='td_change_down'>" . $row['gm_name'] . "</td>";
                    echo "<td id='td_change_down'>" . $row['original_order'] . "</td>";
                    echo "<td id='td_change_down'>" . $original_spot . "</td>";
                    echo "<td id='td_change_down'> Down " . $change_spot_words . "</td>";
                }
            } else {
                echo "<td>" . $row['gm_name'] . "</td>";
                echo "<td>" . $row['original_order'] . " </td>";
                echo "<td>" . $original_spot . "</td>";
                echo "<td>  </td>";
            }
            echo "</tr>";
        }
?>
            </table>
        </div>

        <div class='lottory_date'>
            <?php
echo "<p>Draw Date: <span style='color:#785127'>" . $lotto_date . "</span></p>";
echo "Draw Time: <span style='color:#785127'>" . $lotto_time . "</span>";
?>
        </div>
    </div>

</body>

</html>