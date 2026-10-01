<div class="goalies_wins_daily">
    <table class="daily_goal_standings">
        <td class="table_headers" colspan="6">Wins</td>
        <tr>
            <th class="gm_standings_columns">Rank</th>
            <th class="gm_standings_columns">RAW Pts</th>
            <th class="gm_standings_columns">+/-</th>
            <th class="gm_standings_columns">GM</th>
            <th class="gm_standings_columns">Wins</th>
            <th class="gm_standings_columns">+/-</th>
        </tr>
        <?php
        $rank_spot = 0;

        // Original indexing
        $goalie_by_gm = [];
        foreach ($get_goalie_points as $row) {
            $goalie_by_gm[$row['gm_name']] = $row;
        }

        // --------------------------------------------
        // Preprocess: compute all goalie stats first
        // --------------------------------------------
        foreach ($goalie_by_gm as $gm_name => &$goalie_row) {

            // Previous snapshot
            $get_goalie_change = $pdo->prepare("SELECT goalie_wins FROM daily_positional_totals WHERE gm = :gm");
            $get_goalie_change->execute(['gm' => $gm_name]);
            $goalie_change = $get_goalie_change->fetch(PDO::FETCH_ASSOC);

            $goalie_prev_wins = $goalie_change['goalie_wins'] ?? 0;

            // Current totals
            $goalie_wins = $goalie_row['goalie_total_wins'] ?? 0;

            // Calculations
            $goalie_row['total_goalie_wins'] = $goalie_wins;
            $goalie_row['total_goalie_pts']  = $goalie_wins * 3;
            $goalie_row['goalie_win_change'] = $goalie_wins - $goalie_prev_wins;
            $goalie_row['goalie_change_pts'] = $goalie_row['goalie_win_change'] * 3;
        }
        unset($goalie_row); // break reference

        // --------------------------------------------
        // Sort by calculated goalie points (DESC)
        // --------------------------------------------
        usort($goalie_by_gm, function ($a, $b) {
            return $b['total_goalie_pts'] <=> $a['total_goalie_pts'];
        });
        ?>

        <?php foreach ($goalie_by_gm as $goalie_row): ?>
            <?php
            $rank_spot++;
            $gm_name     = $goalie_row['gm_name'];
            $gm_position = htmlspecialchars($gm_name);

            $total_goalie_pts  = $goalie_row['total_goalie_pts'];
            $goalie_change_pts = $goalie_row['goalie_change_pts'];
            $total_goalie_wins = $goalie_row['total_goalie_wins'];
            $goalie_win_change = $goalie_row['goalie_win_change'];

            $row_class = (strcasecmp($gm, $gm_name) === 0)
                ? 'tr_stats highlight_gm'
                : 'tr_stats';
            ?>
            <tr class="<?= $row_class ?>">
                <td><?= $rank_spot ?></td>
                <td><?= $total_goalie_pts ?></td>
                <td><?= $goalie_change_pts ?></td>
                <td>
                    <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                        <?= $gm_position ?>
                    </a>
                </td>
                <td><?= $total_goalie_wins ?></td>
                <td><?= $goalie_win_change ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>