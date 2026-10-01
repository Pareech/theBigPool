<?php include __DIR__ . '/../misc_files/auth_check.php';  ?>

<!DOCTYPE html>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel='stylesheet' type='text/css' href='../css/draft_lottery.css' />

<title>Post Draft Lottery</title>
<link rel="icon" type="image/x-icon" href="misc_files/rhcp_logo.ico" />

<div class='header'>
    <h1>Draft Order<br>Post Lottery Draw</h1>
    <?php
    // session_start();

    include __DIR__ . '/../db_connections/connection_pdo.php';
    include __DIR__ . '/../misc_files/pre_draft_check.php';
    ?>
</div>

<?php
include __DIR__ . '/../misc_files/nav_bar_links.php';

// Lottery draw timestamp
$lotto_date = date("F j, Y");
$lotto_time = date("H:i:s");

// Clear previous lottery order
$pdo->query("UPDATE draft_lotto SET lottery_order = NULL WHERE lottery_order BETWEEN 1 AND 8;");

// ===== Step 1: Get eligible lottery teams (1–8) =====
$stmt = $pdo->query("SELECT gm_name, original_order, draft_weight
                     FROM draft_lotto
                     WHERE original_order BETWEEN 1 AND 8
                     ORDER BY original_order ASC");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build arrays for weighted draw
$weights = [];
$standingOrder = []; // pre-lottery order
foreach ($teams as $row) {
    $weights[$row['gm_name']] = (int)$row['draft_weight'];
    $standingOrder[] = $row['gm_name'];
}

// ===== Step 2: Draw two winners using true weighted random =====
function weighted_draw(array $weights): string
{
    $total = array_sum($weights);
    if ($total <= 0) throw new RuntimeException("Weights must be positive");
    $rand = random_int(1, $total);
    $cumulative = 0;
    foreach ($weights as $gm => $weight) {
        $cumulative += $weight;
        if ($rand <= $cumulative) return $gm;
    }
    return array_key_last($weights); // fallback
}

$winners = [];
$availableWeights = $weights;
for ($pick = 1; $pick <= 2; $pick++) {
    $winner = weighted_draw($availableWeights);
    $winners[] = $winner;
    unset($availableWeights[$winner]); // remove winner from pool
}

// ===== Step 3: Assign lottery spots (1–8) =====
$finalOrder = [];
$spot = 1;

// Assign winners to top 2 spots
foreach ($winners as $gm) {
    $finalOrder[$gm] = $spot++;
}

// Assign remaining lottery teams in pre-lottery order
foreach ($standingOrder as $gm) {
    if (!in_array($gm, $winners, true)) {
        $finalOrder[$gm] = $spot++;
    }
}

// ===== Step 3b: Assign fixed bottom picks (9–11) =====
$stmtBottom = $pdo->query("SELECT gm_name, original_order
                           FROM draft_lotto
                           WHERE original_order BETWEEN 9 AND 11
                           ORDER BY original_order ASC");
$bottomTeams = $stmtBottom->fetchAll(PDO::FETCH_ASSOC);
foreach ($bottomTeams as $row) {
    $finalOrder[$row['gm_name']] = $row['original_order']; // keep same spot
}

// ===== Step 4: Update draft_lotto table =====
foreach ($finalOrder as $gm => $lottery_order) {
    $update = $pdo->prepare("UPDATE draft_lotto
                             SET lottery_order = :lottery_order
                             WHERE gm_name = :gm");
    $update->execute(['lottery_order' => $lottery_order, 'gm' => $gm]);
}

// ----- Step 5: Update base_numbers with draw timestamp -----
$lotto_period = $pdo->prepare("UPDATE base_numbers
                               SET lotto_date = :lotto_date,
                                   lotto_time = :lotto_time");
$lotto_period->execute(['lotto_date' => $lotto_date, 'lotto_time' => $lotto_time]);

// ----- Step 6: Update gms table draft positions -----
$get_order = $pdo->query("SELECT gm_name, lottery_order FROM draft_lotto ORDER BY lottery_order");
foreach ($get_order as $row) {
    $update_db = $pdo->prepare("UPDATE gms
                                SET draft_pos = :lottery_order
                                WHERE gm_name = :gm");
    $update_db->execute(['lottery_order' => $row['lottery_order'], 'gm' => $row['gm_name']]);
}

// ----- Step 7: Rebuild draft_order table -----
$pdo->query("TRUNCATE draft_order;");
$pdo->query("ALTER SEQUENCE draft_order1_dp_pk_seq RESTART WITH 1;");

$pdo->query("INSERT INTO draft_order (draft_pos, round_1)
             SELECT draft_pos, gm_name
             FROM gms");


// Additional rounds use the original_order
$stmt = $pdo->query("SELECT numb_rds FROM base_numbers");
$numb_rds = (int)$stmt->fetchColumn();

for ($i = 2; $i <= $numb_rds; $i++) {
    // reset this column to NULL
    $pdo->query("UPDATE draft_order SET round_$i = NULL");

    // now fill it in by joining to draft_lotto.original_order
    $fill = $pdo->query("WITH base AS (
                              SELECT gm_name, original_order
                              FROM draft_lotto
                              ORDER BY original_order
                         )
                         UPDATE draft_order d
                         SET round_$i = b.gm_name
                         FROM base b
                         WHERE d.draft_pos = b.original_order");
}

// ----- Step 8: Last DB update -----
include __DIR__ . '/../misc_files/last_db_update.php';
?>

<script>
    window.location.href = 'draft_lottery_4_review.php';
</script>