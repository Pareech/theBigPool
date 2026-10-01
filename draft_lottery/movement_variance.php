<?php
include __DIR__ . '/../misc_files/auth_check.php'; 
session_start();

// SETTINGS
$iterations = isset($_POST['iterations']) ? max(1, intval($_POST['iterations'])) : 5000;

// GM data with weights (1–8 lottery picks)
$gmData = [
    ['gm_name' => 'Tony', 'weight' => 227, 'original_order' => 1],
    ['gm_name' => 'Sean', 'weight' => 166, 'original_order' => 2],
    ['gm_name' => 'Stef', 'weight' => 141, 'original_order' => 3],
    ['gm_name' => 'Rich', 'weight' => 117, 'original_order' => 4],
    ['gm_name' => 'Richard', 'weight' => 104, 'original_order' => 5],
    ['gm_name' => 'Nelson', 'weight' => 92, 'original_order' => 6],
    ['gm_name' => 'Lucio', 'weight' => 80, 'original_order' => 7],
    ['gm_name' => 'Ian', 'weight' => 73, 'original_order' => 8],
];

// Bottom picks (fixed, no lottery)
$bottomGMs = [
    ['gm_name' => 'Eddy', 'original_order' => 9],
    ['gm_name' => 'Eric', 'original_order' => 10],
    ['gm_name' => 'Bob', 'original_order' => 11],
];

// Extract GM names for results array
$gmList = array_column($gmData, 'gm_name');
$gmList = array_merge($gmList, array_column($bottomGMs, 'gm_name'));

// Prepare results arrays
$resultsWeighted = [];
$resultsFlat = [];
foreach ($gmList as $gm) {
    $resultsWeighted[$gm] = array_fill(1, 11, 0);
    $resultsFlat[$gm] = array_fill(1, 11, 0);
}

// ----- FUNCTIONS -----

// Weighted lottery (top 8 only)
function runWeightedDraft($gmData) {
    $order = [];
    $available = $gmData;
    for ($pos = 1; $pos <= 2; $pos++) { // top 2 winners
        $totalWeight = array_sum(array_column($available, 'weight'));
        $rand = mt_rand(1, $totalWeight);
        $cumulative = 0;
        foreach ($available as $key => $gm) {
            $cumulative += $gm['weight'];
            if ($rand <= $cumulative) {
                $order[] = $gm['gm_name'];
                unset($available[$key]);
                $available = array_values($available);
                break;
            }
        }
    }
    // Remaining lottery teams (3–8) in original order
    foreach ($available as $gm) {
        $order[] = $gm['gm_name'];
    }
    return $order;
}

// Flat lottery (shuffle top 8 only)
function runFlatDraft($gmData) {
    $top8 = array_column($gmData, 'gm_name');
    shuffle($top8);
    return $top8;
}

// ----- RUN SIMULATIONS -----
for ($i = 0; $i < $iterations; $i++) {
    // Weighted
    $weightedOrder = runWeightedDraft($gmData);
    $weightedOrder = array_merge($weightedOrder, array_column($bottomGMs, 'gm_name')); // add fixed bottom
    foreach ($weightedOrder as $pos => $gm) {
        $resultsWeighted[$gm][$pos + 1]++;
    }

    // Flat
    $flatOrder = runFlatDraft($gmData);
    $flatOrder = array_merge($flatOrder, array_column($bottomGMs, 'gm_name'));
    foreach ($flatOrder as $pos => $gm) {
        $resultsFlat[$gm][$pos + 1]++;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Draft Lottery Simulation</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; margin-bottom: 20px; }
th, td { border: 1px solid #ccc; padding: 6px 12px; text-align: right; }
th { background-color: #f4f4f4; }
td:first-child, th:first-child { text-align: left; }
.form-bar { margin-bottom: 15px; }
input[type=number] { width: 100px; padding: 5px; font-size: 14px; }
button { padding: 5px 12px; font-size: 14px; cursor: pointer; margin-left: 5px; }
</style>
</head>
<body>

<h2>Draft Lottery Simulation Results (<?= $iterations ?> iterations)</h2>

<form method="post" class="form-bar">
    <label for="iterations"><strong>Iterations:</strong></label>
    <input type="number" id="iterations" name="iterations" value="<?= $iterations ?>" min="1">
    <button type="submit">Run Simulation</button>
    <button type="submit">Refresh</button>
</form>

<h3>Weighted Positions (%)</h3>
<table>
<tr><th>GM</th><?php for ($i=1;$i<=11;$i++) echo "<th>Pos $i</th>"; ?></tr>
<?php
foreach ($resultsWeighted as $gm => $slots) {
    echo "<tr><td>$gm</td>";
    foreach ($slots as $count) {
        echo "<td>" . number_format(($count / $iterations) * 100, 2) . "</td>";
    }
    echo "</tr>";
}
?>
</table>

<h3>Flat Positions (%)</h3>
<table>
<tr><th>GM</th><?php for ($i=1;$i<=11;$i++) echo "<th>Pos $i</th>"; ?></tr>
<?php
foreach ($resultsFlat as $gm => $slots) {
    echo "<tr><td>$gm</td>";
    foreach ($slots as $count) {
        echo "<td>" . number_format(($count / $iterations) * 100, 2) . "</td>";
    }
    echo "</tr>";
}
?>
</table>

</body>
</html>
