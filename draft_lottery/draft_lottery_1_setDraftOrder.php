<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set Order for Lottery Draw</title>
  <link rel='stylesheet' type='text/css' href='../css/draft_lottery.css' />
  <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

  <div class="header">
    <h1 style="color:#D76E00">Enter Draft Order<br>Used for Draft Lottery</h1>
  </div>

  <?php
  include __DIR__ . '/../db_connections/connection_pdo.php';

  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $csrf_token = $_SESSION['csrf_token'];

  include __DIR__ . '/../misc_files/nav_bar_links.php';

  $draft_order_set = date("F j, Y");

  $gm_list = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name;");
  $gm_names = $gm_list->fetchAll(PDO::FETCH_COLUMN);

  // Build the dropdown order from standings: lowest points = spot 1, highest = spot 11.
  // standing_pts.php returns rows DESC (best first), so reversing gives worst-first.
  include __DIR__ . '/../player_scoring/standing_pts.php';
  $standings_rows = $standing_pts->fetchAll(PDO::FETCH_ASSOC);
  $standings_order = array_reverse(array_column($standings_rows, 'gm_name'));

  $default_order = [];
  foreach ($standings_order as $idx => $gm_name) {
    $default_order[$idx + 1] = $gm_name;
  }

  $lotto_info = $pdo->query("SELECT lottery_order_set, lotto_date, lotto_time FROM base_numbers LIMIT 1;")->fetch(PDO::FETCH_ASSOC);

  if ($lotto_info && $lotto_info['lotto_date'] && $lotto_info['lotto_time']) {
    $formatted_date = date("F j, Y", strtotime($lotto_info['lotto_date']));
    $last_draw = "Last Draw:<br>" . $formatted_date . " at " . htmlspecialchars($lotto_info['lotto_time']);
  } else {
    $last_draw = "No Previous Lotto Draw";
  }

  if ($lotto_info && $lotto_info['lottery_order_set']) {
    $formatted_date = date("F j, Y", strtotime($lotto_info['lottery_order_set']));
    $order_set = "Lotto Odds Set:<br>" . $formatted_date;
  } else {
    $order_set = "Draft Order Not Set";
  }
  ?>

  <form name="choose_order" action="" method="POST">
    <input type="hidden" name="csrf_token"
      value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="grid-container_finish">

      <!-- Column 1: Picks 1-4 -->
      <div>
        <?php for ($i = 1; $i <= 4; $i++): ?>
          <div class="draft-row">
            <span class="colon-space"><label for="pick<?= $i ?>">Lotto
                Spot <?= $i ?>:</label></span>
            <select name="pick<?= $i ?>"
              id="pick<?= $i ?>" class="dropmenus" required>
              <option value="">-- Select GM --</option>
              <?php foreach ($gm_names as $gm_name): ?>
                <option value="<?= htmlspecialchars($gm_name) ?>" <?= isset($default_order[$i]) && $default_order[$i] === $gm_name ? 'selected' : '' ?>>
                  <?= htmlspecialchars($gm_name) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Column 2: Picks 5-8 -->
      <div>
        <?php for ($i = 5; $i <= 8; $i++): ?>
          <div class="draft-row">
            <span class="colon-space"><label for="pick<?= $i ?>">Lotto
                Spot <?= $i ?>:</label></span>
            <select name="pick<?= $i ?>"
              id="pick<?= $i ?>" class="dropmenus" required>
              <option value="">-- Select GM --</option>
              <?php foreach ($gm_names as $gm_name): ?>
                <option value="<?= htmlspecialchars($gm_name) ?>" <?= isset($default_order[$i]) && $default_order[$i] === $gm_name ? 'selected' : '' ?>>
                  <?= htmlspecialchars($gm_name) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Column 3: Picks 9-11 -->
      <div>
        <?php for ($i = 9; $i <= 11; $i++): ?>
          <div class="draft-row">
            <span class="colon-space"><label
                for="pick<?= $i ?>">Non-Lotto
                <?= $i ?>:</label></span>
            <select name="pick<?= $i ?>"
              id="pick<?= $i ?>" class="dropmenus" required>
              <option value="">-- Select GM --</option>
              <?php foreach ($gm_names as $gm_name): ?>
                <option value="<?= htmlspecialchars($gm_name) ?>" <?= isset($default_order[$i]) && $default_order[$i] === $gm_name ? 'selected' : '' ?>>
                  <?= htmlspecialchars($gm_name) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Submit Button and Info -->
      <div class="submitButton">
        <button type="submit" name="draft_order" class="submit">Submit Draft Order</button>
        <div class="lastOrderTime"><?= $order_set ?></div>
        <div class="lastDrawTime"><?= $last_draw ?></div>
      </div>

    </div>
  </form>

  <?php
  function validateDraftPicks(array $post_data, int $total_picks): array
  {
    $errors = [];
    $picks = [];
    $gm_counts = [];

    for ($i = 1; $i <= $total_picks; $i++) {
      $key = 'pick' . $i;
      $gm = trim($post_data[$key] ?? '');

      if ($gm === '') {
        $errors[] = "Pick $i is not selected.";
      } else {
        $gm = htmlspecialchars($gm, ENT_QUOTES, 'UTF-8');
        $picks[$i] = $gm;
        $gm_counts[$gm] = ($gm_counts[$gm] ?? 0) + 1;
      }
    }

    foreach ($gm_counts as $gm => $count) {
      if ($count > 1) {
        $errors[] = "GM '$gm' was selected $count times.";
      }
    }

    return [$errors, $picks];
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draft_order'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
      echo "<script>
            alert('Authorization Failure');
            window.location.href = 'https://hmpg.net';
         </script>";
      exit;
    }

    $pdo->query("UPDATE base_numbers SET lotto_date = NULL, lotto_time = NULL;");

    $lotto_order_date = $pdo->prepare("UPDATE base_numbers SET lottery_order_set = :lotto_date;");
    $lotto_order_date->execute(['lotto_date' => $draft_order_set]);

    $total_picks = $pdo->query("SELECT count(gm_name) FROM gms;")->fetchColumn();
    [$errors, $picks] = validateDraftPicks($_POST, $total_picks);

    if (!empty($errors)) {
      $alert_message = implode("\n", $errors);
      echo "<script>alert(" . json_encode($alert_message) . ");</script>";
    } else {
      $stmt = $pdo->prepare("UPDATE draft_lotto SET gm_name = :gm_name WHERE original_order = :original_order;");

      foreach ($picks as $pick_num => $gm_name) {
        $stmt->execute(['gm_name' => $gm_name, 'original_order' => $pick_num]);
      }

      echo "<script>window.location.href = '../draft_lottery/draft_lottery_2_preDraw.php';</script>";
      exit;
    }
  }
  ?>

</body>

</html>