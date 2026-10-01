<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/gm_status.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
    <title>
        <?php echo htmlspecialchars($_GET['gm'] ?? ''); ?>'s
        Page
    </title>
</head>

<body>

    <?php
    $gm = $_GET['gm'] ?? '';
    $gm = trim($gm); // optional: trim spaces

    include __DIR__ . '/../db_connections/connection_pdo.php';

    $gm_name = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name;");
    $allowed_gms = $gm_name->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array($gm, $allowed_gms, true)) {
        echo "<h1>This is not the webpage you are looking for.</h1>
          <h1>Now go away.</h1>
         <meta http-equiv = 'refresh' content = '2; url = https://hmpg.net' />";
        exit();
    }

    $money = new NumberFormatter('en', NumberFormatter::CURRENCY);

    // Check if season has started
    // Get protection list date (may be NULL)
    $season_info = $pdo->query("SELECT season_start, protection_list, franchise_due FROM base_numbers")->fetch(PDO::FETCH_ASSOC);

    $season_start = new DateTime($season_info['season_start']);
    $season_start = $season_start->format('Y-m-d');

    $protection_list = $season_info['protection_list'];
    $franchise_due = $season_info['franchise_due'];


    $check = $pdo->prepare("SELECT count(player) as protected_players FROM salaries WHERE gm = :poolee");
    $check->execute(['poolee' => $gm]);
    $protection_done = $check->fetchColumn();

    // Always create today's DateTime
    $today = new DateTime('today');

    // Decide if button should be shown
    $showForm = false;

    if (empty($protection_list)) {
        // If not set in DB, always show the button
        $showForm = true;
    } else {
        // Otherwise, compare to end of protection day
        $protection_list_dt = (new DateTime($protection_list))->setTime(23, 59, 59);

        if ($today <= $protection_list_dt) {
            $showForm = true;
        }
    }

    // Show Franchise Reset Button or Not
    $get_expiry = $pdo->prepare("SELECT franchise_date FROM salaries WHERE franchise IS NOT NULL AND gm = :gm; ");
    $get_expiry->execute(['gm' => $gm]);
    $franchise_expiry = $get_expiry->fetchColumn() ?? '';

    $protectReset = false;
    $current_year = (int) $today->format('Y');
    $expiry_date = new DateTime($franchise_expiry);
    $expiry_year = (int) $expiry_date->format('Y');
    $year_difference = $expiry_year - $current_year;
    $franchise_due_dt = !empty($season_info['franchise_due'])
        ? new DateTime($season_info['franchise_due'])
        : null;

    if (
        $franchise_due_dt !== null &&
        $today <= $franchise_due_dt &&
        $franchise_expiry !== null &&
        $year_difference == 2
    ) {
        $protectReset = true;
    }
    ?>

    <div class="grid-container-top">
        <div class="title">
            <h1><?php echo htmlspecialchars($gm); ?>'s<br>Draft
                Line-Up</h1>
        </div>
        <div class="draft_status">
            <?php

            $get_season_status = $pdo->query("SELECT season_start, season_end FROM base_numbers;");
            $get_season_status->execute();
            $row = $get_season_status->fetch(PDO::FETCH_ASSOC);
            $season_start = $row['season_start'];
            $season_end   = $row['season_end'];
            $today = date('Y-m-d');

            $season_started = $pdo->query("SELECT CURRENT_DATE >= nhl_start_date FROM base_numbers LIMIT 1")->fetchColumn();

            $draft_complete_check = $pdo->query("SELECT 
                                                    (SELECT COUNT(*) FROM salaries WHERE gm IS NOT NULL) >= 
                                                    (SELECT numb_gms * draft_players FROM base_numbers LIMIT 1)
                                                AS draft_done")->fetchColumn();

            if (!$draft_complete_check) {
                include __DIR__ . '/../draft_state/round_npick.php';
            } else {
                include 'gm_info_pts.php';
            }
            ?>
        </div>
    </div>

    <?php
    include __DIR__ . '/../misc_files/nav_bar_links.php';

    $picks = $pdo->prepare("SELECT sal.player, sal.team, sal.current_salary AS current_salary, sal.waiver_bid AS waiver_bid, sal.position, sal.drafted AS drafted, sal.gm,
                               -- Fetch current stats from player_stats
                               COALESCE(SUM(stat.goals),0) AS total_goals,
                               COALESCE(SUM(stat.assists),0) AS total_assists,
                               COALESCE(SUM(stat.wins),0) AS total_wins,
                               COALESCE(SUM(stat.shutouts),0) AS total_shutouts,
                               COALESCE(SUM(stat.otl),0) AS total_otl,
                               -- Subtract stats at pickup if applicable
                               COALESCE(sum(wm_goals.goals_at_pickup),0) AS goals_at_pickup,
                               COALESCE(sum(wm_assists.assists_at_pickup),0) AS assists_at_pickup,
                               COALESCE(sum(wm_wins.wins_at_pickup),0) AS wins_at_pickup,
                               COALESCE(sum(wm_shutouts.shutouts_at_pickup),0) AS shutouts_at_pickup,
                               COALESCE(sum(wm_otl.otl_at_pickup),0) AS otl_at_pickup
                        FROM salaries AS sal
                        LEFT JOIN player_stats AS stat
                            ON LOWER(TRIM(sal.player)) = LOWER(TRIM(stat.player))
                            AND sal.position = stat.position
                            -- AND stat.team = sal.team
                            AND TRIM(stat.team) ILIKE '%' || TRIM(sal.team) || '%'
                            -- Only include stats AFTER pickup if the player was picked up
                            AND stat.game_date > COALESCE(
                                (SELECT move_date 
                                FROM waiver_moves 
                                WHERE player = sal.player 
                                AND move_type = 'pickup' 
                                AND gm = sal.gm 
                                ORDER BY move_date DESC LIMIT 1),
                                '1900-01-01'::date
                            )
                        -- LEFT JOINs to fetch pickup snapshot for subtraction
                        LEFT JOIN (
                            SELECT player, gm, sum(goals) AS goals_at_pickup
                            FROM waiver_moves
                            WHERE move_type='pickup'
                            GROUP BY player, gm
                        ) AS wm_goals
                            ON wm_goals.player = sal.player AND wm_goals.gm = sal.gm
                        LEFT JOIN (
                            SELECT player, gm, sum(assists) AS assists_at_pickup
                            FROM waiver_moves
                            WHERE move_type='pickup'
                            GROUP BY player, gm
                        ) AS wm_assists
                            ON wm_assists.player = sal.player AND wm_assists.gm = sal.gm
                        LEFT JOIN (
                            SELECT player, gm, sum(wins) AS wins_at_pickup
                            FROM waiver_moves
                            WHERE move_type='pickup'
                            GROUP BY player, gm
                        ) AS wm_wins
                            ON wm_wins.player = sal.player AND wm_wins.gm = sal.gm
                        LEFT JOIN (
                            SELECT player, gm, sum(shutouts) AS shutouts_at_pickup
                            FROM waiver_moves
                            WHERE move_type='pickup'
                            GROUP BY player, gm
                        ) AS wm_shutouts
                            ON wm_shutouts.player = sal.player AND wm_shutouts.gm = sal.gm
                        LEFT JOIN (
                            SELECT player, gm, sum(otl) AS otl_at_pickup
                            FROM waiver_moves
                            WHERE move_type='pickup'
                            GROUP BY player, gm
                        ) AS wm_otl
                            ON wm_otl.player = sal.player AND wm_otl.gm = sal.gm
                        WHERE sal.gm = :gm
                        GROUP BY sal.player, sal.team, sal.current_salary, sal.waiver_bid, sal.position, sal.gm, sal.drafted,
                                wm_goals.goals_at_pickup, wm_assists.assists_at_pickup,
                                wm_wins.wins_at_pickup, wm_shutouts.shutouts_at_pickup, wm_otl.otl_at_pickup
                        ORDER BY sal.position, sal.player");
    $picks->execute(['gm' => $gm]);


    // Fetch dropped players (those where a GM retains salary)
    $drops = $pdo->prepare("SELECT 
                            wm.player,
                            wm.team,
                            wm.position,
                            wm.move_type,
                            wm.move_date,
                            wm.gp,
                            wm.goals,
                            wm.assists,
                            wm.wins,
                            wm.shutouts,
                            wm.otl,
                            sal.salary_retained AS gm,
                            sal.drafted AS drafted,
                            sal.current_salary AS current_salary
                        FROM waiver_moves AS wm
                        JOIN salaries AS sal
                            ON LOWER(TRIM(wm.player)) = LOWER(TRIM(sal.player))
                        AND wm.gm = sal.salary_retained
                        AND wm.team = sal.team
                        AND wm.position = sal.position
                        WHERE wm.move_type = 'drop'
                        AND sal.salary_retained = :gm
                        ORDER BY wm.position, wm.player");
    $drops->execute(['gm' => $gm]);


    $picks_left = $pdo->prepare("SELECT count(drafted) AS drafted FROM salaries WHERE gm = :gm");
    $picks_left->execute(['gm' => $gm]);
    $count_picks = $picks_left->fetchColumn();

    $max_check = $pdo->query("SELECT draft_players, max_waivers, max_trades FROM base_numbers;");

    $row_max = $max_check->fetch(PDO::FETCH_ASSOC);
    $max_picks = $row_max['draft_players'];
    $max_waivers = $row_max['max_waivers'];
    $max_trades = $row_max['max_trades'];

    $waiver_info = $pdo->prepare("SELECT count(salary_retained) AS waivers_picked, sum(current_salary) AS retained_salary 
                              FROM salaries WHERE salary_retained = :gm;");
    $waiver_info->execute(['gm' => $gm]);

    $rows = $waiver_info->fetch(PDO::FETCH_ASSOC);
    $waivers_picked = $rows['waivers_picked'];
    $retained_salary = $rows['retained_salary'];


    $in_bank_query = $pdo->prepare("SELECT cap_number - 
                                       (SELECT sum(current_salary) 
                                        FROM salaries 
                                        WHERE gm = :gm AND waiver_bid IS NULL AND franchise IS NULL
                                       ) AS bank_cash FROM base_numbers;");
    $in_bank_query->execute(['gm' => $gm]);
    $in_bank_amount = $in_bank_query->fetchColumn();

    if ($franchise_expiry == false) {
        $franchise_expiry = 'None Selected';
    } else {
        $franchise_expiry = date("F jS, Y", strtotime($franchise_expiry));
    }

    $waiver_amount_query = $pdo->prepare("SELECT cap_number + waiver_number - COALESCE(:salary, 0) -
                                             (SELECT sum(current_salary)
                                                 FROM salaries
                                                 WHERE gm = :gm AND franchise IS NULL AND waiver_bid IS NULL) - 
                                             (SELECT COALESCE(sum(waiver_bid), 0) 
                                                 FROM salaries
                                             WHERE gm = :gm AND waiver_bid IS NOT NULL) AS waiver_cash
                                      FROM base_numbers;");
    $waiver_amount_query->execute(['salary' => $retained_salary, 'gm' => $gm]);
    $waiver_amount = $waiver_amount_query->fetchColumn();

    $fwd_query = $pdo->prepare("SELECT fwds_max - 
                              (SELECT count(*) 
                                FROM salaries 
                                WHERE position = 'F' AND gm = :gm
                              ) AS fwd_to_pick 
                            FROM base_numbers;");
    $fwd_query->execute(['gm' => $gm]);
    $fwds_to_pick = $fwd_query->fetchColumn();

    $def_query = $pdo->prepare("SELECT defs_max - 
                              (SELECT count(*) 
                                FROM salaries 
                                WHERE position = 'D' AND gm = :gm
                              ) AS dmen_to_pick 
                            FROM base_numbers;");
    $def_query->execute(['gm' => $gm]);
    $defs_to_pick = $def_query->fetchColumn();

    $goalies_query = $pdo->prepare("SELECT goalies_max - 
                                  (SELECT count(*) 
                                    FROM salaries 
                                    WHERE position = 'G' AND gm = :gm
                                  ) AS goalies_to_pick 
                                FROM base_numbers;");
    $goalies_query->execute(['gm' => $gm]);
    $goalies_to_pick = $goalies_query->fetchColumn();

    $waivers =  $waivers_picked . " of " . $max_waivers;

    if ($today < $season_start) {
        $defs_to_pick = $fwds_to_pick = $goalies_to_pick = $waivers = '-';
    } else {
        if ($defs_to_pick == 0) {
            $defs_to_pick = '-';
        }
        if ($fwds_to_pick == 0) {
            $fwds_to_pick = '-';
        }
        if ($goalies_to_pick == 0) {
            $goalies_to_pick = '-';
        }
    }

    $trades_made = $pdo->prepare("SELECT trades_made FROM gms WHERE gm_name = :gm");
    $trades_made->execute(['gm' => $gm]);
    $trades_done = $trades_made->fetchColumn();


    $get_protection_time = $pdo->prepare("SELECT team_protected FROM gms WHERE gm_name = :gm");
    $get_protection_time->execute(['gm' => $gm]);
    $protection_time = $get_protection_time->fetchColumn();


    if (!empty($protection_time)) {
        $date = new DateTime($protection_time);
        $date->setTimezone(new DateTimeZone('America/Toronto'));
        $protect_date = $date->format('M. jS, Y') . "\nat " . $date->format('H\hi');
    } else {
        $protect_date = 'Not Submitted';
    }

    $goal_total = 0;
    $assist_total = 0;
    $win_total = 0;
    $shutout_total = 0;
    $otl_total = 0;
    $points_total = 0;

    ?>

    <div class="outer-scroll">
        <div class="grid-container-bottom">
            <div class="left_panels">
                <div class="div_grid_left_top">
                    <table class="remainpicks_info">
                        <tr>
                            <th colspan="2" class="remainpicks_headings">Remaining<br>Draft Picks</th>
                        </tr>
                        <tr>
                            <td>Defence</td>
                            <td><?= htmlspecialchars($defs_to_pick); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Forwards</td>
                            <td><?= htmlspecialchars($fwds_to_pick); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Goalies</td>
                            <td><?= htmlspecialchars($goalies_to_pick); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="div_grid_waivers_middle">
                    <table class="waivers">
                        <tr>
                            <th class="waiver_headings">Successful<br>Waiver Picks</th>
                        </tr>
                        <tr>
                            <td class="info_rows">
                                <?= htmlspecialchars($waivers); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="div_grid_left_trades">
                    <table class="trades">
                        <tr>
                            <th class="trade_headings">Trades Completed</th>
                        </tr>
                        <tr>
                            <td class="info_rows">
                                <?= htmlspecialchars($trades_done . ' of ' . $max_trades); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="div_grid_left_protection">
                    <table class="trades">
                        <tr>
                            <th class="trade_headings">Protection List Submitted</th>
                        </tr>
                        <tr>
                            <td class="info_rows">
                                <?= nl2br(htmlspecialchars($protect_date)); ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="drafted_players">

                <?php
                // ── Helper: calculate stats and points for one row ──────────────────
                function calcRow(array $row, bool $isDropRow = false): array
                {
                    $tg  = (int)($row['total_goals']    ?? $row['goals']    ?? 0);
                    $ta  = (int)($row['total_assists']  ?? $row['assists']  ?? 0);
                    $tw  = (int)($row['total_wins']     ?? $row['wins']     ?? 0);
                    $ts  = (int)($row['total_shutouts'] ?? $row['shutouts'] ?? 0);
                    $to  = (int)($row['total_otl']      ?? $row['otl']      ?? 0);
                    $pg  = (int)($row['goals_at_pickup']    ?? 0);
                    $pa  = (int)($row['assists_at_pickup']  ?? 0);
                    $pw  = (int)($row['wins_at_pickup']     ?? 0);
                    $ps  = (int)($row['shutouts_at_pickup'] ?? 0);
                    $po  = (int)($row['otl_at_pickup']      ?? 0);
                    $goals    = max(0, $tg - $pg);
                    $assists  = max(0, $ta - $pa);
                    $wins     = max(0, $tw - $pw);
                    $shutouts = max(0, $ts - $ps);
                    $otl      = max(0, $to - $po);
                    $pos = strtoupper(trim((string)($row['position'] ?? '')));
                    $gp = $ap = $wp = $sp = $op = 0;
                    if ($pos === 'F') {
                        $gp = $goals * 2;
                        $ap = $assists * 1;
                    } elseif ($pos === 'D') {
                        $gp = $goals * 2;
                        $ap = $assists * 2;
                    } elseif ($pos === 'G') {
                        $wp = $wins * 3;
                        $sp = $shutouts * 3;
                        $op = $otl * 1;
                        $gp = $goals * 5;
                        $ap = $assists * 1;
                    }
                    return compact(
                        'goals',
                        'assists',
                        'wins',
                        'shutouts',
                        'otl',
                        'pos',
                        'gp',
                        'ap',
                        'wp',
                        'sp',
                        'op'
                    ) + ['points' => $gp + $ap + $wp + $sp + $op];
                }

                // ── Separate active picks by position ───────────────────────────────
                $skater_picks = [];
                $goalie_picks = [];
                foreach ($get_fwd_def_picks ?? [] as $r) { // fallback to $picks
                    $pos = strtoupper(trim($r['position'] ?? ''));
                    if ($pos === 'G') {
                        $goalie_picks[] = $r;
                    } else {
                        $skater_picks[] = $r;
                    }
                }
                // $picks is already fetched; split it
                $skater_picks = [];
                $goalie_picks = [];
                $picks->execute(['gm' => $gm]);
                foreach ($picks as $r) {
                    $pos = strtoupper(trim($r['position'] ?? ''));
                    if ($pos === 'G') {
                        $goalie_picks[] = $r;
                    } else {
                        $skater_picks[] = $r;
                    }
                }

                // Sort skaters: D first, then F
                usort($skater_picks, function ($a, $b) {
                    $pa = strtoupper(trim($a['position']));
                    $pb = strtoupper(trim($b['position']));
                    if ($pa === $pb) {
                        return strcmp($a['player'], $b['player']);
                    }
                    return ($pa === 'D') ? -1 : 1;
                });

                // ── Separate dropped players by position ────────────────────────────
                $skater_drops = [];
                $goalie_drops = [];
                $drops->execute(['gm' => $gm]);
                foreach ($drops as $r) {
                    $pos = strtoupper(trim($r['position'] ?? ''));
                    if ($pos === 'G') {
                        $goalie_drops[] = $r;
                    } else {
                        $skater_drops[] = $r;
                    }
                }
                ?>

                <?php
                // ── SKATER TABLE (D + F) ─────────────────────────────────────────────
                $sk_goal_total = $sk_assist_total = $sk_points_total = 0;
                ?>
                <div class="table-scroll">
                    <table class="draft_choices">
                        <tr>
                            <th>Player</th>
                            <th class="draft_choices_columns">Team</th>
                            <th class="draft_choices_columns">Pos</th>
                            <th>Salary</th>
                            <th class="draft_choices_columns">G</th>
                            <th class="draft_choices_columns">A</th>
                            <th class="draft_choices_columns">FPts</th>
                        </tr>

                        <?php foreach ($skater_picks as $row):
                            $isDrop = ($row['waiver_bid'] !== null);
                            $c = calcRow($row);
                            // MODIFIED: Show "Franchise Player" instead of salary for franchise-designated players
                            // MODIFIED: Append "(Waiver Bid)" label to waiver pickup salary
                            $salary = ($row['drafted'] === 'Franchise')
                                ? 'Franchise Player'
                                : ($isDrop
                                    ? $money->formatCurrency($row['waiver_bid'], 'USD') . ' (Waiver Bid)'
                                    : $money->formatCurrency($row['current_salary'], 'USD'));
                            $drafted   = $row['drafted'] ?? '';
                            $rowClass  = ($drafted === 'Franchise') ? 'franchise' : ($isDrop ? 'drops' : 'picks');
                            $waiverClass = $isDrop ? 'waiver_pickup' : '';
                            $playerName = htmlspecialchars($row['player']);
                            $player = ($isDrop && stripos($drafted, 'waiver') !== false)
                                ? $playerName . ' (' . htmlspecialchars($drafted) . ')'
                                : $playerName;
                            $sk_goal_total   += $c['goals'];
                            $sk_assist_total += $c['assists'];
                            $sk_points_total += $c['points'];
                        ?>
                            <tr
                                class="<?= $rowClass ?> <?= $waiverClass ?>">
                                <td><?= $player ?></td>
                                <td><?= htmlspecialchars($row['team']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['position']) ?>
                                </td>
                                <td><?= $salary ?></td>
                                <td><?= $season_started ? ($c['goals'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['assists'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['points'] ?: '') : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($skater_drops as $row):
                            $c = calcRow($row, true);
                            $salary = $money->formatCurrency($row['current_salary'], 'USD');
                            $sk_goal_total   += $c['goals'];
                            $sk_assist_total += $c['assists'];
                            $sk_points_total += $c['points'];
                        ?>
                            <tr class="drops">
                                <td><?= htmlspecialchars($row['player'] . ' (Dropped)') ?>
                                </td>
                                <td><?= htmlspecialchars($row['team']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['position']) ?>
                                </td>
                                <td><?= $salary ?></td>
                                <td><?= $season_started ? ($c['goals'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['assists'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['points'] ?: '') : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr style="font-weight:bold; background:#eaeaea;">
                            <td colspan="4" style="text-align:right;">Totals:</td>
                            <td><?= $season_started ? ($sk_goal_total ?: '') : '' ?>
                            </td>
                            <td><?= $season_started ? ($sk_assist_total ?: '') : '' ?>
                            </td>
                            <td style="color:red;">
                                <?= $season_started ? ($sk_points_total ?: '') : '' ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php
                // ── GOALIE TABLE ─────────────────────────────────────────────────────
                $g_win_total = $g_shutout_total = $g_otl_total = $g_points_total = 0;
                ?>
                <div class="table-scroll">
                    <table class="draft_choices">
                        <tr>
                            <th>Player</th>
                            <th class="draft_choices_columns">Team</th>
                            <th class="draft_choices_columns">Pos</th>
                            <th>Salary</th>
                            <th class="draft_choices_columns">W</th>
                            <th class="draft_choices_columns">SHO</th>
                            <th class="draft_choices_columns">OTL</th>
                            <th class="draft_choices_columns">FPts</th>
                        </tr>

                        <?php foreach ($goalie_picks as $row):
                            $isDrop = ($row['waiver_bid'] !== null);
                            $c = calcRow($row);
                            // MODIFIED: Show "Franchise Player" instead of salary for franchise-designated players
                            // MODIFIED: Append "(Waiver Bid)" label to waiver pickup salary
                            $salary = ($row['drafted'] === 'Franchise')
                                ? 'Franchise Player'
                                : ($isDrop
                                    ? $money->formatCurrency($row['waiver_bid'], 'USD') . ' (Waiver Bid)'
                                    : $money->formatCurrency($row['current_salary'], 'USD'));
                            $drafted   = $row['drafted'] ?? '';
                            $rowClass  = ($drafted === 'Franchise') ? 'franchise' : ($isDrop ? 'drops' : 'picks');
                            $waiverClass = $isDrop ? 'waiver_pickup' : '';
                            $playerName = htmlspecialchars($row['player']);
                            $player = ($isDrop && stripos($drafted, 'waiver') !== false)
                                ? $playerName . ' (' . htmlspecialchars($drafted) . ')'
                                : $playerName;
                            $g_win_total     += $c['wins'];
                            $g_shutout_total += $c['shutouts'];
                            $g_otl_total     += $c['otl'];
                            $g_points_total  += $c['points'];
                        ?>
                            <tr
                                class="<?= $rowClass ?> <?= $waiverClass ?>">
                                <td><?= $player ?></td>
                                <td><?= htmlspecialchars($row['team']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['position']) ?>
                                </td>
                                <td><?= $salary ?></td>
                                <td><?= $season_started ? ($c['wins'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['shutouts'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['otl'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['points'] ?: '') : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($goalie_drops as $row):
                            $c = calcRow($row, true);
                            $salary = $money->formatCurrency($row['current_salary'], 'USD');
                            $g_win_total     += $c['wins'];
                            $g_shutout_total += $c['shutouts'];
                            $g_otl_total     += $c['otl'];
                            $g_points_total  += $c['points'];
                        ?>
                            <tr class="drops">
                                <td><?= htmlspecialchars($row['player'] . ' (Dropped)') ?>
                                </td>
                                <td><?= htmlspecialchars($row['team']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['position']) ?>
                                </td>
                                <td><?= $salary ?></td>
                                <td><?= $season_started ? ($c['wins'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['shutouts'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['otl'] ?: '') : '' ?>
                                </td>
                                <td><?= $season_started ? ($c['points'] ?: '') : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr style="font-weight:bold; background:#eaeaea;">
                            <td colspan="4" style="text-align:right;">Totals:</td>
                            <td><?= $season_started ? ($g_win_total ?: '') : '' ?>
                            </td>
                            <td><?= $season_started ? ($g_shutout_total ?: '') : '' ?>
                            </td>
                            <td><?= $season_started ? ($g_otl_total ?: '') : '' ?>
                            </td>
                            <td style="color:red;">
                                <?= $season_started ? ($g_points_total ?: '') : '' ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if ($gm === $_SESSION['gm_name']): ?>
                    <div class="button_grid-container">
                        <!-- Button for Roster Protection Reset -->
                        <div class="reset_protect">
                            <?php if ($showForm && $protection_done == 10): ?>
                                <form name="display" action="" method="POST">
                                    <div>
                                        <button type="submit" id="buttonSet" name="resetProtection">
                                            Reset Roster<BR>to Pre-Protection
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>


                        <!-- Button for Franchise Player Reset -->
                        <div class="reset_franchise">
                            <?php if ($protectReset): ?>
                                <form name="display" action="" method="POST">
                                    <div>
                                        <button type="submit" id="buttonSet" name="resetFranchise">
                                            Reset Franchise Player
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            $display_bank = '-';
            $display_waiver = '-';
            if ($today < $season_start) {
                if ($in_bank_amount > 0) {
                    $display_bank = "$" . number_format($in_bank_amount);
                }
            } elseif ($count_picks < $max_picks) {
                $display_bank = "$" . number_format($in_bank_amount);
                $display_waiver = '<span style="color:#FF0000;"><strong>Draft Incomplete</strong></span>';
            } else {
                $display_bank = '<span style="color:#FF0000;"><strong>Draft Completed</strong></span>';
                $display_waiver = ($waivers_picked == 2)
                    ? '<span style="color:#FF0000;"><strong>Max Picks Reached</strong></span>'
                    : "$" . number_format($waiver_amount);
            }
            ?>

            <div class="right_panels">
                <div class="draft_money">
                    <table class="money">
                        <thead>
                            <tr>
                                <th class="money_headings">Remaining Draft<br>Day Bank</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="info_rows">
                                    <?= $display_bank; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="waiver_bank">
                    <table class="money">
                        <thead>
                            <tr>
                                <th class="waiver_headings">Remaining<br>Waiver Bank</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="info_rows">
                                    <?= $display_waiver; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="expiry_date">
                    <table class="money">
                        <thead>
                            <tr>
                                <th class="waiver_headings">Franchise<br>Expiry Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="info_rows">
                                    <?= htmlspecialchars($franchise_expiry); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <?php
    if (isset($_POST['resetProtection'])) {
        // 1. Always restore GM
        $reset_roster_gm = $pdo->prepare("UPDATE salaries
                                      SET gm = :poolee
                                      WHERE protection_drop = :poolee");
        $reset_roster_gm->execute(['poolee' => $gm]);

        // 2. Null drafted, but not for Franchise
        $reset_roster_drafted = $pdo->prepare("UPDATE salaries
                                           SET drafted = NULL
                                           WHERE protection_drop = :poolee
                                             AND drafted IS NOT NULL
                                             AND drafted != 'Franchise'");
        $reset_roster_drafted->execute(['poolee' => $gm]);

        // 3. Reset to Protection Drop
        $reset_roster_drafted = $pdo->prepare("UPDATE salaries
                                           SET protection_drop = NULL
                                           WHERE protection_drop = :poolee");
        $reset_roster_drafted->execute(['poolee' => $gm]);

        // 4. Remove Protection Timestamp for GM
        $reset_protectionTime = $pdo->prepare("UPDATE gms
                                           SET team_protected = NULL
                                           WHERE gm_name = :poolee");
        $reset_protectionTime->execute(['poolee' => $gm]);

        echo
        "<script>
        window.location.href='../gm_listings/gm_info.php?gm=$gm';
    </script>";
        exit();
    }

    if (isset($_POST['resetFranchise'])) {
        if ($protection_done == 10) {
            $draft_status = 'Protected';
        }

        $reset_franchise = $pdo->prepare("UPDATE salaries
                                      SET drafted = :draft_status,
                                          franchise = NULL,
                                          franchise_date = NULL
                                      WHERE gm = :poolee
                                        AND drafted = 'Franchise'");
        $reset_franchise->execute(['poolee' => $gm, 'draft_status' => $draft_status ?? '']);

        echo
        "<script>
        window.location.href='../gm_listings/gm_info.php?gm=" . rawurlencode($gm) . "';
    </script>";
        exit();
    }
    ?>
</body>

</html>