<?php
include __DIR__ . '/../misc_files/auth_check.php';
include __DIR__ . '/../db_connections/connection_pdo.php';

$alert_message = '';
$chosenPosition = $_POST['position'] ?? '';
$players_list = [];

// Process Add action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $players = $_POST['players'] ?? [];
  $notesByPlayer = $_POST['notes'] ?? [];

  if ($players) {
    $upd = $pdo->prepare("UPDATE salaries
                          SET draft_research = 'x',
                              draft_research_notes = :notes
                          WHERE player = :player");
    $updatedCount = 0;
    foreach ($players as $player_raw) {
      $note = isset($notesByPlayer[$player_raw]) ? trim((string)$notesByPlayer[$player_raw]) : '';
      $upd->execute([
        'notes'  => $note,
        'player' => $player_raw
      ]);
      $updatedCount++;
    }
    $alert_message = "{$updatedCount} player(s) added to Draft Wants.";
  } else {
    $alert_message = "No players were selected.";
  }
}

// If a position is present in POST (auto-submitted or after Add), fetch players
if (!empty($_POST['position'])) {
  $chosenPosition = $_POST['position'];
  if (!empty($chosenPosition)) {
    $player_stmt = $pdo->prepare("SELECT player, team, current_salary, gm
                                  FROM salaries
                                  WHERE draft_research IS NULL
                                    AND position = :position
                                  ORDER BY player");
    $player_stmt->execute(['position' => $chosenPosition]);
    $players_list = $player_stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Draft Wants</title>

  <link rel="stylesheet" href="../css/round_npick.css" />
  <link rel="stylesheet" href="../css/notes.css?v=7" />

  <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
  <script>
    let sortDirections = {};

    function sortTable(tableId, n) {
      const table = document.getElementById(tableId);
      if (!table) return;
      const tbody = table.querySelector("tbody");
      const rows = Array.from(tbody.querySelectorAll("tr"));
      const key = tableId + "_" + n;
      sortDirections[key] = sortDirections[key] === "asc" ? "desc" : "asc";
      const dir = sortDirections[key];
      rows.sort(function(a, b) {
        const x = a.querySelectorAll("td")[n]?.innerText.trim().toLowerCase() ?? "";
        const y = b.querySelectorAll("td")[n]?.innerText.trim().toLowerCase() ?? "";
        const nx = parseFloat(x.replace(/[^0-9.-]/g, ""));
        const ny = parseFloat(y.replace(/[^0-9.-]/g, ""));
        if (!isNaN(nx) && !isNaN(ny)) return dir === "asc" ? nx - ny : ny - nx;
        return dir === "asc" ? x.localeCompare(y) : y.localeCompare(x);
      });
      rows.forEach(row => tbody.appendChild(row));
    }
  </script>
</head>

<body>

  <div id="sticky_top">
    <div class="grid-container-top">
      <div class="title">
        <h1>My Draft<br>Choice Wants</h1>
      </div>

      <div class="draft_status">
        <?php include __DIR__ . '/../draft_state/round_npick.php'; ?>
      </div>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <!-- single form wraps controls + table so checkboxes submit correctly -->
    <form method="POST" id="draftForm">
      <div class="controls-bar">
        <label for="position">Position:</label>
        <select name="position" id="position" onchange="document.getElementById('draftForm').submit();" aria-label="Select position">
          <option value="">-- Choose --</option>
          <option value="F" <?= ($chosenPosition === 'F') ? 'selected' : '' ?>>F</option>
          <option value="D" <?= ($chosenPosition === 'D') ? 'selected' : '' ?>>D</option>
          <option value="G" <?= ($chosenPosition === 'G') ? 'selected' : '' ?>>G</option>
        </select>
        <button type="submit" name="action" value="add" class="buttonSet">Add to my Draft Wants</button>
      </div>

      <!-- Table rendered when $chosenPosition has players -->
      <?php if (!empty($players_list)): ?>
        <div class="table-scroll">
          <div id="table_container">
            <table class="make_notes_table" id="player_table">
              <thead>
                <tr>
                  <th onclick="sortTable('player_table', 0)">Player</th>
                  <th onclick="sortTable('player_table', 1)">Team</th>
                  <th onclick="sortTable('player_table', 2)">Salary</th>
                  <th>Notes</th>
                  <th onclick="sortTable('player_table', 4)">Protected</th>
                  <th>Select</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($players_list as $row): ?>
                  <tr>
                    <td class="td-left"><?= e($row['player']) ?></td>
                    <td><?= e($row['team']) ?></td>
                    <td>$<?= number_format($row['current_salary'] ?? 0) ?></td>
                    <td>
                      <input class="notes-input" type="text"
                        name="notes[<?= e($row['player']) ?>]"
                        maxlength="600" />
                    </td>
                    <td><?= e($row['gm']) ?></td>
                    <td style="text-align:center;">
                      <input type="checkbox" name="players[]" value="<?= e($row['player']) ?>">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php elseif ($chosenPosition !== ''): ?>
        <p style="text-align:center; margin-top:12px;">No players found for position <?= htmlspecialchars($chosenPosition) ?>.</p>
      <?php endif; ?>
    </form>
  </div>

  <?php
  // Show native alert if we prepared one server-side
  if (!empty($alert_message)) {
    $js_msg = str_replace("'", "\\'", $alert_message);
    echo
    "<script>
      alert('{$js_msg}');
      window.location.href = 'my_draft_notes.php';
    </script>";
  }


  function e(?string $value): string
  {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
  }

  ?>

</body>

</html>