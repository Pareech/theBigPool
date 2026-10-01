<div class="skaters_assists_daily">
    <table class="daily_assists_standings">
        <td class="table_headers" colspan="6">Skater Assists</td>
        <tr>
            <th class="gm_standings_columns">Rank</th>
            <th class="gm_standings_columns">RAW Pts</th>
            <th class="gm_standings_columns">+/-</th>
            <th class="gm_standings_columns">GM</th>
            <th class="gm_standings_columns">Assists</th>
            <th class="gm_standings_columns">+/-</th>
        </tr>

        <?php
        $rank_spot = 0;

        // Index forwards by GM
        $fwd_by_gm = [];
        foreach ($get_fwd_points as $row) {
            $fwd_by_gm[$row['gm_name']] = $row;
        }

        // Index defense by GM
        $def_by_gm = [];
        foreach ($get_def_points as $row) {
            $def_by_gm[$row['gm_name']] = $row;
        }

        // -----------------------------------------------------
        // Preprocess: compute skater assist totals for each GM
        // -----------------------------------------------------
        foreach ($fwd_by_gm as $gm_name => &$fwd_row) {

            // Match defense row (or zero if GM has none)
            $def_row = $def_by_gm[$gm_name] ?? ['def_total_assists' => 0];

            // Previous snapshot
            $get_skater_change = $pdo->prepare("SELECT fwd_assists, def_assists FROM daily_positional_totals WHERE gm = :gm");
            $get_skater_change->execute(['gm' => $gm_name]);
            $skater_change = $get_skater_change->fetch(PDO::FETCH_ASSOC);

            $fwd_prev_assists = $skater_change['fwd_assists'] ?? 0;
            $def_prev_assists = $skater_change['def_assists'] ?? 0;

            // Current totals
            $fwd_assists = $fwd_row['fwd_total_assists'] ?? 0;
            $def_assists = $def_row['def_total_assists'] ?? 0;

            // Calculations
            $total_assists               = $fwd_assists + $def_assists;
            $total_assist_pts            = $fwd_assists + ($def_assists * 2);
            $prev_total_assists          = $fwd_prev_assists + $def_prev_assists;
            $assist_change               = $total_assists - $prev_total_assists;
            $assist_change_pts           = ($fwd_assists + ($def_assists * 2)) - ($fwd_prev_assists + ($def_prev_assists * 2));

            // Store in the forward row (primary array)
            $fwd_row['total_assists']        = $total_assists;
            $fwd_row['total_assist_pts']     = $total_assist_pts;
            $fwd_row['assist_change']        = $assist_change;
            $fwd_row['assist_change_pts']    = $assist_change_pts;
        }
        unset($fwd_row);

        // -----------------------------------------------------
        // Sort by calculated skater assist points (DESC)
        // -----------------------------------------------------
        usort($fwd_by_gm, function ($a, $b) {
            return $b['total_assist_pts'] <=> $a['total_assist_pts'];
        });
        ?>

        <?php foreach ($fwd_by_gm as $fwd_row): ?>
            <?php
            $rank_spot++;
            $gm_name     = $fwd_row['gm_name'];
            $gm_position = htmlspecialchars($gm_name);

            $total_assist_pts  = $fwd_row['total_assist_pts'];
            $assist_change_pts = $fwd_row['assist_change_pts'];
            $total_assists     = $fwd_row['total_assists'];
            $assist_change     = $fwd_row['assist_change'];

            $row_class = (strcasecmp($gm, $gm_name) === 0)
                ? 'tr_stats highlight_gm'
                : 'tr_stats';
            ?>
            <tr class="<?= $row_class ?>">
                <td><?= $rank_spot ?></td>
                <td><?= $total_assist_pts ?></td>
                <td><?= $assist_change_pts ?></td>
                <td>
                    <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                        <?= $gm_position ?>
                    </a>
                </td>
                <td><?= $total_assists ?></td>
                <td><?= $assist_change ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

</div>