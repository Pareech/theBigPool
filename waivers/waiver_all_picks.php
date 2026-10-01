<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display all Waiver Bids</title>
    <link rel='stylesheet' type='text/css' href='../css/waiver_admin.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <?php
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

$season_started = $pdo->query("SELECT season_start FROM base_numbers")->fetchColumn();
$today = new DateTime();
$season_started = new DateTime($season_started);
$post_draft = clone $season_started;
$post_draft->modify('+1 day');

if ($today <= $post_draft) {
    $post_draft_fmt = $post_draft->format("F jS, Y");
    echo "<script>
        alert('The season has not started. Waivers are currently closed.\\n\\n'
            + 'Waivers will be available as of $post_draft_fmt after\\n'
            + 'the draft has been completed.');
        window.location.href='../draft_setup/draft_info.php';
    </script>";
    exit;
}

include __DIR__ . '/waiver_period_check.php';
include __DIR__ . '/popups_for_waivers.php';
?>

    <div class="header">
        <h1>
            <span
                style="color:#32cd32;"><?= htmlspecialchars((string)$waiver_title) ?></span><br>
            Waiver Bids
        </h1>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <?php
$get_waiver_picks = $pdo->prepare("SELECT waiver_option, waiver_pick, waiver_bid, waiver_drop, gm, waiver_bid_time, waiver_period
                                   FROM waiver_draft
                                   WHERE waiver_option IN ('1', '2', '3')
                                     AND waiver_period = :waiver_period
                                   ORDER BY waiver_option, waiver_pick, waiver_bid DESC, waiver_bid_time ASC");
$get_waiver_picks->execute(['waiver_period' => (string)$waiver_period]);
$waiver_picks = $get_waiver_picks->fetchAll(PDO::FETCH_ASSOC);

// Group and identify winners
$grouped_bids = [];
foreach ($waiver_picks as $row) {
    $option = (int)$row['waiver_option'];
    $pick = $row['waiver_pick'];
    $grouped_bids[$option][$pick][] = $row;
}

$won_players = [];
$gm_winners = [];
$winners = [];

foreach ([1, 2, 3] as $option) {
    if (!isset($grouped_bids[$option])) {
        continue;
    }

    foreach ($grouped_bids[$option] as $pick => $bids) {
        if (in_array($pick, $won_players)) {
            continue;
        }

        $valid_bids = array_filter($bids, function ($bid) use ($gm_winners) {
            return !in_array($bid['gm'], $gm_winners);
        });

        if (empty($valid_bids)) {
            continue;
        }

        usort($valid_bids, function ($a, $b) {
            if ($a['waiver_bid'] == $b['waiver_bid']) {
                return strtotime($a['waiver_bid_time']) <=> strtotime($b['waiver_bid_time']);
            }
            return $b['waiver_bid'] <=> $a['waiver_bid'];
        });

        $winning_bid = $valid_bids[0];
        $winners[] = $winning_bid;
        $won_players[] = $pick;
        $gm_winners[] = $winning_bid['gm'];
    }
}
?>

    <div class="outer-scroll">
        <form name="display" action="waiver_results.php" method="POST">
            <div class="grid-container_waiver_results">
                <table class="results_table">
                    <tr>
                        <th>Waiver<br>Choice</th>
                        <th class="waiver_pick-column">Waiver Pick</th>
                        <th>Bid</th>
                        <th class="player_drop-column">Player to Drop</th>
                        <th>GM</th>
                    </tr>

                    <?php
            function getHighlightClass($option)
            {
                return match ((int) $option) {
                    1 => 'highlight-round1',
                    2 => 'highlight-round2',
                    3 => 'highlight-round3',
                    default => '',
                };
            }

foreach ($waiver_picks as $row):
    $is_winner = false;
    $highlight_class = '';

    foreach ($winners as $win) {
        $match = (
            $win['waiver_option'] == $row['waiver_option'] &&
            $win['waiver_pick'] === $row['waiver_pick'] &&
            $win['gm'] === $row['gm'] &&
            $win['waiver_bid'] == $row['waiver_bid']
        );

        if ($match) {
            $is_winner = true;
            $highlight_class = getHighlightClass($row['waiver_option']);
            break;
        }
    }
    ?>
                    <tr
                        class="<?= htmlspecialchars($highlight_class, ENT_QUOTES, 'UTF-8') ?>">
                        <td><?= htmlspecialchars($row['waiver_option'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="waiver_pick-column">
                            <?= htmlspecialchars($row['waiver_pick'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>$<?= number_format((float) $row['waiver_bid'], 0) ?>
                        </td>
                        <td class="player_drop-column">
                            <?= htmlspecialchars($row['waiver_drop'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td><?= htmlspecialchars($row['gm'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <div class="update_rosters">
                    <button type="submit" id="buttonSet" name="">Display Only<br>Winning Bids</button>
                </div>
            </div>
        </form>
    </div>

</body>

</html>