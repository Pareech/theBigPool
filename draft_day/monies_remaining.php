<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgets Remaining</title>
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/monies_remaining.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="grid-container-top">
        <div class="title">
            <h1>Budgets<br>Remaining</h1>
        </div>
        <div class="draft_status">
            <?php
        $money = new NumberFormatter('en', NumberFormatter::CURRENCY);
include __DIR__ . '/../db_connections/connection_pdo.php';
include __DIR__ . '/../draft_state/round_npick.php';
?>
        </div>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <?php
// Get Current Draft Budget Remaining
$draft_budget = $pdo->query("SELECT gm, 
                              (SELECT cap_number 
                               FROM base_numbers) - sum(current_salary
                              ) remaining_draft_budget
                             FROM salaries
                             WHERE gm NOTNULL AND franchise IS NULL
                             GROUP BY gm
                             ORDER BY gm;");

// Get GM Waiver Budget (Post Draft Amount)
$waiver = $pdo->query("SELECT gm, 
                              (
                                (SELECT cap_number + waiver_number 
                                 FROM base_numbers
                                ) - 
                              sum(current_salary) 
                              ) AS waiver_budget
                       FROM salaries
                       WHERE drafted NOTNULL AND franchise IS NULL
                       GROUP BY gm
                       ORDER BY gm;");

// Get Max Players to be Drafted by GM
$max_team_size = $pdo->query("SELECT fwds_max + defs_max + goalies_max
                              FROM base_numbers;")->fetchColumn();

$max_waiver_picks = $pdo->query("SELECT max_waivers 
                                 FROM base_numbers;")->fetchColumn();
?>

    <div class="div_table_grid">
        <div class="table-scroll">
            <table class="budget_remaining">
                <tr class="tr_header_budget_reamining">
                    <th class="two_sided_border"></th>
                    <th>Remaining Draft<br>Day Budget</th>
                    <th>Waiver<br>Budget</th>
                    <th>Remaining<br>Waiver Picks</th>
                </tr>

                <?php
        foreach ($draft_budget as $row) {
            $gm = $row['gm'];

            if ($today < $season_start) {
                $draft_budget = '-';
                $waiver_budget = '-';
                $waivers_left_budget = '-';
                $waivers_left = '-';
            } else {

                // Find if max draft day picks done for GM
                $check_gm_draft_day_picks = $pdo->prepare("SELECT count(gm) 
                                                           FROM salaries WHERE GM = :gm;");
                $check_gm_draft_day_picks->execute(['gm' => $gm]);
                $gm_draft_day_picks = $check_gm_draft_day_picks->fetch();
                $final_draft_count = $gm_draft_day_picks[0];

                // Find Remaining GM Waiver Picks
                $check_gm_remaining_waivers = $pdo->prepare("SELECT count(salary_retained) 
                                                             FROM salaries WHERE salary_retained = :gm");
                $check_gm_remaining_waivers->execute(['gm' => $gm]);
                $waiver_count = $check_gm_remaining_waivers->fetchColumn() ?? 0;

                // Find Waiver Monies Available
                if ($max_team_size == $final_draft_count) {
                    $budget_query = $pdo->prepare("SELECT cap_number + waiver_number - 
                                                    (SELECT sum(current_salary) AS draft_spent
                                                            FROM salaries
                                                            WHERE gm = :gm AND franchise IS NULL AND waiver_bid IS NULL 
                                                            GROUP BY gm) -
                                                        (SELECT sum(retained_money) AS waiver_spent
                                                            FROM 
                                                                (SELECT sum(current_salary) AS retained_money
                                                                FROM salaries 
                                                                WHERE salary_retained = :gm AND gm IS null
                                                            
                                                                UNION
                                                            
                                                                SELECT COALESCE(sum(waiver_bid), 0) AS bid_mmoney
                                                                FROM salaries
                                                                WHERE waiver_bid NOTNULL AND gm = :gm
                                                                ) monies
                                                    ) remaining_budget
                                                   FROM base_numbers;");
                    $budget_query->execute(['gm' => $gm]);
                    $waiver_budget = $budget_query->fetchColumn() ?? 0;

                    // Verify if GM Has Remaining Waiver Picks
                    $waivers_left = $max_waiver_picks - $waiver_count;
                    if ($waiver_budget != 0 && $waivers_left != 0) {
                        $waiver_budget = "$" . number_format($waiver_budget);
                    } else {
                        $waiver_budget = "-";
                        $waivers_left = "-";
                    }
                    $draft_budget = "-";
                } else {
                    // If GM still has not completed Drafting Roster
                    $draft_budget = "$" . number_format($row['remaining_draft_budget']);
                    $waiver_budget = "Draft Incomplete";
                    $waivers_left = "-";
                }
            }
            ?>
                <tr class="tr_data_budget_remaining budgets">
                    <td class="gm_color"><?php echo $gm ?></td>
                    <td class="td_monies"><?php echo $draft_budget ?>
                    </td>
                    <?php
                    if ($waiver_budget == 'Draft Incomplete') {
                        echo "<td colspan=2 class='td_monies'>" . $waiver_budget . "</td>";
                    } else {
                        echo "<td class='td_monies'> " . $waiver_budget . "</td>";
                        echo "<td class='td_monies'> " . $waivers_left . "</td>";
                    }
            ?>
                </tr>
                <?php
        }
?>
            </table>
        </div>
    </div>

</body>

</html>