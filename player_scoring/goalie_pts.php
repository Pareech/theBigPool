<?php
// Points by Goalie per GM
$goalie_points = $pdo->query("WITH waiver_totals AS (
                                SELECT
                                    player,
                                    position,
                                    gm,
                                    move_type,
                                    SUM(goals) AS goals,
                                    SUM(assists) AS assists,
                                    SUM(wins) AS wins,
                                    SUM(shutouts) AS shutouts,
                                    SUM(otl) AS otl,
                                    SUM(gp) AS gp
                                FROM waiver_moves
                                WHERE position = 'G'
                                GROUP BY player, position, gm, move_type
                            ),
                            adjusted_goalies AS (
                                SELECT
                                    s.gm,
                                    s.player,
                                    COALESCE(ps.gp,0)      - COALESCE(wt_pickup.gp,0)      AS adj_gp,
                                    COALESCE(ps.goals,0)   - COALESCE(wt_pickup.goals,0)   AS adj_goals,
                                    COALESCE(ps.assists,0) - COALESCE(wt_pickup.assists,0) AS adj_assists,
                                    COALESCE(ps.wins,0)    - COALESCE(wt_pickup.wins,0)    AS adj_wins,
                                    COALESCE(ps.shutouts,0)- COALESCE(wt_pickup.shutouts,0) AS adj_shutouts,
                                    COALESCE(ps.otl,0)     - COALESCE(wt_pickup.otl,0)     AS adj_otl,
                                    (COALESCE(ps.wins,0) - COALESCE(wt_pickup.wins,0))*3 +
                                    (COALESCE(ps.shutouts,0) - COALESCE(wt_pickup.shutouts,0))*3 +
                                    (COALESCE(ps.otl,0) - COALESCE(wt_pickup.otl,0))*1 +
                                    COALESCE(ps.goals,0)*5 +
                                    COALESCE(ps.assists,0)*1 AS goalie_points
                                FROM salaries s
                                JOIN player_stats ps
                                    ON LOWER(TRIM(s.player)) = LOWER(TRIM(ps.player))
                                AND s.position = ps.position
                                LEFT JOIN waiver_totals wt_pickup
                                    ON LOWER(TRIM(s.player)) = LOWER(TRIM(wt_pickup.player))
                                AND s.position = wt_pickup.position
                                AND wt_pickup.move_type = 'pickup'
                                WHERE s.gm IS NOT NULL
                                AND s.position = 'G'
                            ),
                            dropped_goalies AS (
                                SELECT
                                    gm,
                                    SUM(goals) AS goals,
                                    SUM(assists) AS assists,
                                    SUM(wins) AS wins,
                                    SUM(shutouts) AS shutouts,
                                    SUM(otl) AS otl,
                                    SUM(gp) AS gp,
                                    SUM(wins*3 + shutouts*3 + otl*1 + goals*5 + assists*1) AS goalie_points
                                FROM waiver_totals
                                WHERE move_type = 'drop'
                                GROUP BY gm
                            )
                            SELECT
                                COALESCE(a.gm, d.gm) AS gm_name,
                                COALESCE(SUM(a.adj_gp),0)        + COALESCE(d.gp,0)            AS goalie_total_gp,
                                COALESCE(SUM(a.adj_goals),0)     + COALESCE(d.goals,0)         AS goalie_total_goals,
                                COALESCE(SUM(a.adj_assists),0)   + COALESCE(d.assists,0)       AS goalie_total_assists,
                                COALESCE(SUM(a.adj_wins),0)      + COALESCE(d.wins,0)          AS goalie_total_wins,
                                COALESCE(SUM(a.adj_shutouts),0)  + COALESCE(d.shutouts,0)      AS goalie_total_shutouts,
                                COALESCE(SUM(a.adj_otl),0)       + COALESCE(d.otl,0)           AS goalie_total_otl,
                                COALESCE(SUM(a.goalie_points),0) + COALESCE(d.goalie_points,0) AS goalie_points
                            FROM adjusted_goalies a
                            FULL OUTER JOIN dropped_goalies d
                                ON a.gm = d.gm
                            GROUP BY COALESCE(a.gm, d.gm), d.gp, d.goals, d.assists, d.wins, d.shutouts, d.otl, d.goalie_points
                            ORDER BY goalie_points DESC;");
?>