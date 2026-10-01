<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/gm_status.css' />
    <link rel='stylesheet' type='text/css' href='../css/gm_daily.css?v=2' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
    <title>
        <?php echo htmlspecialchars($_GET['gm'] ?? ''); ?>'s
        Daily Stats
    </title>
</head>

<body>

    <?php
    $gm = $_GET['gm'] ?? '';
    $gm = trim($gm);

    include __DIR__ . '/../db_connections/connection_pdo.php';

    // Validate GM
    $gm_name = $pdo->query("SELECT gm_name FROM gms ORDER BY gm_name;");
    $allowed_gms = $gm_name->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array($gm, $allowed_gms, true)) {
        echo "<h1>This is not the webpage you are looking for.</h1>
          <h1>Now go away.</h1>
          <meta http-equiv='refresh' content='2; url=https://hmpg.net' />";
        exit();
    }

    // Season dates
    $season_info = $pdo->query("SELECT season_start, season_end FROM base_numbers")->fetch(PDO::FETCH_ASSOC);
    $season_start = $season_info['season_start'];
    $season_end   = $season_info['season_end'];
    $today        = (new DateTime('now', new DateTimeZone('America/Toronto')))->format('Y-m-d');
    ?>

    <!-- ── TOP HEADER (mirrors gm_info.php) ── -->
    <div class="grid-container-top">
        <div class="title">
            <h1><?= htmlspecialchars($gm) ?>'s<br>Daily Stats</h1>
        </div>
        <div class="draft_status">
            <?php
            $draft_complete_check = $pdo->query("SELECT 
    (
        (SELECT COUNT(*) FROM salaries WHERE gm IS NOT NULL) >= 
        (SELECT numb_gms * draft_players FROM base_numbers LIMIT 1)
    ) AS draft_done")->fetchColumn();


            if (!$draft_complete_check) {
                echo "<script>
                        alert('The season has not started yet');
                        window.location.href = '../draft_state/draft_order.php';
                    </script>";
                exit();
            } else {
                include 'gm_info_pts.php';
            }
            ?>
        </div>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <?php if ($today < $season_start || $today >= $season_end): ?>
        <p style="text-align:center; margin-top: 40px;">Daily stats are only available during the season.</p>
    <?php else: ?>

        <?php
        // ── TODAY'S PLAYING TEAMS — read from daily_schedule snapshot ────────
        // Use MAX(game_date) so before today's cron fires we show yesterday's
        // teams, and after it fires we show today's teams automatically.
        $schedule_q = $pdo->prepare("SELECT team
                                 FROM daily_schedule
                                 WHERE game_date = (SELECT MAX(game_date) FROM daily_schedule)");
        $schedule_q->execute();
        $teams_playing_today = $schedule_q->fetchAll(PDO::FETCH_COLUMN);
        $schedule_api_failed = empty($teams_playing_today);

        // ── CAPTURE THE SCHEDULE DATE FOR TABLE HEADINGS ─────────────────────
        $schedule_date_q = $pdo->prepare("SELECT MAX(game_date) FROM daily_schedule");
        $schedule_date_q->execute();
        $schedule_date_raw = $schedule_date_q->fetchColumn();
        $schedule_date_label = $schedule_date_raw
            ? (new DateTime($schedule_date_raw))->format('F j, Y')
            : 'Today';

        // ── LIVE STATS for this GM's active roster ──────────────────────────
        $live = $pdo->prepare("SELECT s.player,
                                  s.team,
                                  s.position,
                                  COALESCE(ps.goals,    0) AS goals,
                                  COALESCE(ps.assists,  0) AS assists,
                                  COALESCE(ps.wins,     0) AS wins,
                                  COALESCE(ps.shutouts, 0) AS shutouts,
                                  COALESCE(ps.otl,      0) AS otl
                           FROM salaries s
                           LEFT JOIN player_stats ps
                               ON  LOWER(TRIM(s.player))  = LOWER(TRIM(ps.player))
                               AND s.position             = ps.position
                               AND TRIM(ps.team) ILIKE '%' || TRIM(s.team) || '%'
                           WHERE s.gm = :gm");
        $live->execute(['gm' => $gm]);
        $live_rows = $live->fetchAll(PDO::FETCH_ASSOC);

        // Filter to only players whose team is playing today.
        // $teams_playing_today contains both NHL API and salaries abbreviations
        // so it will match regardless of which version $row['team'] contains.
        if (!$schedule_api_failed && !empty($teams_playing_today)) {
            $live_rows = array_filter($live_rows, function ($row) use ($teams_playing_today) {
                return in_array(strtoupper(trim($row['team'])), $teams_playing_today, true);
            });
            $live_rows = array_values($live_rows);
        }

        // ── TODAY'S SNAPSHOT ────────────────────────────────────────────────
        $snap = $pdo->prepare("SELECT player, position, goals, assists, wins, shutouts, otl
                           FROM daily_player_snapshot
                           WHERE gm = :gm");
        $snap->execute(['gm' => $gm]);

        $snapshot = [];
        foreach ($snap->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $snapshot[$s['player'] . '|' . $s['position']] = $s;
        }

        // ── SEASON TOTALS per player (for the last column) ──────────────────
        $season_q = $pdo->prepare("SELECT s.player,
                                      s.position,
                                      COALESCE(ps.goals,    0) - COALESCE(wm_g.goals,    0) AS season_goals,
                                      COALESCE(ps.assists,  0) - COALESCE(wm_a.assists,  0) AS season_assists,
                                      COALESCE(ps.wins,     0) - COALESCE(wm_w.wins,     0) AS season_wins,
                                      COALESCE(ps.shutouts, 0) - COALESCE(wm_s.shutouts, 0) AS season_shutouts,
                                      COALESCE(ps.otl,      0) - COALESCE(wm_o.otl,      0) AS season_otl
                               FROM salaries s
                               LEFT JOIN player_stats ps
                                   ON  LOWER(TRIM(s.player)) = LOWER(TRIM(ps.player))
                                   AND s.position            = ps.position
                               LEFT JOIN (SELECT player, gm, SUM(goals)    AS goals    FROM waiver_moves WHERE move_type='pickup' GROUP BY player, gm) wm_g
                                   ON wm_g.player = s.player AND wm_g.gm = s.gm
                               LEFT JOIN (SELECT player, gm, SUM(assists)  AS assists  FROM waiver_moves WHERE move_type='pickup' GROUP BY player, gm) wm_a
                                   ON wm_a.player = s.player AND wm_a.gm = s.gm
                               LEFT JOIN (SELECT player, gm, SUM(wins)     AS wins     FROM waiver_moves WHERE move_type='pickup' GROUP BY player, gm) wm_w
                                   ON wm_w.player = s.player AND wm_w.gm = s.gm
                               LEFT JOIN (SELECT player, gm, SUM(shutouts) AS shutouts FROM waiver_moves WHERE move_type='pickup' GROUP BY player, gm) wm_s
                                   ON wm_s.player = s.player AND wm_s.gm = s.gm
                               LEFT JOIN (SELECT player, gm, SUM(otl)      AS otl      FROM waiver_moves WHERE move_type='pickup' GROUP BY player, gm) wm_o
                                   ON wm_o.player = s.player AND wm_o.gm = s.gm
                               WHERE s.gm = :gm");
        $season_q->execute(['gm' => $gm]);

        $season_map = [];
        foreach ($season_q->fetchAll(PDO::FETCH_ASSOC) as $sr) {
            $pos = strtoupper(trim($sr['position']));
            $g   = max(0, (int)$sr['season_goals']);
            $a   = max(0, (int)$sr['season_assists']);
            $w   = max(0, (int)$sr['season_wins']);
            $s   = max(0, (int)$sr['season_shutouts']);
            $o   = max(0, (int)$sr['season_otl']);

            if ($pos === 'F') {
                $pts = ($g * 2) + ($a * 1);
            } elseif ($pos === 'D') {
                $pts = ($g * 2) + ($a * 2);
            } elseif ($pos === 'G') {
                $pts = ($w * 3) + ($s * 3) + ($o * 1) + ($g * 5) + ($a * 1);
            } else {
                $pts = 0;
            }

            $season_map[$sr['player'] . '|' . $pos] = $pts;
        }

        // ── BUILD DAILY ROWS ─────────────────────────────────────────────────
        $skaters = [];
        $goalies  = [];

        foreach ($live_rows as $row) {
            $pos = strtoupper(trim($row['position']));
            $key = $row['player'] . '|' . $pos;
            $s   = $snapshot[$key] ?? ['goals' => 0, 'assists' => 0, 'wins' => 0, 'shutouts' => 0, 'otl' => 0];

            $d_goals    = max(0, (int)$row['goals']    - (int)$s['goals']);
            $d_assists  = max(0, (int)$row['assists']  - (int)$s['assists']);
            $d_wins     = max(0, (int)$row['wins']     - (int)$s['wins']);
            $d_shutouts = max(0, (int)$row['shutouts'] - (int)$s['shutouts']);
            $d_otl      = max(0, (int)$row['otl']      - (int)$s['otl']);

            if ($pos === 'F') {
                $daily_pts = ($d_goals * 2) + ($d_assists * 1);
            } elseif ($pos === 'D') {
                $daily_pts = ($d_goals * 2) + ($d_assists * 2);
            } elseif ($pos === 'G') {
                $daily_pts = ($d_wins * 3) + ($d_shutouts * 3) + ($d_otl * 1) + ($d_goals * 5) + ($d_assists * 1);
            } else {
                $daily_pts = 0;
            }

            $season_pts = $season_map[$key] ?? 0;

            $entry = [
                'player'     => $row['player'],
                'team'       => $row['team'],
                'position'   => $pos,
                'daily_pts'  => $daily_pts,
                'season_pts' => $season_pts,
                'd_goals'    => $d_goals,
                'd_assists'  => $d_assists,
                'd_wins'     => $d_wins,
                'd_shutouts' => $d_shutouts,
                'd_otl'      => $d_otl,
            ];

            if ($pos === 'G') {
                $goalies[]  = $entry;
            } else {
                $skaters[] = $entry;
            }
        }

        // Sort skaters: D first, then F; within each group sort by daily pts desc
        usort($skaters, function ($a, $b) {
            if ($a['position'] !== $b['position']) {
                return ($a['position'] === 'D') ? -1 : 1;
            }
            return $b['daily_pts'] <=> $a['daily_pts'];
        });

        usort($goalies, fn($a, $b) => $b['daily_pts'] <=> $a['daily_pts']);

        // Totals
        $sk_daily_total  = array_sum(array_column($skaters, 'daily_pts'));
        $sk_season_total = array_sum(array_column($skaters, 'season_pts'));
        $g_daily_total   = array_sum(array_column($goalies, 'daily_pts'));
        $g_season_total  = array_sum(array_column($goalies, 'season_pts'));
        ?>

        <div class="outer-scroll">
            <div class="grid-container-bottom">

                <div class="left_panels"></div>

                <div class="drafted_players">

                    <?php if ($schedule_api_failed): ?>
                        <p style="text-align:center; font-style:italic; color:#888; margin-bottom:10px;">
                            Schedule unavailable — showing all players.
                        </p>
                    <?php elseif (empty($teams_playing_today)): ?>
                        <p style="text-align:center; font-style:italic; color:#888; margin-bottom:10px;">
                            No NHL games scheduled today.
                        </p>
                    <?php endif; ?>

                    <!-- ── SKATER TABLE ── -->
                    <div class="table-scroll">
                        <table class="draft_choices daily-skater-table">
                            <tr>
                                <th colspan="6">Skater Stats for Games on <?= htmlspecialchars($schedule_date_label) ?></th>
                            </tr>
                            <tr>
                                <th>Player</th>
                                <th>Team</th>
                                <th>Pos</th>
                                <th>G</th>
                                <th>A</th>
                                <th>FPts</th>
                            </tr>
                            <?php foreach ($skaters as $p): ?>
                                <tr class="picks">
                                    <td><?= htmlspecialchars($p['player']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['team']) ?>
                                    </td>
                                    <td><?= $p['position'] ?>
                                    </td>
                                    <td><?= $p['d_goals'] ?: '' ?>
                                    </td>
                                    <td><?= $p['d_assists'] ?: '' ?>
                                    </td>
                                    <td><?= $p['daily_pts'] ?: '' ?>
                                    <!-- </td> -->
                                    <!-- <td><?= $p['season_pts'] ?> -->
                                    <!-- </td> -->
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:bold; background:#eaeaea;">
                                <td colspan="5" style="text-align:right;">Totals:</td>
                                <td style="color:red;">
                                    <?= $sk_daily_total ?>
                                </td>
                                <!-- <td style="color:red;">
                                    <?= $sk_season_total ?>
                                </td> -->
                            </tr>
                        </table>
                    </div>

                    <!-- ── GOALIE TABLE ── -->
                    <div class="table-scroll">
                        <table class="draft_choices daily-goalie-table">
                            <tr>
                                <th colspan="7">Goalie Stats for Games on <?= htmlspecialchars($schedule_date_label) ?></th>
                            </tr>
                            <tr>
                                <th>Player</th>
                                <th>Team</th>
                                <th>Pos</th>
                                <th>W</th>
                                <th>SHO</th>
                                <th>OTL</th>
                                <th>FPts</th>
                            </tr>
                            <?php foreach ($goalies as $p): ?>
                                <tr class="picks">
                                    <td><?= htmlspecialchars($p['player']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['team']) ?>
                                    </td>
                                    <td><?= $p['position'] ?>
                                    </td>
                                    <td><?= $p['d_wins'] ?: '' ?>
                                    </td>
                                    <td><?= $p['d_shutouts'] ?: '' ?>
                                    </td>
                                    <td><?= $p['d_otl'] ?: '' ?>
                                    </td>
                                    <td><?= $p['daily_pts'] ?: '' ?>
                                    <!-- </td> -->
                                    <!-- <td><?= $p['season_pts'] ?> -->
                                    <!-- </td> -->
                                </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:bold; background:#eaeaea;">
                                <td colspan="6" style="text-align:right;">Totals:</td>
                                <td style="color:red;">
                                    <?= $g_daily_total ?>
                                </td>
                                <!-- <td style="color:red;">
                                    <?= $g_season_total ?>
                                </td> -->
                            </tr>
                        </table>
                    </div>

                </div><!-- end drafted_players -->

                <div class="right_panels"></div>

            </div>
        </div><!-- end outer-scroll -->

    <?php endif; ?>

</body>

</html>