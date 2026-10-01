<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draft Lottery Integrity</title>
    <link rel='stylesheet' type='text/css' href='../css/draft_lottery.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="header">
        <h1 style="color:#D76E00">Validate Lottery<br>Integrity</h1>
    </div>

    <?php
include __DIR__ . '/../db_connections/connection_pdo.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include __DIR__ . '/../misc_files/nav_bar_links.php';

$iterations = isset($_POST['iterations']) ? max(1, intval($_POST['iterations'])) : 1000;

$gmData = $pdo->query("
    SELECT gm_name, original_order, draft_weight
    FROM draft_lotto
    WHERE original_order BETWEEN 1 AND 8
    ORDER BY original_order
")->fetchAll(PDO::FETCH_ASSOC);

$gmList = array_column($gmData, 'gm_name');

$results = [];
foreach ($gmList as $gm) {
    $results[$gm] = array_fill(1, 8, 0);
}

function weighted_draw(array $weights): string
{
    $total = array_sum($weights);
    if ($total <= 0) {
        throw new RuntimeException("Total weight must be positive.");
    }
    $roll = random_int(1, $total);
    $cumulative = 0;
    foreach ($weights as $gm => $w) {
        $cumulative += $w;
        if ($roll <= $cumulative) {
            return $gm;
        }
    }
    return array_key_last($weights);
}

function renormalize_weights(array $weights, int $target = 1000): array
{
    $sum = array_sum($weights);
    if ($sum <= 0) {
        return $weights;
    }
    $scaled = [];
    $accum = 0;
    foreach ($weights as $gm => $w) {
        $val = ($w / $sum) * $target;
        $rounded = (int)round($val);
        $scaled[$gm] = $rounded;
        $accum += $rounded;
    }
    $drift = $target - $accum;
    if ($drift !== 0) {
        $fractions = [];
        foreach ($weights as $gm => $w) {
            $fractions[$gm] = ($w / $sum) * $target - $scaled[$gm];
        }
        uasort($fractions, fn ($a, $b) => $drift > 0 ? $b <=> $a : $a <=> $b);
        foreach (array_keys($fractions) as $gm) {
            if ($drift === 0) {
                break;
            }
            if ($drift < 0 && $scaled[$gm] <= 1) {
                continue;
            }
            $scaled[$gm] += $drift > 0 ? 1 : -1;
            $drift += $drift > 0 ? -1 : 1;
        }
    }
    return $scaled;
}

for ($i = 0; $i < $iterations; $i++) {
    $availableGMs = $gmList;
    $availableWeights = [];
    foreach ($gmData as $row) {
        $availableWeights[$row['gm_name']] = (int)$row['draft_weight'];
    }
    for ($slot = 1; $slot <= 8; $slot++) {
        $pickedGM = weighted_draw($availableWeights);
        $results[$pickedGM][$slot]++;
        unset($availableWeights[$pickedGM]);
        $availableGMs = array_values(array_diff($availableGMs, [$pickedGM]));
        if (!empty($availableWeights)) {
            $availableWeights = renormalize_weights($availableWeights, array_sum($availableWeights));
        }
    }
}

$totalDraftWeight = array_sum(array_column($gmData, 'draft_weight'));
?>

    <div class="sim-wrapper">

        <!-- Iterations form -->
        <form method="post" class="sim-form">
            <div>
                <label for="iterations"><strong>Iterations:</strong></label>
                <input type="number" id="iterations" name="iterations"
                    value="<?= $iterations ?>" min="1">
            </div>
            <div class="sim-buttons">
                <button type="submit">Run Simulation</button>
                <button type="submit">Refresh</button>
            </div>
        </form>

        <div class="sim-tables">

            <!-- LEFT: Original Order & Odds -->
            <div class="table-scroll">
                <table>
                    <tr>
                        <th>GM Name</th>
                        <th>Original Order</th>
                        <th>Draft Odds (%)</th>
                    </tr>
                    <?php foreach ($gmData as $row): ?>
                    <tr>
                        <td class="td-left">
                            <?= htmlspecialchars($row['gm_name']) ?>
                        </td>
                        <td><?= (int)$row['original_order'] ?>
                        </td>
                        <td><?= number_format(($row['draft_weight'] / $totalDraftWeight) * 100, 2) ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- RIGHT: Simulation Results -->
            <div class="table-scroll">
                <h3 class="sim-title">Iterations Run:
                    <?= $iterations ?></h3>
                <table>
                    <tr>
                        <th>GM Name</th>
                        <?php for ($slot = 1; $slot <= 8; $slot++): ?>
                        <th>Pos <?= $slot ?></th>
                        <?php endfor; ?>
                    </tr>
                    <?php foreach ($results as $gm => $slots): ?>
                    <tr>
                        <td class="td-left">
                            <?= htmlspecialchars($gm) ?></td>
                        <?php foreach ($slots as $count): ?>
                        <td><?= $count ?>
                            (<?= number_format($count / $iterations * 100, 1) ?>%)
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

        </div>
    </div>

</body>

</html>