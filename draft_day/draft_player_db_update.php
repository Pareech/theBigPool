<!DOCTYPE html>

<!-- This script will add a selected player to a GM's roster
     Mark the player as drafted. 
     Updated the Draft Board to display Drafted Player in Place of GM's Name -->

<?php
session_start();

$userLoggedIn      = $_SESSION['gm_name'];
$player            = $_POST['radioChoice'] ?? '';
$_SESSION['player'] = $player;
$position_drafting = $_SESSION['position'];

$player_alert = addslashes($player);

include __DIR__ . '/../db_connections/connection_pdo.php';

// -------------------------------------------------------
// Recalculate who should be drafting right now from the DB
// — never trust $_POST for turn enforcement
// -------------------------------------------------------
$numb_gms    = $pdo->query("SELECT count(gm_name) FROM gms")->fetchColumn();
$count_picks = $pdo->query("SELECT count(gm) AS picks_made FROM salaries WHERE gm IS NOT NULL;")->fetchColumn();
$numb_rds    = $pdo->query("SELECT numb_rds FROM base_numbers")->fetchColumn();

$pick_number  = ($count_picks % $numb_gms == 0) ? 1 : ($count_picks % $numb_gms) + 1;
$round        = (int)($count_picks / $numb_gms) % (int)$numb_rds + 1;
$choose_round = 'round_' . $round;

$picking_now = $pdo->query("SELECT {$choose_round}
                             FROM (SELECT {$choose_round}, row_number() OVER (ORDER BY draft_pos ASC) AS rn
                                   FROM draft_order
                                  ) AS picking
                             WHERE rn = {$pick_number};")->fetchColumn();

$picking_now = $picking_now ?: '';

// -------------------------------------------------------
// Turn check:
// - Any GM can draft when it is their turn
// - Ian can draft on behalf of whoever's turn it is
// - Anyone else who is not the current drafter is blocked
// -------------------------------------------------------
if (strcasecmp($userLoggedIn, $picking_now) !== 0 && strcasecmp($userLoggedIn, 'Ian') !== 0) {
    echo
    "<script>
        alert('It\\'s not your pick, {$userLoggedIn}.\\n{$picking_now} is drafting.');
        window.location.href='../draft_state/draft_order.php';
     </script>";
    exit();
}

// Assign the player to whoever's turn it actually is —
// if Ian is drafting on someone's behalf, the pick still goes to that GM
$gm_drafting = $picking_now;

// Validate if a player to be drafted was selected
if (empty($_POST['radioChoice'])) {
    echo
    "<script>
        alert('You did not select a player to be drafted.');
        window.location.href='../gm_listings/gm_info.php?gm=$userLoggedIn';
     </script>";
    exit();
}

// Get salary and position of player being drafted
$draft_query = $pdo->prepare("SELECT current_salary, position FROM salaries WHERE player = :player;");
$draft_query->execute(['player' => $player]);

foreach ($draft_query as $row) {
    $salary   = $row['current_salary'];
    $position = $row['position'];
}

if ($position == 'F') {
    $count = 'fwds_max';
    $note  = 'Forwards';
} elseif ($position == 'D') {
    $count = 'defs_max';
    $note  = 'Defencemen';
} else {
    $count = 'goalies_max';
    $note  = 'Goalies';
}

// Validate if maximum number of players have been chosen for the player's position
$max_position_query = $pdo->prepare("SELECT $count - 
                                            (SELECT count(*) 
                                             FROM salaries 
                                             WHERE gm = :gm AND position = :position
                                            ) position_drafted
                                    FROM base_numbers;");
$max_position_query->execute(['gm' => $gm_drafting, 'position' => $position]);
$max_position = $max_position_query->fetchColumn();

if ($max_position <= 0) {
    echo
    "<script>
        alert('You have selected the maximum number of $note. Choose a player from another position');
        window.location.href='../gm_listings/gm_info.php?gm=$gm_drafting';
        </script>";
    exit();
} else {

    // Verify Draft Day Budget Remaining
    $in_bank_query = $pdo->prepare("SELECT cap_number - 
                                          (SELECT sum(current_salary) 
                                           FROM salaries
                                           WHERE gm = :gm AND waiver_bid IS NULL AND franchise IS NULL
                                          ) bank_cash
                                    FROM base_numbers;");
    $in_bank_query->execute(['gm' => $gm_drafting]);
    $bank = $in_bank_query->fetchColumn();

    $affordable = $bank - $salary;
    $money = new NumberFormatter('en', NumberFormatter::CURRENCY);
    $bank  = $money->formatCurrency($bank, "USD");

    // Verify if GM can afford the player being drafted
    if ($affordable < 0) {
        $affordable = $money->formatCurrency(abs($affordable), "USD");
        $salary     = $money->formatCurrency($salary, "USD");
        echo
        "<script>
            alert('You have $bank remaining in the bank.\\n\\n'
                 + '$player_alert has a salary of $salary.\\n'
                 + 'This will put you $affordable over budget.\\n\\n'
                 + 'This transaction is refused.')
            window.location.href='draft_player.php?position=$position_drafting';
        </script>";
        exit();
    } else {

        $obtained  = 'Round ' . $round . ', Pick ' . $pick_number;
        $affordable = $money->formatCurrency($affordable, "USD");

        // Remove previous latest player marker and assign drafted player to GM
        $pdo->query("UPDATE salaries SET latest_pick = NULL WHERE latest_pick IS NOT NULL;");

        $update = $pdo->prepare("UPDATE salaries 
                                 SET gm = :gm,
                                     drafted = :obtained,
                                     latest_pick = 'x'
                                 WHERE player = :player;");
        $update->execute(['gm' => $gm_drafting, 'obtained' => $obtained, 'player' => $player]);

        // Update Draft Board — replace GM's name with the drafted player
        $updated_rd = $player . "<br>(" . $gm_drafting . ")";

        $update_draft_board = "UPDATE draft_order
                               SET $choose_round = :updated_rd
                               FROM (SELECT draft_pos, $choose_round, row_number() over(order by draft_pos ASC) AS rn
                                     FROM draft_order ORDER BY draft_pos LIMIT 1
                                    ) picking
                               WHERE draft_order.draft_pos = :pick_number;";
        $pdo->prepare($update_draft_board)->execute(['updated_rd' => $updated_rd, 'pick_number' => $pick_number]);

        echo
        "<script>
            alert('$player_alert was added to $gm_drafting\\'s roster.\\n'
                 + 'Draft day budget remaining: $affordable.');
            window.location.href='../gm_listings/gm_info.php?gm=$gm_drafting';
        </script>";
    }
}
?>