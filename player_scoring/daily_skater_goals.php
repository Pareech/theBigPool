<div class="skaters_goals_daily">
    <table class="daily_goal_standings">
        <td class="table_headers" colspan="6">Skater Goals</td>
        <tr>
            <th class="gm_standings_columns">Rank</th>
            <th class="gm_standings_columns">RAW Pts</th>
            <th class="gm_standings_columns">+/-</th>
            <th class="gm_standings_columns">GM</th>
            <th class="gm_standings_columns">Goals</th>
            <th class="gm_standings_columns">+/-</th>
        </tr>

        <?php
        $rank_spot = 0;

        /* -----------------------------
            Index forwards by GM
        ------------------------------ */
        $fwd_by_gm = [];
        foreach ($get_fwd_points as $row) {
            $fwd_by_gm[$row['gm_name']] = $row;
        }

        /* -----------------------------
            Index defense by GM
        ------------------------------ */
        $def_by_gm = [];
        foreach ($get_def_points as $row) {
            $def_by_gm[$row['gm_name']] = $row;
        }

        /* -----------------------------
             Build sortable array
        ------------------------------ */
        $goal_stats = [];

        foreach ($fwd_by_gm as $gm_name => $fwd_row) {

            $gm_position = htmlspecialchars($gm_name);

            // Defense row may be missing
            $def_row = $def_by_gm[$gm_name] ?? ['def_goals' => 0, 'def_assists' => 0];

            // Previous snapshot
            $get_skater_change = $pdo->prepare("SELECT fwd_goals, def_goals FROM daily_positional_totals WHERE gm = :gm");
            $get_skater_change->execute(['gm' => $gm_name]);
            $skater_change = $get_skater_change->fetch(PDO::FETCH_ASSOC);

            $fwd_prev_goals = $skater_change['fwd_goals'] ?? 0;
            $def_prev_goals = $skater_change['def_goals'] ?? 0;

            // Current totals
            $fwd_goals = $fwd_row['fwd_total_goals'] ?? 0;
            $def_goals = $def_row['def_total_goals'] ?? 0;

            // --- Calculations ---
            $total_goals = $fwd_goals + $def_goals;          // Column 5
            $total_goal_pts = $total_goals * 2;              // Column 2

            $prev_total_goals = $fwd_prev_goals + $def_prev_goals;
            $goal_change = $total_goals - $prev_total_goals; // Column 6
            $goal_change_pts = $goal_change * 2;             // Column 3

            // Push into array for sorting
            $goal_stats[] = [
                'gm_name'          => $gm_name,
                'gm_position'      => $gm_position,
                'total_goals'      => $total_goals,
                'total_goal_pts'   => $total_goal_pts,
                'goal_change'      => $goal_change,
                'goal_change_pts'  => $goal_change_pts
            ];
        }

        /* -----------------------------
            SORTING ORDER:
            1) total_goal_pts DESC
            2) total_goals DESC
            3) goal_change DESC
        ------------------------------ */
        usort($goal_stats, function ($a, $b) {

            // First: sort by points
            if ($b['total_goal_pts'] !== $a['total_goal_pts']) {
                return $b['total_goal_pts'] <=> $a['total_goal_pts'];
            }

            // Second: total goals
            if ($b['total_goals'] !== $a['total_goals']) {
                return $b['total_goals'] <=> $a['total_goals'];
            }

            // Third: change
            return $b['goal_change'] <=> $a['goal_change'];
        });
        ?>

        <?php foreach ($goal_stats as $row): ?>
            <?php
            $rank_spot++;
            $gm_name     = $row['gm_name'];
            $gm_position = $row['gm_position'];

            $row_class = (strcasecmp($gm, $gm_name) === 0)
                ? 'tr_stats highlight_gm'
                : 'tr_stats';
            ?>
            <tr class="<?= $row_class ?>">
                <td><?= $rank_spot ?></td>
                <td><?= $row['total_goal_pts'] ?></td>
                <td><?= $row['goal_change_pts'] ?></td>
                <td>
                    <a style="color: #0000FF;" href="../gm_listings/gm_info.php?gm=<?= urlencode($gm_position) ?>">
                        <?= $gm_position ?>
                    </a>
                </td>
                <td><?= $row['total_goals'] ?></td>
                <td><?= $row['goal_change'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>