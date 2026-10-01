<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Player Update</title>
  <link rel="stylesheet" href="../css/round_npick.css" />
  <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
</head>

<body>
  <div class="grid-container-top">
    <div class="title">
      <h1>Update Player<br>Information</h1>
    </div>

    <div class="draft_status">
      <?php
      include __DIR__ . '/../db_connections/connection_pdo.php';
      include __DIR__ . '/../draft_state/round_npick.php';
      ?>
    </div>
  </div>

  <!-- Style For the Navigation Bar and Below -->
  <link rel="stylesheet" href="../css/player_info_update.css" />
  <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

  <?php
  // Query once for your valid teams (all uppercase in DB)
  $team_list    = "SELECT DISTINCT team FROM salaries WHERE team IS NOT NULL ORDER BY team;";
  $teams        = $pdo->query($team_list)->fetchAll(PDO::FETCH_COLUMN);

  // For player autocomplete
  $player_list = $pdo->prepare("SELECT player FROM salaries ORDER BY player;");
  $player_list->execute();
  ?>

  <form name="display" action="" method="POST">
    <div class="grid-container-bottom">
      <div>
        <h3>Select Player</h3>
        <input type="text" class="textbox" list="player_search" name="player" required />
        <datalist id="player_search">
          <option value=""></option>
          <?php foreach ($player_list as $row): ?>
            <option value="<?= htmlspecialchars($row['player']) ?>"><?= htmlspecialchars($row['player']) ?></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <div>
        <h3>Salary</h3>
        <input type="number" class="textbox" name="salary" placeholder="Enter Salary" min="0" step="any" />
      </div>

      <div>
        <h3>Team</h3>
        <input type="text" class="textbox" list="team_search" name="team" placeholder="Team Name" />
        <datalist id="team_search">
          <?php foreach ($teams as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <div>
        <button type="submit" name="update_player" class="buttonSet">Update Player Information</button>
      </div>
      <div>
        <button type="reset" class="buttonSet">Reset the Form</button>
      </div>
    </div>
  </form>

  <?php
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_player'])) {
    $player_raw = trim((string)$_POST['player']);
    $salary_raw = trim((string)$_POST['salary']);
    $team_raw   = trim((string)$_POST['team']);

    // 1) If both salary & team left blank at submission, stop:
    if ($salary_raw === '' && $team_raw === '') {
      $js = "alert('Please update {$player_raw}’s salary and/or team.'); history.back();";
      echo "<script>{$js}</script>";
      exit;
    }

    // 2) Fetch current values from salaries table
    $stmt = $pdo->prepare("SELECT current_salary AS sal, team AS tm FROM salaries WHERE player = :player");
    $stmt->execute(['player' => $player_raw]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      echo "<script>alert('Player not found.'); history.back();</script>";
      exit;
    }

    // 3) Determine final salary & team:
    $salary = ($salary_raw === '') ? $row['sal'] : $salary_raw;
    $teamUc  = strtoupper($team_raw);  // normalize entered team to uppercase
    $team    = ($team_raw === '') ? $row['tm'] : $teamUc;

    // 4) Detect if the team actually changed
    $team_changed = ($team !== $row['tm']);

    // 5) Validate team against your cached list
    if (!in_array($team, $teams, true)) {
      echo "<script>alert('Invalid team selected.'); history.back();</script>";
      exit;
    }

    // 6) Perform the update
    $upd = $pdo->prepare("UPDATE salaries
                          SET current_salary = :salary,
                                team           = :team
                          WHERE player        = :player");
    $upd->execute([
      'salary' => $salary,
      'team'   => $team,
      'player' => $player_raw
    ]);

    // 7) If team changed, update waiver_moves (only if player exists there)
    if ($team_changed) {

      // Check if player exists in waiver_moves
      $checkWaiver = $pdo->prepare("SELECT player
                                    FROM waiver_moves
                                    WHERE player = :player
                                    LIMIT 1");
      $checkWaiver->execute(['player' => $player_raw]);

      if ($checkWaiver->fetch()) {

        // Update team in waiver_moves
        $updWaiver = $pdo->prepare("UPDATE waiver_moves
                                    SET team = :team
                                    WHERE player = :player");
        $updWaiver->execute([
          'team'   => $team,
          'player' => $player_raw
        ]);
      }
    }

    // 8) Redirect back to your search page with ?player=...
    $urlPlayer = urlencode($player_raw);
    echo "<script>
          alert('{$player_raw}’s information has been updated.');
          window.location.href='../find_player/player_search_db.php?player={$urlPlayer}';
        </script>";
    exit;
  }
  ?>
</body>

</html>