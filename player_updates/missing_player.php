<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add Player to Database</title>
  <link rel="stylesheet" href="../css/round_npick.css" />
  <link rel="stylesheet" href="../css/player_info_update.css" />
  <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
</head>

<body>
  <div class="grid-container-top">
    <div class="title">
      <h1>Add Player<br>to Database</h1>
    </div>

    <div class="draft_status">
      <?php
      include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../draft_state/round_npick.php';
?>
    </div>
  </div>

  <?php
  include __DIR__ . '/../misc_files/nav_bar_links.php';

$team_list = "SELECT DISTINCT team FROM salaries WHERE team IS NOT NULL ORDER BY team;";
$position_list = "SELECT DISTINCT position FROM salaries ORDER BY position;";

$teams = $pdo->query($team_list)->fetchAll(PDO::FETCH_COLUMN);
?>

  <form name="display" action="" method="POST">
    <div class="grid-container-bottom">

      <div>
        <h3>Player</h3>
        <input type="text" class="textbox" name="player" placeholder="Player Name" required />
      </div>

      <div>
        <h3>Position</h3>
        <select name="position" class="textbox" required>
          <option value="">-- Select Position --</option>
          <?php
        foreach ($pdo->query($position_list) as $row_pos) {
            $pos = htmlspecialchars($row_pos['position']);
            echo "<option value=\"$pos\">$pos</option>";
        }
?>
        </select>
      </div>

      <div>
        <h3>Salary</h3>
        <input type="number" class="textbox" name="salary" placeholder="Enter Salary" min="0" step="any" required />
      </div>

      <div>
        <h3>Team</h3>
        <input type="text" class="textbox" list="team_search" name="team" placeholder="Team Name" required />
        <datalist id="team_search">
          <?php
foreach ($teams as $team) {
    $escaped = htmlspecialchars($team);
    echo "<option value=\"$escaped\">$escaped</option>";
}
?>
        </datalist>
      </div>

      <div>
        <button type="submit" name="submit" class="buttonSet">Add the<br>Missing Player</button>
      </div>

      <div>
        <button type="reset" class="buttonSet">Reset the Form</button>
      </div>

    </div>
  </form>

  <?php
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
      $player = ucwords(strtolower(trim((string)$_POST['player'])), ".,' ");
      $position = $_POST['position'];
      $salary = $_POST['salary'];
      $team = strtoupper(trim($_POST['team']));

      if (!in_array($team, $teams, true)) {
          echo "<script>alert('Invalid team selected.'); history.back();</script>";
          exit;
      }

      $check_inDB = $pdo->prepare("
      SELECT COUNT(player) AS counted, MAX(gm) AS gm
      FROM salaries
      WHERE player = :player
    ");
      $check_inDB->execute(['player' => $player]);
      $row = $check_inDB->fetch(PDO::FETCH_ASSOC);

      if ($row && $row['counted'] > 0) {
          $drafted_by = $row['gm'];
          $msg = $drafted_by
            ? "$player has already been drafted by $drafted_by."
            : "$player is already in the database.";
          echo
          "<script>
          alert('$msg');
          window.location.href='../find_player/player_search_db.php?player=" . urlencode($player) . "';
      </script>";
          exit();
      } else {
          $add_player = $pdo->prepare("
        INSERT INTO salaries (player, team, current_salary, position)
        VALUES (:player, :team, :salary, :position)");
          $add_player->execute([
            'player' => $player,
            'team' => $team,
            'salary' => $salary,
            'position' => $position
          ]);
          echo
          "<script>
          alert('$player has been added to the database.');
          window.location.href='../find_player/player_search_db.php?player=" . urlencode($player) . "';
      </script>";
          exit();
      }
  }
?>
</body>

</html>