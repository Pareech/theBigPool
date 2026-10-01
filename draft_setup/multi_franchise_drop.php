<!DOCTYPE html>

<?php
include __DIR__ . '/../db_connections/connection_pdo.php';

$current_date = date('d-M-Y');

$franchise_date =  $pdo->prepare("SELECT player, gm, franchise, franchise_date, drafted 
                                  FROM salaries 
                                  WHERE franchise IS NOT NULL;");
$franchise_date->execute();

$multi_franch_removal = $pdo->prepare("UPDATE salaries 
                                       SET franchise = NULL, 
                                           franchise_date = NULL,
                                           latest_pick = NULL,
                                           drafted = NULL
                                       WHERE player = :player");

foreach ($franchise_date as $row) {
    if (strtotime($current_date) >= strtotime($row['franchise_date'])) {
        $multi_franch_removal->execute(['player' => $row['player']]);
    }
}

// Will Update base_numbers for last update to the database
include __DIR__ . '/../misc_files/last_db_update.php';

echo
"<script>
    alert('Franchise tag has been dropped from all players,\\nwhose franchise designation expired before $current_date.');
    window.location.href='../draft_state/draft_order.php';
</script>";
?>