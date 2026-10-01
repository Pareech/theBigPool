<div class="goalies_otl_daily ">
    <table class="daily_goal_standings">
        <td class="table_headers" colspan="6">Overtime Losses + Shootout Losses</td>
        <tr>
            <th class="gm_standings_columns">Rank</th>
            <th class="gm_standings_columns">RAW Pts</th>
            <th class="gm_standings_columns">+/-</th>
            <th class="gm_standings_columns">GM</th>
            <th class="gm_standings_columns">OL+ShL</th>
            <th class="gm_standings_columns">+/-</th>
        </tr>

        <?php
        $rank_spot = 0;

        /* -----------------------------
            Index goalies by GM
        ------------------------------ */
        $goalie_by_gm = [];
        foreach ($get_goalie_points as $row) {
            $goalie_by_gm[$row['gm_name']] = $row;
        }

        /* -----------------------------
            Build sortable array
        ------------------------------ */
        $goalie_stats = [];

        foreach ($goalie_by_gm as $gm_name => $goalie_row) {

            $gm_position = htmlspecialchars($gm_name);

            // Previous snapshot
            $get_goalie_change = $pdo->prepare("SELECT goalie_otl FROM daily_positional_totals WHERE gm = :gm");
            $get_goalie_change->execute(['gm' => $gm_name]);
            $goalie_change = $get_goalie_change->fetch(PDO::FETCH_ASSOC);

            $goalie_prev_otl = $goalie_change['goalie_otl'] ?? 0;

            // Current totals
            $goalie_otl = $goalie_row['goalie_total_otl'] ?? 0;

            // --- Calculations ---
            $total_goalie_otl = $goalie_otl;                    // Column 5
            $total_goalie_pts = $total_goalie_otl * 1;          // Column 2
            $goalie_otl_change = $total_goalie_otl - $goalie_prev_otl; // Column 6
            $goalie_change_pts = $goalie_otl_change * 1;        // Column 3

            // Add to sortable list
            $goalie_stats[] = [
                'gm_name'           => $gm_name,
                'gm_position'       => $gm_position,
                'total_goalie_otl'  => $total_goalie_otl,
                'total_goalie_pts'  => $total_goalie_pts,
                'goalie_otl_change' => $goalie_otl_change,
                'goalie_change_pts' => $goalie_change_pts
            ];
        }

        /* -----------------------------
            SORTING ORDER:
            1) total_goalie_pts DESC
            2) total_goalie_otl DESC
            3) goalie_otl_change DESC
        ------------------------------ */
        usort($goalie_stats, function ($a, $b) {

            if ($b['total_goalie_pts'] !== $a['total_goalie_pts']) {
                return $b['total_goalie_pts'] <=> $a['total_goalie_pts'];
            }

            if ($b['total_goalie_otl'] !== $a['total_goalie_otl']) {
                return $b['total_goalie_otl'] <=> $a['total_goalie_otl'];
            }

            return $b['goalie_otl_change'] <=> $a['goalie_otl_change'];
        });

        ?>

        <?php foreach ($goalie_stats as $row): ?>
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
                <td><?= $row['total_goalie_pts'] ?></td>
                <td><?= $row['goalie_change_pts'] ?></td>
                <td>
                    <a style="color: #0000FF;" href="../gm_listings/gm_info.php?gm=<?= urlencode($gm_position) ?>">
                        <?= $gm_position ?>
                    </a>
                </td>
                <td><?= $row['total_goalie_otl'] ?></td>
                <td><?= $row['goalie_otl_change'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>