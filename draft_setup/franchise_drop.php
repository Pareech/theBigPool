<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>

<meta charset="UTF-8" />

<title>Drop Franchise Player</title>

<?php
include __DIR__ . '/../db_connections/connection_pdo.php';
?>

<?php
$gm = $_POST['poolee'];

// Get Name of Franchise Player Being Dropped
$drop_franchise_query = $pdo->prepare("SELECT player FROM salaries WHERE drafted = 'Franchise' AND gm = :gm;");
$drop_franchise_query->execute(['gm' => $gm]);
$drop_franchise = $drop_franchise_query->fetchColumn();

$update = $pdo->prepare("UPDATE salaries
                         SET drafted = NULL,
                             franchise = NULL,
                             franchise_date = NULL,
                             latest_pick = NULL
                         WHERE player = :drop_franch;");
$update->execute(['drop_franch' => $drop_franchise]);

// Will Update base_numbers for last update to the database
include __DIR__ . '/../misc_files/last_db_update.php';

echo
"<script>
    alert('$drop_franchise has been removed as the designated franchise player from $gm\\'s roster.\\n\\n'
          + 'A new franchise player will need to be selected before the Waiver Draft.');
          window.location.href='../gm_listings/gm_info.php?gm=$gm';
  </script>";
exit();
?>