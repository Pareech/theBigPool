<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

include __DIR__ . '/../db_connections/connection_pdo.php';
$pdo->exec("SET TIME ZONE 'America/Toronto'");

include __DIR__ . '/../classes/logging_functions.php';


// Check if season has started
$season_started = $pdo->query("SELECT season_start FROM base_numbers")->fetchColumn();

$today = new DateTime();
$today_str = $pdo->query("SELECT CURRENT_DATE")->fetchColumn();
$season_started = new DateTime($season_started);
$post_draft = clone $season_started;
$post_draft->modify('+1 day');

if ($today <= $post_draft) {
    exit;
    // End Check if Season has started
} else {
    include __DIR__ . '/find_winners.php';

    // Determine the effective waiver transaction date (day after most recent waiver close date)
    $waiver_date_stmt = $pdo->query("SELECT (GREATEST(
                                             CASE WHEN waiver1 <= CURRENT_DATE THEN waiver1 ELSE NULL END,
                                             CASE WHEN waiver2 <= CURRENT_DATE THEN waiver2 ELSE NULL END,
                                             CASE WHEN waiver3 <= CURRENT_DATE THEN waiver3 ELSE NULL END
                                        ) + INTERVAL '1 day')::date AS waiver_effective_date
                                 FROM base_numbers;");
    $waiver_effective_date = $waiver_date_stmt->fetchColumn();


    if ($today_str != $waiver_effective_date) {
        log_waiver_update("Waivers not updated");
        exit();
    } else {
        // --- Step 1: prepare statements to fetch salary, fetch player_stats, and insert waiver_moves ---
        $get_salary = $pdo->prepare("SELECT player, team, position
                                 FROM salaries
                                 WHERE player = :player
                                 LIMIT 1");

        $get_player_stats = $pdo->prepare("SELECT gp, goals, assists, wins, shutouts, otl
                                       FROM player_stats
                                       WHERE player = :player
                                         AND team = :team
                                         AND position = :position
                                       LIMIT 1");

        $insert_waiver_move = $pdo->prepare("INSERT INTO waiver_moves
                                             (player, gm, position, team, gp, goals, assists, wins, shutouts, otl, move_type, move_date)
                                         VALUES
                                             (:player, :gm, :position, :team, :gp, :goals, :assists, :wins, :shutouts, :otl, :move_type, :move_date)");


        /**
         * Record a waiver move (drop or pickup) into waiver_moves table.
         */
        function recordWaiverMove($get_salary, $get_player_stats, $insert_waiver_move, $player, $gm, $move_type, $move_date)
        {
            // Get salary info (team + position)
            $get_salary->execute(['player' => $player]);
            $salary_info = $get_salary->fetch(PDO::FETCH_ASSOC);

            if (!$salary_info) {
                // No salary record found — skip
                return;
            }

            // Get player stats for that player/team/position
            $get_player_stats->execute([
                'player'   => $salary_info['player'],
                'team'     => $salary_info['team'],
                'position' => $salary_info['position']
            ]);

            $stats = $get_player_stats->fetch(PDO::FETCH_ASSOC) ?: [
                'gp'        => 0,
                'goals'     => 0,
                'assists'   => 0,
                'wins'      => 0,
                'shutouts'  => 0,
                'otl'       => 0
            ];

            // Insert into waiver_moves
            $insert_waiver_move->execute([
                'player'    => $salary_info['player'],
                'gm'        => $gm,
                'position'  => $salary_info['position'],
                'team'      => $salary_info['team'],
                'gp'        => $stats['gp'],
                'goals'     => $stats['goals'],
                'assists'   => $stats['assists'],
                'wins'      => $stats['wins'],
                'shutouts'  => $stats['shutouts'],
                'otl'       => $stats['otl'],
                'move_type' => $move_type,
                'move_date' => $move_date
            ]);
        }


        foreach ($waiver_winners as $row) {
            if ($row['waiver_period'] != $waiver_period) {
                continue;
            }

            // Drop: remove from GM and set drop date
            $retained_salary = $pdo->prepare("UPDATE salaries
                                          SET gm = NULL,
                                              drafted = NULL,
                                              picked_up_date = NULL,
                                              salary_retained = :gm
                                          WHERE player = :waiver_drop;");
            $retained_salary->execute([
                'gm'                    => $row['gm'],
                'waiver_drop'           => $row['waiver_drop'],
            ]);

            // --- Record DROP in waiver_moves ---
            recordWaiverMove($get_salary, $get_player_stats, $insert_waiver_move, $row['waiver_drop'], $row['gm'], 'drop', $waiver_effective_date);


            // Pickup: assign to GM and set pickup date
            $update_rosters = $pdo->prepare("UPDATE salaries
                                             SET gm = :gm,
                                                 waiver_bid = :waiver_bid,
                                                 drafted = :drafted,
                                                 picked_up_date = :waiver_effective_date
                                             WHERE player = :waiver_pick;");
            $update_rosters->execute([
                'gm'                    => $row['gm'],
                'waiver_bid'            => $row['waiver_bid'],
                'waiver_pick'           => $row['waiver_pick'],
                'drafted'               => $drafted_info,
                'waiver_effective_date' => $waiver_effective_date
            ]);

            // --- Record PICKUP in waiver_moves ---
            recordWaiverMove($get_salary, $get_player_stats, $insert_waiver_move, $row['waiver_pick'], $row['gm'], 'pickup', $waiver_effective_date);
        }


        // 3️⃣ Record the system-wide timestamp for when waivers were processed
        $pdo->prepare("UPDATE base_numbers SET waivers_updated = now()")->execute();

        include __DIR__ . '/../misc_files/last_db_update.php';

        log_waiver_update("Success: Waivers Updated for $drafted_info on $waiver_effective_date");
        exit();
    }
}
