<?php
include __DIR__ . '/../misc_files/auth_check.php';
include __DIR__ . '/../db_connections/connection_pdo.php';

$get_my_list = $pdo->query("SELECT player, team, current_salary, position, draft_research_notes, gm
                            FROM salaries
                            WHERE draft_research IS NOT NULL
                            ORDER BY position, player")->fetchAll(PDO::FETCH_ASSOC);

$defencemen = [];
$forwards   = [];
$goalies    = [];

foreach ($get_my_list as $row) {
  if ($row['position'] === 'D') {
    $defencemen[] = $row;
  } elseif ($row['position'] === 'F') {
    $forwards[]   = $row;
  } elseif ($row['position'] === 'G') {
    $goalies[]    = $row;
  }
}

function e(?string $value): string
{
  return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function renderTable(array $rows, string $tableId): void
{
?>
  <table class="my_notes_table" id="<?= $tableId ?>">
    <thead>
      <tr>
        <th onclick="sortTable('<?= $tableId ?>', 0)">Player</th>
        <th onclick="sortTable('<?= $tableId ?>', 1)">Team</th>
        <th onclick="sortTable('<?= $tableId ?>', 2)">Pos</th>
        <th onclick="sortTable('<?= $tableId ?>', 3)">Salary</th>
        <th onclick="sortTable('<?= $tableId ?>', 4)">Notes</th>
        <th onclick="sortTable('<?= $tableId ?>', 5)">Drafted</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="td-left">
            <?= e($row['player']) ?></td>
          <td><?= e($row['team']) ?></td>
          <td><?= e($row['position']) ?></td>
          <td>
            $<?= number_format((float)($row['current_salary'] ?? 0), 0) ?>
          </td>
          <td class="td-left">
            <?= e($row['draft_research_notes']) ?>
          </td>
          <td><?= e($row['gm']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Draft Notes</title>
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

    function showTable() {
      const selected = document.getElementById("positionSelect").value;
      document.getElementById("table-all").style.display = (selected === "all") ? "block" : "none";
      document.getElementById("table-D").style.display = (selected === "D") ? "block" : "none";
      document.getElementById("table-F").style.display = (selected === "F") ? "block" : "none";
      document.getElementById("table-G").style.display = (selected === "G") ? "block" : "none";
    }

    document.addEventListener('DOMContentLoaded', showTable);
  </script>
</head>

<body>

  <div class="grid-container-top">
    <div class="title">
      <h1>My Draft<br>Day Notes</h1>
    </div>
    <div class="draft_status">
      <?php include __DIR__ . '/../draft_state/round_npick.php'; ?>
    </div>
  </div>

  <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

  <div class="notes-wrapper">

    <div class="controls-draft">
      <label for="positionSelect"><strong>Show:</strong></label>
      <select id="positionSelect" onchange="showTable()">
        <option value="all" selected>All</option>
        <option value="D">Defencemen</option>
        <option value="F">Forwards</option>
        <option value="G">Goalies</option>
      </select>
    </div>

    <!-- ALL table (merged) -->
    <div id="table-all" class="table-scroll">
      <?php renderTable(array_merge($defencemen, $forwards, $goalies), 'table-all-data'); ?>
    </div>

    <!-- Individual position tables -->
    <div id="table-D" class="table-scroll" style="display:none;">
      <h2>Defencemen</h2>
      <?php renderTable($defencemen, 'table-D-data'); ?>
    </div>

    <div id="table-F" class="table-scroll" style="display:none;">
      <h2>Forwards</h2>
      <?php renderTable($forwards, 'table-F-data'); ?>
    </div>

    <div id="table-G" class="table-scroll" style="display:none;">
      <h2>Goalies</h2>
      <?php renderTable($goalies, 'table-G-data'); ?>
    </div>

  </div>

</body>

</html>