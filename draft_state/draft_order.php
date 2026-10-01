<?php
include __DIR__ . '/../misc_files/auth_check.php';
include __DIR__ . '/../db_connections/connection_pdo.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="120">
    <title>Draft Board</title>
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/draft_order.css' />
    <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
</head>

<body>

    <div class="grid-container-top">
        <div class="title">
            <h1>RAW Hockey Pool<br>Draft Board</h1>
        </div>
        <div class="draft_status">
            <?php include __DIR__ . '/../draft_state/round_npick.php'; ?>
        </div>
    </div>

    <?php
    include __DIR__ . '/../misc_files/nav_bar_links.php';

    $numb_rds = (int)$pdo->query("SELECT numb_rds FROM base_numbers")->fetchColumn();
    ?>

    <div class="outer-scroll">
        <table id="picks">
            <tr class="round">
                <th class="two_sided_border"></th>
                <?php for ($i = 1; $i <= $numb_rds; $i++): ?>
                    <th id="<?= $i % 2 !== 0 ? 'odd_columns' : 'even_columns' ?>">Round <?= $i ?></th>
                <?php endfor; ?>
            </tr>

            <?php
            $gm = "SELECT * FROM draft_order ORDER BY draft_pos;";
            $total_gms = $pdo->query("SELECT count(draft_pos) FROM draft_order")->fetchColumn();

            $check_display_name = $pdo->prepare("SELECT gm_name FROM gms WHERE :rd IN (gm_name);");
            $get_player_position = $pdo->prepare("SELECT position FROM salaries WHERE player = :player_name;");

            foreach ($pdo->query($gm) as $row) {
                $round_pick = $row['draft_pos'];

                echo "<tr id='tr_picks'>
                    <td id='pick_numb'>Pick " . $round_pick . "</td>";

                for ($x = 1; $x <= $numb_rds; $x++) {
                    echo "<td id='" . ($x % 2 !== 0 ? 'odd_columns' : 'even_columns') . "'>";

                    $check_display_name->execute(['rd' => $row["round_$x"]]);
                    $display_name = $check_display_name->fetchColumn();

                    if (empty($display_name)) {
                        $gm_n_pick = explode("(", $row["round_$x"] ?? '');
                        $gm_pick = rtrim($gm_n_pick[1] ?? '', ")") . "<br>";

                        $player_pick = str_replace("<br>", "", $gm_n_pick[0]);
                        $get_player_position->execute(['player_name' => $player_pick]);
                        $player_position = $get_player_position->fetchColumn();

                        echo '<span style="color:#000000; font-size:13px">' . $gm_pick . '</span>';
                        echo '<span style="color:#FF0000; font-size:13px">' . $player_pick . ' (' . $player_position . ')</span>';
                    } else {
                        echo '<span style="font-size:15px;">' . $row["round_$x"] . '</span>';
                    }

                    echo "</td>";
                }

                echo "</tr>";
            }
            ?>
        </table>

        <!-- Last DB Update — bottom left below table -->
        <div class="div_lastUpdate">
            <?php
            if ($dbname !== 'RAW_HockeyPool_prod') {
                $last_update = $pdo->query("SELECT last_db_update FROM base_numbers")->fetchColumn();
                if ($last_update !== NULL) {
                    echo "Last DB Update: " . date("F jS, Y", strtotime($last_update));
                }
            }
            ?>
        </div>
    </div>

</body>

</html>