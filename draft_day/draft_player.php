<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<?php
// Setup variables
$position = $_SESSION['position'] = $_GET['position'] ?? '';

switch ($position) {
  case 'D':
    $position_title = 'Defenceman';
    break;
  case 'F':
    $position_title = 'Forward';
    break;
  case 'G':
    $position_title = 'Goalie';
    break;
  default:
    $position_title = 'Unknown';
    break;
}

include __DIR__ . '/../db_connections/connection_pdo.php';

$sql_players = "SELECT player, team, current_salary
                FROM salaries
                WHERE position = :position AND gm IS NULL
                ORDER BY player;";
$player_list = $pdo->prepare($sql_players);
$player_list->execute(['position' => $position]);

$money = new NumberFormatter('en', NumberFormatter::CURRENCY);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Draft a Player</title>
  <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
  <link rel='stylesheet' type='text/css' href='../css/player_available.css' />
  <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

  <div class="grid-container-top">
    <div class="title">
      <h1>Draft a<br><?php echo htmlspecialchars($position_title); ?>
      </h1>
    </div>
    <div class="draft_status">
      <?php
      include __DIR__ . '/../draft_state/round_npick.php';

      if (strcasecmp($_SESSION['gm_name'], $picking_now) !== 0) {
        echo "<script>
                        alert('It is not your turn to draft. It is currently {$picking_now}\'s pick.');
                        window.location.href = '../draft_state/draft_order.php';
                    </script>";
        exit();
      }

      ?>
    </div>
  </div>

  <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>
  <?php include __DIR__ . '/../misc_files/header_row_sticky.js'; ?>

  <form method="post" action="draft_player_db_update.php">
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
              <th>Draft Player</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($player_list as $row): ?>
              <tr>
                <td class="bottom_draft">
                  <?php echo htmlspecialchars($row['player']); ?>
                </td>
                <td class="bottom_draft">
                  <?php echo htmlspecialchars($row['team'] ?? ''); ?>
                </td>
                <td class="bottom_draft">
                  <?php echo $money->formatCurrency((int)$row['current_salary'], 'USD'); ?>
                </td>
                <td class="bottom_draft">
                  <input type="radio" name="radioChoice"
                    value="<?php echo htmlspecialchars($row['player']); ?>">
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