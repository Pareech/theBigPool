<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select a GM</title>
  <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
  <link rel='stylesheet' type='text/css' href='../css/lineup_changes.css' />
  <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>
<body>

<?php
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

$doing = $_GET['doing'];

if ($doing == 'draft') {
  if (empty($_POST['radioChoice'])) {
    echo
    "<script>
          alert('You did not select a player to be drafted.');
          window.history.back();
      </script>";
  } else {
    $player = $_POST['radioChoice'];
    $_SESSION['player'] = $player;
    $title = 'Drafting <span style="color:#FFFFFF">' . $player . '</span>';
    $do_this = '../draft_day/draft_player_db_update.php';
  }
} elseif ($doing == 'drop') {
  $title = 'Dropping Player(s)';
  $do_this = '../draft_setup/drop_players.php';
} elseif ($doing == 'franchise_drop') {
  $title = 'Dropping Franchise Player';
  $do_this = '../draft_setup/franchise_drop.php';
}
?>

<div class="grid-container-top">
  <div class="title">
    <h1><?php echo "<font color='#D76E00'>Select GM</br>" . $title . "</font>"; ?></h1>
  </div>

  <div class="draft_status">
    <?php include __DIR__ . '/../draft_state/round_npick.php'; ?>
  </div>
</div>

<?php
$gm_list = $pdo->prepare("SELECT gm_name FROM gms ORDER BY gm_name;");
$gm_list->execute();

include __DIR__ . '/../misc_files/nav_bar_links.php';
?>

<form name="display" action="<?php echo $do_this ?>" method="POST">
  <div class="grid-container-bottom">
    <div class="gmMenu">
      <h3>Select GM</h3>
      <select name="poolee" class="dropmenus" required>
        <option value="">-- Please Select --</option>
        <?php
        foreach ($gm_list as $row) {
          $gm_name = htmlspecialchars($row['gm_name'], ENT_QUOTES, 'UTF-8');
          echo "<option value=\"$gm_name\">$gm_name</option>";
        }
        ?>
      </select>
    </div>

    <div class="submitButton">
      <button type="submit" name="submit" class="submit">Confirm</button>
    </div>
  </div>
</form>

</body>
</html>