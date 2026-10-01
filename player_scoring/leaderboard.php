<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>RAW Hockey Pool – Standings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/scoring.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="page-header">
        <h1>RAW Hockey Pool<br>Standings</h1>
    </div>

    <?php
    $gm = $_SESSION['gm_name'];
    include __DIR__ . '/../db_connections/connection_pdo.php';
    include __DIR__ . '/../misc_files/nav_bar_links.php';

    // Helper: exact match for players to avoid duplicates like Elias Pettersson / Sebastian Aho
    // $player_match_condition = "LOWER(TRIM(sal.player)) = LOWER(TRIM(stat.player)) AND sal.position = stat.position";

    // Overall Pool Standings "Standings"
    include 'standing_pts.php';

    // Source of Points (goals, assists, wins, SHO, OTL)
    include 'source_of_pts.php';

    // Forward Points per GM
    include 'fwd_pts.php';

    // Points by Defencemen per GM
    include 'def_pts.php';

    // Points by Goalie per GM
    include 'goalie_pts.php';

    $get_fwd_points    = $fwd_points->fetchAll(PDO::FETCH_ASSOC);
    $get_def_points    = $def_points->fetchAll(PDO::FETCH_ASSOC);
    $get_goalie_points = $goalie_points->fetchAll(PDO::FETCH_ASSOC);

    // True only when:
    //   (a) today is on or after nhl_start_date in base_numbers, AND
    //   (b) player_stats has been populated (at least one GM has points > 0)
    // Either condition being false suppresses all stat columns (shows — instead of 0).
    $nhl_started     = $pdo->query("SELECT CURRENT_DATE >= nhl_start_date FROM base_numbers LIMIT 1")->fetchColumn();
    $stats_available = $nhl_started &&
        !empty($get_fwd_points) &&
        array_sum(array_column($get_fwd_points, 'fwd_points')) > 0;
    ?>

    <div class="grid-container-bottom">

        <!-- Standings -->
        <div class="standings">
            <div class="table-scroll">
                <table class="gm_standings">
                    <tr>
                        <td class="table_headers" colspan="9">Standings</td>
                    </tr>
                    <tr>
                        <th class="gm_standings_columns">Ranks</th>
                        <th class="gm_standings_columns">GM</th>
                        <th class="gm_standings_columns">RAW Points</th>
                        <th class="gm_standings_columns">Daily Point Change</th>
                        <th class="gm_standings_columns">RAW Pts / Game</th>
                        <th class="gm_standings_columns">Games Played</th>
                        <th class="gm_standings_columns">Skater Points</th>
                        <th class="gm_standings_columns">Goalie Points</th>
                        <th class="gm_standings_columns">PBL</th>
                    </tr>

                    <?php
                    $rank_spot = 0;
                    $standings_rows = $standing_pts->fetchAll(PDO::FETCH_ASSOC);

                    // If no standings data yet (e.g. pre-season with empty salaries table),
                    // fall back to showing GM names alphabetically with no stats.
                    if (empty($standings_rows)):
                        $gm_names_only = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($gm_names_only as $gm_name_only):
                            $rank_spot++;
                            $gm_position = htmlspecialchars($gm_name_only);
                            $row_class   = (strcasecmp($gm, $gm_name_only) === 0) ? 'tr_stats highlight_gm' : 'tr_stats';
                    ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $rank_spot ?></td>
                                <td>
                                    <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                                        <?= $gm_position ?>
                                    </a>
                                </td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                            </tr>
                        <?php endforeach;
                    else:
                        foreach ($standings_rows as $row): ?>
                            <?php
                            $rank_spot++;
                            $gm_position  = htmlspecialchars($row['gm_name']);
                            $total_pts    = htmlspecialchars($row['total_points']);

                            $get_point_change = $pdo->prepare("SELECT total_points 
                                                       FROM daily_positional_totals
                                                       WHERE gm = :gm;");
                            $get_point_change->execute(['gm' => $row['gm_name']]);
                            $point_change = $get_point_change->fetchColumn();

                            $avg_pts      = htmlspecialchars($row['avg_pts'] ?? 0);
                            $games_played = htmlspecialchars($row['total_gp']);
                            $skater_pts   = htmlspecialchars($row['skater_points']);
                            $goalie_pts   = htmlspecialchars($row['goalie_points']);
                            $pts_behind   = htmlspecialchars($row['points_behind']);
                            $row_class    = (strcasecmp($gm, $row['gm_name']) === 0) ? 'tr_stats highlight_gm' : 'tr_stats';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $rank_spot ?></td>
                                <td>
                                    <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                                        <?= htmlspecialchars($gm_position) ?>
                                    </a>
                                </td>
                                <td><?= $stats_available ? $total_pts : '—' ?></td>
                                <td class="pt_totals"><?= $stats_available ? ($total_pts - $point_change) : '—' ?></td>
                                <td><?= $stats_available ? $avg_pts      : '—' ?></td>
                                <td><?= $stats_available ? $games_played : '—' ?></td>
                                <td><?= $stats_available ? $skater_pts   : '—' ?></td>
                                <td><?= $stats_available ? $goalie_pts   : '—' ?></td>
                                <td><?= $stats_available ? $pts_behind   : '—' ?></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </table>
            </div>
        </div>

        <!-- Leader board Table -->
        <div class="gm_rankings">
            <div class="table-scroll">
                <table class="gm_standings">
                    <td class="table_headers" colspan="8">Points Sources</td>
                    <tr>
                        <th class="gm_standings_columns">Rank</th>
                        <th class="gm_standings_columns">GM</th>
                        <th class="gm_standings_columns">RAW Points</th>
                        <th class="gm_standings_columns">Goals</th>
                        <th class="gm_standings_columns">Assists</th>
                        <th class="gm_standings_columns">Wins</th>
                        <th class="gm_standings_columns">SHO</th>
                        <th class="gm_standings_columns">OTL</th>
                    </tr>

                    <?php
                    $rank_spot = 0;
                    foreach ($pts_source as $row): ?>
                        <?php
                        $rank_spot++;
                        $gm_position = htmlspecialchars($row['gm_name']);
                        $goals       = htmlspecialchars($row['total_goals']);
                        $assists     = htmlspecialchars($row['total_assists']);
                        $wins        = htmlspecialchars($row['total_wins']);
                        $shutouts    = htmlspecialchars($row['total_shutouts']);
                        $otls        = htmlspecialchars($row['total_otls']);
                        $points      = htmlspecialchars($row['total_points']);
                        $row_class   = (strcasecmp($gm, $row['gm_name']) === 0) ? 'tr_stats highlight_gm' : 'tr_stats';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= $rank_spot ?></td>
                            <td>
                                <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                                    <?= htmlspecialchars($gm_position) ?>
                                </a>
                            </td>
                            <td><?= $stats_available ? $points  : '—' ?></td>
                            <td><?= $stats_available ? $goals   : '—' ?></td>
                            <td><?= $stats_available ? $assists : '—' ?></td>
                            <td><?= $stats_available ? $wins    : '—' ?></td>
                            <td><?= $stats_available ? $shutouts : '—' ?></td>
                            <td><?= $stats_available ? $otls    : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Forwards table -->
        <div class="forward_leaders">
            <div class="table-scroll">
                <table class="gm_standings">
                    <td class="table_headers" colspan="6">Points by Forwards</td>
                    <tr>
                        <th class="gm_standings_columns">Rank</th>
                        <th class="gm_standings_columns">GM</th>
                        <th class="gm_standings_columns">RAW Points</th>
                        <th class="gm_standings_columns">Daily Point Change</th>
                        <th class="gm_standings_columns">Goals</th>
                        <th class="gm_standings_columns">Assists</th>
                    </tr>
                    <?php
                    $rank_spot = 0;
                    foreach ($get_fwd_points as $row): ?>
                        <?php
                        $rank_spot++;
                        $gm_position  = htmlspecialchars($row['gm_name']);

                        $get_fwd_change = $pdo->prepare("SELECT fwd_pts 
                                                     FROM daily_positional_totals
                                                     WHERE gm = :gm;");
                        $get_fwd_change->execute(['gm' => $row['gm_name']]);
                        $fwd_change = $get_fwd_change->fetchColumn();

                        $fwd_goals   = htmlspecialchars($row['fwd_total_goals']);
                        $fwd_assists = htmlspecialchars($row['fwd_total_assists']);
                        $fwd_points  = htmlspecialchars($row['fwd_points']);
                        $row_class   = (strcasecmp($gm, $row['gm_name']) === 0) ? 'tr_stats highlight_gm' : 'tr_stats';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= $rank_spot ?></td>
                            <td>
                                <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                                    <?= htmlspecialchars($gm_position) ?>
                                </a>
                            </td>
                            <td><?= $stats_available ? $fwd_points  : '—' ?></td>
                            <td class="pt_totals"><?= $stats_available ? ($fwd_points - $fwd_change) : '—' ?></td>
                            <td><?= $stats_available ? $fwd_goals   : '—' ?></td>
                            <td><?= $stats_available ? $fwd_assists : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Defencemen table -->
        <div class="defence_leaders">
            <div class="table-scroll">
                <table class="gm_standings">
                    <td class="table_headers" colspan="6">Points by Defencemen</td>
                    <tr>
                        <th class="gm_standings_columns">Rank</th>
                        <th class="gm_standings_columns">GM</th>
                        <th class="gm_standings_columns">RAW Points</th>
                        <th class="gm_standings_columns">Daily Point Change</th>
                        <th class="gm_standings_columns">Goals</th>
                        <th class="gm_standings_columns">Assists</th>
                    </tr>
                    <?php
                    $rank_spot = 0;
                    foreach ($get_def_points as $row): ?>
                        <?php
                        $rank_spot++;
                        $gm_position  = htmlspecialchars($row['gm_name']);

                        $get_def_change = $pdo->prepare("SELECT def_pts 
                                                     FROM daily_positional_totals
                                                     WHERE gm = :gm;");
                        $get_def_change->execute(['gm' => $row['gm_name']]);
                        $def_change = $get_def_change->fetchColumn();

                        $def_goals   = htmlspecialchars($row['def_total_goals']);
                        $def_assists = htmlspecialchars($row['def_total_assists']);
                        $def_points  = htmlspecialchars($row['def_points']);
                        $row_class   = (strcasecmp($gm, $row['gm_name']) === 0) ? 'tr_stats highlight_gm' : 'tr_stats';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= $rank_spot ?></td>
                            <td>
                                <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                                    <?= htmlspecialchars($gm_position) ?>
                                </a>
                            </td>
                            <td><?= $stats_available ? $def_points  : '—' ?></td>
                            <td class="pt_totals"><?= $stats_available ? ($def_points - $def_change) : '—' ?></td>
                            <td><?= $stats_available ? $def_goals   : '—' ?></td>
                            <td><?= $stats_available ? $def_assists : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Goalie table -->
        <div class="goalie_leaders">
            <div class="table-scroll">
                <table class="gm_standings">
                    <td class="table_headers" colspan="9">Points by Goalies</td>
                    <tr>
                        <th class="gm_standings_columns">Rank</th>
                        <th class="gm_standings_columns">GM</th>
                        <th class="gm_standings_columns">RAW Points</th>
                        <th class="gm_standings_columns">Daily Point Change</th>
                        <th class="gm_standings_columns">Wins</th>
                        <th class="gm_standings_columns">SHO</th>
                        <th class="gm_standings_columns">OL+ShL</th>
                        <th class="gm_standings_columns">Goals</th>
                        <th class="gm_standings_columns">Assists</th>
                    </tr>
                    <?php
                    $rank_spot = 0;
                    foreach ($get_goalie_points as $row): ?>
                        <?php
                        $rank_spot++;
                        $gm_position  = htmlspecialchars($row['gm_name']);

                        $get_goalie_change = $pdo->prepare("SELECT goalie_pts 
                                                        FROM daily_positional_totals
                                                        WHERE gm = :gm;");
                        $get_goalie_change->execute(['gm' => $row['gm_name']]);
                        $goalie_change = $get_goalie_change->fetchColumn();

                        $wins           = htmlspecialchars($row['goalie_total_wins']);
                        $shutouts       = htmlspecialchars($row['goalie_total_shutouts']);
                        $otl            = htmlspecialchars($row['goalie_total_otl']);
                        $goalie_goals   = htmlspecialchars($row['goalie_total_goals']);
                        $goalie_assists = htmlspecialchars($row['goalie_total_assists']);
                        $goalie_points  = htmlspecialchars($row['goalie_points']);
                        $row_class      = (strcasecmp($gm, $row['gm_name']) === 0) ? 'tr_stats highlight_gm' : 'tr_stats';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><?= $rank_spot ?></td>
                            <td>
                                <a style="color: #0000FF;" href="../gm_listings/gm_daily.php?gm=<?= urlencode($gm_position) ?>">
                                    <?= htmlspecialchars($gm_position) ?>
                                </a>
                            </td>
                            <td><?= $stats_available ? $goalie_points  : '—' ?></td>
                            <td class="pt_totals"><?= $stats_available ? ($goalie_points - $goalie_change) : '—' ?></td>
                            <td><?= $stats_available ? $wins           : '—' ?></td>
                            <td><?= $stats_available ? $shutouts       : '—' ?></td>
                            <td><?= $stats_available ? $otl            : '—' ?></td>
                            <td><?= $stats_available ? $goalie_goals   : '—' ?></td>
                            <td><?= $stats_available ? $goalie_assists : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <?php if ($stats_available): ?>
        <div class="grid-container-dailies">
            <?php
            include 'daily_skater_goals.php';
            include 'daily_skater_assists.php';
            include 'daily_goalie_wins.php';
            include 'daily_goalie_otl.php';
            include 'daily_goalie_shutouts.php';
            include 'daily_goalie_assists.php';
            ?>
        </div>
    <?php endif; ?>

    <script>
        const tables = document.querySelectorAll("table");

        tables.forEach(table => {
            table.addEventListener("mouseover", e => {
                const cell = e.target;
                if (cell.tagName !== "TD" && cell.tagName !== "TH") return;

                // Skip everything if the cell has class "table_headers"
                if (cell.classList.contains("table_headers")) return;

                const row = cell.parentElement;
                const colIndex = cell.cellIndex;

                // Highlight the row
                row.classList.add("hover-row");

                // Skip column hover if:
                // - first column
                // - cell is <th> (header row)
                if (colIndex !== 0 && cell.tagName !== "TH") {
                    table.querySelectorAll("tr").forEach(tr => {
                        const c = tr.children[colIndex];
                        if (c && !c.classList.contains("table_headers") && c.tagName !== "TH") {
                            c.classList.add("hover-col");
                        }
                    });
                }

                // Intersection cell
                cell.classList.add("hover-cell");
            });

            table.addEventListener("mouseout", e => {
                table.querySelectorAll("tr").forEach(tr => tr.classList.remove("hover-row"));
                table.querySelectorAll("td").forEach(td => td.classList.remove("hover-col", "hover-cell"));
            });
        });

        // Enable click-to-sort on all table headers
        tables.forEach(table => {
            const headers = table.querySelectorAll("th");
            headers.forEach((header, index) => {
                header.style.cursor = "pointer";

                header.addEventListener("click", () => {
                    const tbody = table.tBodies[0] || table;

                    // Determine next sort direction:
                    // - if neither asc nor desc present -> sort DESC on first click
                    // - otherwise toggle from current state
                    const currentlyAsc = header.classList.contains("asc");
                    const currentlyDesc = header.classList.contains("desc");
                    let nextAsc;
                    if (!currentlyAsc && !currentlyDesc) {
                        nextAsc = false; // first click => DESC
                    } else {
                        nextAsc = !currentlyAsc; // toggle
                    }

                    // Apply classes for visuals
                    headers.forEach(h => h.classList.remove("asc", "desc"));
                    header.classList.toggle("asc", nextAsc);
                    header.classList.toggle("desc", !nextAsc);

                    const dirModifier = nextAsc ? 1 : -1;

                    // Collect rows to sort (skip top 'table_headers' row and header row)
                    const rows = Array.from(tbody.querySelectorAll("tr")).slice(2);

                    const sortedRows = rows.sort((a, b) => {
                        const aText = a.children[index]?.textContent.trim() || "";
                        const bText = b.children[index]?.textContent.trim() || "";

                        const aNum = parseFloat(aText.replace(/[^0-9.-]/g, ""));
                        const bNum = parseFloat(bText.replace(/[^0-9.-]/g, ""));
                        const bothNumeric = !isNaN(aNum) && !isNaN(bNum);

                        if (bothNumeric) {
                            return (aNum - bNum) * dirModifier;
                        } else {
                            return aText.localeCompare(bText) * dirModifier;
                        }
                    });

                    // Re-attach rows in sorted order
                    sortedRows.forEach(row => tbody.appendChild(row));
                });
            });
        });
    </script>
</body>

</html>