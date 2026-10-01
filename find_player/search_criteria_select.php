<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Player Search by Position & Salary</title>
  <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
  <link rel='stylesheet' type='text/css' href='../css/player_available.css' />
  <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

  <div class="grid-container-top">
    <div class="title">
      <h1>Enter Player<br>Search Criteria</h1>
    </div>
    <div class="draft_status">
      <?php
        include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../draft_state/round_npick.php';
?>
    </div>
  </div>

  <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

  <form name="player_search" action="search_criteria_db.php" method="POST">
    <div class="grid-criteria">

      <div class="div_format">
        <h3>Select Player<br>Position</h3>
        <select name="position" id="textboxid" class="dropmenus">
          <option value=""></option>
          <option value="D">Defencemen</option>
          <option value="F">Forwards</option>
          <option value="G">Goalies</option>
        </select>
      </div>

      <div class="buttonSubmit">
        <button type="submit" id="select_critera" name="player_filter" class="buttonSet" value="submit">Submit Filter
          Selection</button>
      </div>

      <div class="div_textbox">
        <h3>Salary Range</h3>
        <input type="text" id="textbox_minSalary" class="textbox" placeholder="If blank, no minimum"
          name="salary_min" />
        <br><br> to <p>
          <input type="text" id="textbox_maxSalary" class="textbox" placeholder="If blank, no maximum"
            name="salary_max" />
      </div>

    </div>
  </form>

</body>

</html>