<?php
 // Points by Forward per GM
$fwd_points = $pdo->query("WITH waiver_totals AS (
                                SELECT
                                    player,
                                    position,
                                    SUM(goals)   AS goals,
                                    SUM(assists) AS assists,
                                    SUM(gp)      AS gp
                                FROM waiver_moves
                                GROUP BY player, position
                            ),
                            adjusted_stats AS (
                                SELECT
                                    s.gm,
                                    s.player,
                                    s.position,
                                    COALESCE(ps.gp,0)      - COALESCE(wt.gp,0)      AS adj_gp,
                                    COALESCE(ps.goals,0)   - COALESCE(wt.goals,0)   AS adj_goals,
                                    COALESCE(ps.assists,0) - COALESCE(wt.assists,0) AS adj_assists,
                                    (COALESCE(ps.goals,0) - COALESCE(wt.goals,0))*2 +
                                    (COALESCE(ps.assists,0) - COALESCE(wt.assists,0))*1 AS fwd_points
                                FROM salaries s
                                JOIN player_stats ps
                                    ON LOWER(TRIM(s.player)) = LOWER(TRIM(ps.player))
                                AND s.position = ps.position
                                LEFT JOIN waiver_totals wt
                                    ON LOWER(TRIM(s.player)) = LOWER(TRIM(wt.player))
                                AND s.position = wt.position   -- <-- only subtract same-position points
                                WHERE s.gm IS NOT NULL
                                AND s.position = 'F'
                            ),
                            dropped_totals AS (
                                SELECT
                                    gm,
                                    SUM(gp)      AS total_gp,
                                    SUM(goals)   AS total_goals,
                                    SUM(assists) AS total_assists,
                                    SUM(goals*2 + assists*1) AS fwd_points
                                FROM waiver_moves
                                WHERE move_type = 'drop'
                                AND position = 'F'   -- <-- only include forwards
                                GROUP BY gm
                            )
                            SELECT
                                COALESCE(a.gm, d.gm) AS gm_name,
                                COALESCE(SUM(a.adj_gp),0)      + COALESCE(d.total_gp,0)      AS fwd_total_gp,
                                COALESCE(SUM(a.adj_goals),0)   + COALESCE(d.total_goals,0)   AS fwd_total_goals,
                                COALESCE(SUM(a.adj_assists),0) + COALESCE(d.total_assists,0) AS fwd_total_assists,
                                COALESCE(SUM(a.fwd_points),0)  + COALESCE(d.fwd_points,0)   AS fwd_points
                            FROM adjusted_stats a
                            FULL OUTER JOIN dropped_totals d
                                ON a.gm = d.gm
                            GROUP BY COALESCE(a.gm, d.gm), d.total_gp, d.total_goals, d.total_assists, d.fwd_points
                            ORDER BY fwd_points DESC;");
?>