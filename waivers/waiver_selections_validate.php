<?php
include __DIR__ . '/../misc_files/auth_check.php';
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../misc_files/pre_draft_check.php';

$all_good = true;
$failed_waivers = [];
$waiver_data = [];

$waiver_count = 1;
$gm = ucfirst(strtolower($_SESSION['gm_name']));
$waiver_period = $_POST['waiver_period'] ?? null;

while ($waiver_count <= 3) {

    $waiver_number = $_POST['waiver_number' . $waiver_count] ?? null;
    $waiver_pick   = $_POST['waiver' . $waiver_count] ?? null;
    $waiver_drop   = $_POST['player_drop' . $waiver_count] ?? null;
    $bid           = $_POST['bid' . $waiver_count] ?? 0;

    if ($waiver_pick && $waiver_drop) {

        // Get and sanitize bid as integer
        $bid = $_POST['bid' . $waiver_count] ?? '0';
        $bid = preg_replace('/[^0-9]/', '', $bid);   // remove $ or commas
        $bid = (int)$bid;

        // Check salary of player being picked  
        $salary_stmt = $pdo->prepare("SELECT current_salary FROM salaries WHERE player = :player LIMIT 1");
        $salary_stmt->execute(['player' => $waiver_pick]);
        $salary = $salary_stmt->fetchColumn();

        if ($salary === false) {
            $failed_waivers[] = "Waiver $waiver_count: Unable to find salary for $waiver_pick";
            $all_good = false;
            $waiver_count++;
            continue;
        }

        // Bid must be >= player's salary
        $salary_stmt = $pdo->prepare("SELECT current_salary FROM salaries WHERE player = :player LIMIT 1");
        $salary_stmt->execute(['player' => $waiver_pick]);
        $salary = (int)$salary_stmt->fetchColumn();

        if ($bid < $salary) {
            $money = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
            $salary_formatted = $money->formatCurrency($salary, 'USD');
            $bid_formatted = $money->formatCurrency($bid, 'USD');
            $bid_field_id = "bid" . $waiver_count;

            echo
            "<script>
                alert('The minimum bid for $waiver_pick is $salary_formatted. Your bid of $bid_formatted is too low.');
                sessionStorage.setItem('clearBidField', '$bid_field_id');
                window.history.back();
            </script>";
            exit();
        }

        // Check GM's waiver cash
        $waiver_query = $pdo->prepare("SELECT cap_number + waiver_number 
                                            - (SELECT COALESCE(SUM(current_salary),0)
                                                FROM salaries
                                                WHERE gm = :gm AND franchise IS NULL AND waiver_bid IS NULL)
                                            - (SELECT COALESCE(SUM(waiver_bid),0)
                                                FROM salaries
                                                WHERE gm = :gm)
                                        AS waiver_cash
                                        FROM base_numbers
                                        LIMIT 1");
        $waiver_query->execute(['gm' => $gm]);
        $waiver = (int)$waiver_query->fetchColumn();

        $affordable = $waiver - (int)$bid;

        if ($affordable < 0) {
            $money = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
            $waiver_amount = $money->formatCurrency($waiver, 'USD');
            $bid_formatted = $money->formatCurrency($bid, 'USD');
            $over_formatted = $money->formatCurrency(abs($affordable), 'USD');
            $bid_field_id = "bid" . $waiver_count;

            echo
            "<script>
                alert('Your waiver bank is $waiver_amount and your bid of $bid_formatted is more than you can afford for $waiver_pick, as it will put you $over_formatted over budget. This transaction is refused.');
                sessionStorage.setItem('clearBidField', '$bid_field_id');
                window.history.back();
            </script>";
            exit();
        }

        // Validate positions
        $result = validateWaiverPositions($pdo, $waiver_number, $waiver_pick, $waiver_drop);
        if ($result !== true) {
            $all_good = false;
            $failed_waivers[] = "Waiver Choice #$waiver_count: {$result['pick']} ({$result['pick_pos']}) vs {$result['drop']} ({$result['drop_pos']})";
        } else {
            $waiver_data[] = [
                'option' => $waiver_number,
                'pick'   => $waiver_pick,
                'drop'   => $waiver_drop,
                'bid'    => $bid,
                'gm'     => $gm
            ];
        }
    }

    $waiver_count++;
}

// If all good, insert into database
if ($all_good) {
    foreach ($waiver_data as $waiver) {
        // insertWaiverPick($pdo, $waiver['option'], $waiver['pick'], $waiver['drop'], $waiver['bid'], $waiver['gm']);
        insertWaiverPick($pdo, $waiver['option'], $waiver['pick'], $waiver['drop'], $waiver['bid'], $waiver['gm'], $waiver_period);
    }

    $_SESSION['gm_picks'] = $gm;

    echo
    "<script>
        alert('No issues detected with your selections.');
        window.location.href='waiver_gm_picks.php';
    </script>";
    exit();
} else {
    $error_message = "Waiver selections have different positions:\n";
    foreach ($failed_waivers as $error) {
        $error_message .= $error . "\n";
    }
    $js_safe_message = json_encode($error_message);
    echo
    "<script>
        alert($js_safe_message);
        window.history.back();
    </script>";
    exit();
}

// --- FUNCTIONS ---

function validateWaiverPositions($pdo, $waiver_number, $waiver_pick, $waiver_drop)
{
    $stmt = $pdo->prepare("SELECT player, position
                           FROM salaries
                           WHERE player IN (:pick, :drop)");
    $stmt->execute([
        'pick' => $waiver_pick,
        'drop' => $waiver_drop
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $positions = [];
    foreach ($results as $row) {
        $positions[$row['player']] = $row['position'];
    }

    if (!isset($positions[$waiver_pick]) || !isset($positions[$waiver_drop])) {
        return [
            'pick' => $waiver_pick,
            'pick_pos' => $positions[$waiver_pick] ?? 'unknown',
            'drop' => $waiver_drop,
            'drop_pos' => $positions[$waiver_drop] ?? 'unknown'
        ];
    }

    if ($positions[$waiver_pick] === $positions[$waiver_drop]) {
        return true;
    }

    return [
        'pick' => $waiver_pick,
        'pick_pos' => $positions[$waiver_pick],
        'drop' => $waiver_drop,
        'drop_pos' => $positions[$waiver_drop]
    ];
}

function insertWaiverPick($pdo, $waiver_option, $waiver_pick, $waiver_drop, $bid, $gm, $waiver_period)
{
    $stmt = $pdo->prepare("INSERT INTO waiver_draft (waiver_option, waiver_pick, waiver_drop, waiver_bid, gm, waiver_bid_time, waiver_period)
                           VALUES (:option, :pick, :drop, :bid, :gm, NOW(), :waiver_period)");
    $stmt->execute([
        ':option'        => $waiver_option,
        ':pick'          => $waiver_pick,
        ':drop'          => $waiver_drop,
        ':bid'           => $bid,
        ':gm'            => $gm,
        ':waiver_period' => $waiver_period
    ]);
}
