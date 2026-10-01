<!DOCTYPE html>

<?php
include __DIR__ . '/../db_connections/connection_pdo.php';

$clear_waiver_monies =  $pdo->prepare("UPDATE salaries 
                                       SET waiver_bid = null, 
                                           salary_retained = null, 
                                           latest_pick = NULL
                                       WHERE drafted = 'Waiver';");
$clear_waiver_monies->execute();

// Will Update base_numbers for last update to the database
include __DIR__ . '/../misc_files/last_db_update.php';

echo
"<script>
    alert('Waiver payments and retained salaries have been cleared.\\nWaiver still showing as obtained for historic.');
    window.location.href='../draft_state/draft_order.php';
</script>";

?>