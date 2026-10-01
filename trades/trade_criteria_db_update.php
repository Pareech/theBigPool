<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Perform Agreed Upon Trade</title>
</head>

<body>

    <?php
    session_start();
    include __DIR__ . '/../db_connections/connection_pdo.php';

    $gm1 = trim($_SESSION['gm1']);
    $gm2 = trim($_SESSION['gm2']);

    /**
     * Update player ownership and return a map of traded players per GM.
     */
    function processPlayerTrades(PDO $pdo, array $players, string $gm1, string $gm2): array
    {
        $playerMap = [$gm1 => [], $gm2 => []];
        $gmLookup = $pdo->prepare("SELECT gm FROM salaries WHERE player = :player");
        $updatePlayer = $pdo->prepare("UPDATE salaries SET gm = :toGM, drafted = 'Via Trade' WHERE player = :player");

        foreach ($players as $player) {
            $player = trim($player);
            $gmLookup->execute(['player' => $player]);
            $fromGM = $gmLookup->fetchColumn();
            $toGM = ($fromGM === $gm1) ? $gm2 : $gm1;

            $updatePlayer->execute(['toGM' => $toGM, 'player' => $player]);
            $playerMap[$fromGM][] = $player;
        }

        return $playerMap;
    }

    /**
     * Update draft picks and return a map of traded picks per GM.
     */
    function processRoundTrades(PDO $pdo, array $rounds, array $gmOwners, string $gm1, string $gm2): array
    {
        $roundMap = [$gm1 => [], $gm2 => []];

        foreach ($rounds as $i => $roundNum) {
            $roundNum = trim($roundNum);
            if ($roundNum === '') continue;

            $fromGM = trim($gmOwners[$i]);
            $toGM = ($fromGM === $gm1) ? $gm2 : $gm1;
            $roundField = 'round_' . (int)$roundNum;

            $updatePick = $pdo->prepare("UPDATE draft_order SET $roundField = :toGM WHERE $roundField = :fromGM");
            $updatePick->execute(['toGM' => $toGM, 'fromGM' => $fromGM]);

            $roundMap[$fromGM][] = "Round $roundNum pick";
        }

        return $roundMap;
    }

    /**
     * Build a grammatically correct asset list (players + picks).
     */
    function formatAssets(array $players, array $picks): string
    {
        $assets = array_merge($players, $picks);
        if (empty($assets)) return 'nothing';

        $last = array_pop($assets);
        return $assets ? implode(', ', $assets) . ' and ' . $last : $last;
    }

    /**
     * Insert trade summary to the log and increment trade counts.
     */
    function recordTrade(PDO $pdo, string $gm1, string $gm2, string $trade_info): void
    {
        $tradeNum = $pdo->query("SELECT COUNT(*) FROM trades_completed")->fetchColumn() + 1;

        $log = $pdo->prepare("INSERT INTO trades_completed (trade_numb, trade_done) VALUES (:num, :info)");
        $log->execute(['num' => $tradeNum, 'info' => $trade_info]);

        $updateCount = $pdo->prepare("UPDATE gms 
                                      SET trades_made = trades_made + 1 
                                      WHERE gm_name IN (:gm1, :gm2);");
        $updateCount->execute(['gm1' => $gm1, 'gm2' => $gm2]);
    }

    // === Run Trade Logic ===
    $playerMap = !empty($_POST['player_chosen'])
        ? processPlayerTrades($pdo, $_POST['player_chosen'], $gm1, $gm2)
        : [$gm1 => [], $gm2 => []];

    $roundMap = (!empty($_POST['round']) && !empty($_POST['gm']))
        ? processRoundTrades($pdo, $_POST['round'], $_POST['gm'], $gm1, $gm2)
        : [$gm1 => [], $gm2 => []];

    $gm1_assets = formatAssets($playerMap[$gm1], $roundMap[$gm1]);
    $gm2_assets = formatAssets($playerMap[$gm2], $roundMap[$gm2]);

    $trade_info =
        "<div class='trade-line'><strong>{$gm1}</strong> trades <em>{$gm1_assets}</em></div>
        <div class='trade-line'>to</div>
        <div class='trade-line'><strong>{$gm2}</strong> for <em>{$gm2_assets}</em></div>
        <div class='trade-spacer'></div>";

    recordTrade($pdo, $gm1, $gm2, $trade_info);

    // Record DB update timestamp
    include __DIR__ . '/../misc_files/last_db_update.php';

    echo "<script>window.location.href = 'trades_completed.php';</script>";
    ?>

</body>

</html>