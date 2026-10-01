<!DOCTYPE html>

<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel='stylesheet' type='text/css' href='../css/draft_lottery.css' />

<?php
session_start();
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo "<script>
            alert('Authorization Failure');
            window.location.href = 'https://hmpg.net';
         </script>";
    exit;
}
?>

<title>Post Draft Lottery</title>
<link rel="icon" type="image/x-icon" href="misc_files/rhcp_logo.ico" />

<div class='header'>
    <h1>Draft Order<br>Post Lottery Draw</h1>
    <?php
    include __DIR__ . '/../db_connections/connection_pdo.php';
    include __DIR__ . '/../misc_files/pre_draft_check.php';
    ?>
</div>

<?php
include __DIR__ . '/../misc_files/nav_bar_links.php';

// Get Lotto Draw Date and Time
$lotto_date = date("F j, Y");
$lotto_time = date("H:i:s");

// For clearing previous season's draft order prior to lottery draw
$clear_draft_order = $pdo->query("UPDATE draft_lotto 
                                  SET lottery_order = NULL 
                                  WHERE lottery_order BETWEEN 1 AND 8;");
$clear_draft_order->execute();

// Get chosen GM's draft position prior to the lottery
$winning_gm = $pdo->query("SELECT gm_name
                           FROM draft_lotto
                           WHERE original_order BETWEEN 1 AND 8
                           ORDER BY RANDOM() * draft_weight DESC
                           LIMIT 1;")->fetchColumn();

// Get the winning GM's pre-Lotto draft spot
$get_win_gmPosition = $pdo->prepare("SELECT original_order
                                     FROM draft_lotto
                                     WHERE gm_name = :winning_gm;");
$get_win_gmPosition->execute(['winning_gm' => $winning_gm]);
$win_gmPosition = $get_win_gmPosition->fetchColumn();

// If the winning GM already has the #1 spot, update the DB accordingly
if ($win_gmPosition == 1) {
    $update_order = $pdo->query("UPDATE draft_lotto
                                 SET lottery_order = original_order
                                 WHERE lottery_order IS NULL;");
} else {
    // Update the winning GM with spot #1
    $update_order = $pdo->prepare("UPDATE draft_lotto
                                   SET lottery_order = '1'
                                   WHERE gm_name = :winning_gm;");
    $update_order->execute(['winning_gm' => $winning_gm]);

    // Generate list of non-winning lottery GMs
    $set_new_order = $pdo->query("SELECT gm_name, original_order, lottery_order
                                  FROM draft_lotto
                                  WHERE lottery_order IS NULL
                                  ORDER BY original_order;");

    // Update the remaining positions, starting with draft spot #2
    $new_spot = 2;
    foreach ($set_new_order as $row) {
        $update_new_order = $pdo->prepare("UPDATE draft_lotto
                                           SET lottery_order = :new_spot
                                           WHERE gm_name = :gm_name;");
        $update_new_order->execute(['new_spot' => $new_spot, 'gm_name' => $row['gm_name']]);
        ++$new_spot;
    }
}

// Update Lottery Draw Date and Time
$lotto_period = $pdo->prepare("UPDATE base_numbers
                               SET lotto_date = :lotto_date,
                                   lotto_time = :lotto_time;");
$lotto_period->execute(['lotto_date' => $lotto_date, 'lotto_time' => $lotto_time]);

// UPDATE THE DRAFT ORDER

// Get draft order -Post lottery draw
$get_order = $pdo->query("SELECT gm_name, lottery_order
                          FROM draft_lotto
                          ORDER BY lottery_order;");

// Update the Draft Order
foreach ($get_order as $row) {
    $gm_name = $row['gm_name'];
    $spot = $row['lottery_order'];

    $update_db = $pdo->prepare("UPDATE gms
                                SET draft_pos = :lottery_order
                                WHERE gm_name = :gm;");
    $update_db->execute(['lottery_order' => $spot, 'gm' => $gm_name]);
}


// TRUNCATE and RESTART Primary Key SEQUENCE
  $pdo->query("TRUNCATE draft_order;");
  $pdo->query("ALTER SEQUENCE draft_order1_dp_pk_seq RESTART WITH 1;");


// -- Adds information to draft position and GM drafting order for Round 1
$update_order_db = $pdo->query("INSERT INTO draft_order (draft_pos, round_1)
                                SELECT draft_pos,gm_name
                                FROM gms;");

// Get the number of rounds from the base_numbers table
$stmt = $pdo->query("SELECT numb_rds FROM base_numbers");
$numb_rds = (int)$stmt->fetchColumn();

// Build dynamic SQL SET clause
$set_clauses = [];
for ($i = 2; $i <= $numb_rds; $i++) {
    $set_clauses[] = "round_$i = round_1";
}
$set_sql = implode(", ", $set_clauses);

// Execute dynamic UPDATE
$update_sql = "UPDATE draft_order SET $set_sql";
$pdo->query($update_sql);


// UPDATE THE DRAFT ORDER COMPLETED


// Will Update base_numbers for last update to the database
include __DIR__ . '/../misc_files/last_db_update.php';
?>

<!-- Lottery Completed -->
<script>
    window.location.href = 'draft_lottery_4_review.php';
</script>