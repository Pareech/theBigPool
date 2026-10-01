<?php
$standing_pts = $pdo->query("WITH waiver_pickups AS (
                                SELECT 
                                    player,
                                    gm,
                                    SUM(goals)   AS goals,
                                    SUM(assists) AS assists,
                                    SUM(wins)    AS wins,
                                    SUM(shutouts) AS shutouts,
                                    SUM(otl)     AS otl,
                                    SUM(gp)      AS gp
                                FROM waiver_moves
                                WHERE move_type = 'pickup'
                                GROUP BY player, gm
                            ),

                            waiver_drops AS (
                                SELECT 
                                    player,
                                    gm,
                                    SUM(goals)   AS goals,
                                    SUM(assists) AS assists,
                                    SUM(wins)    AS wins,
                                    SUM(shutouts) AS shutouts,
                                    SUM(otl)     AS otl,
                                    SUM(gp)      AS gp
                                FROM waiver_moves
                                WHERE move_type = 'drop'
                                GROUP BY player, gm
                            ),

                            active_totals AS (
                                SELECT
                                    sal.gm,

                                    /* GP: full GP minus pickup GP (how many they already had when acquired) */
                                    SUM(
                                        COALESCE(stat.gp,0)
                                        - COALESCE(pu.gp,0)
                                    ) AS total_gp,

                                    /* Skater points with full subtraction */
                                    SUM(
                                        CASE WHEN sal.position = 'F' THEN
                                            (COALESCE(stat.goals,0)   - COALESCE(pu.goals,0)) * 2 +
                                            (COALESCE(stat.assists,0) - COALESCE(pu.assists,0)) * 1

                                        WHEN sal.position = 'D' THEN
                                            (COALESCE(stat.goals,0)   - COALESCE(pu.goals,0)) * 2 +
                                            (COALESCE(stat.assists,0) - COALESCE(pu.assists,0)) * 2
                                        ELSE 0 END
                                    ) AS skater_points,

                                    /* Goalie points with full subtraction */
                                    SUM(
                                        CASE WHEN sal.position = 'G' THEN
                                            (COALESCE(stat.wins,0)      - COALESCE(pu.wins,0)) * 3 +
                                            (COALESCE(stat.shutouts,0)  - COALESCE(pu.shutouts,0)) * 3 +
                                            (COALESCE(stat.otl,0)       - COALESCE(pu.otl,0)) * 1 +
                                            (COALESCE(stat.goals,0)     - COALESCE(pu.goals,0)) * 5 +
                                            (COALESCE(stat.assists,0)   - COALESCE(pu.assists,0)) * 1
                                        ELSE 0 END
                                    ) AS goalie_points,

                                    /* Total combined */
                                    SUM(
                                        CASE 
                                            WHEN sal.position IN ('F','D') THEN
                                                CASE WHEN sal.position = 'F' THEN
                                                    (COALESCE(stat.goals,0)   - COALESCE(pu.goals,0)) * 2 +
                                                    (COALESCE(stat.assists,0) - COALESCE(pu.assists,0)) * 1
                                                ELSE
                                                    (COALESCE(stat.goals,0)   - COALESCE(pu.goals,0)) * 2 +
                                                    (COALESCE(stat.assists,0) - COALESCE(pu.assists,0)) * 2
                                                END

                                            WHEN sal.position = 'G' THEN
                                                (COALESCE(stat.wins,0)      - COALESCE(pu.wins,0)) * 3 +
                                                (COALESCE(stat.shutouts,0)  - COALESCE(pu.shutouts,0)) * 3 +
                                                (COALESCE(stat.otl,0)       - COALESCE(pu.otl,0)) * 1 +
                                                (COALESCE(stat.goals,0)     - COALESCE(pu.goals,0)) * 5 +
                                                (COALESCE(stat.assists,0)   - COALESCE(pu.assists,0)) * 1

                                            ELSE 0
                                        END
                                    ) AS total_points

                                FROM salaries sal
                                LEFT JOIN player_stats stat
                                    ON LOWER(TRIM(sal.player)) = LOWER(TRIM(stat.player))
                                    AND sal.position = stat.position
                                    AND TRIM(stat.team) ILIKE '%' || TRIM(sal.team) || '%'

                                LEFT JOIN waiver_pickups pu
                                    ON LOWER(TRIM(sal.player)) = LOWER(TRIM(pu.player))
                                    AND sal.gm = pu.gm

                                WHERE sal.gm IS NOT NULL
                                GROUP BY sal.gm
                            ),

                            dropped_totals AS (
                                SELECT 
                                    dr.gm,

                                    SUM(dr.gp) AS total_gp,

                                    SUM(
                                        CASE WHEN sal.position = 'F' THEN dr.goals*2 + dr.assists*1
                                            WHEN sal.position = 'D' THEN dr.goals*2 + dr.assists*2
                                            ELSE 0 END
                                    ) AS skater_points,

                                    SUM(
                                        CASE WHEN sal.position = 'G' THEN
                                            dr.wins*3 + dr.shutouts*3 + dr.otl*1 + dr.goals*5 + dr.assists*1
                                        ELSE 0 END
                                    ) AS goalie_points,

                                    SUM(
                                        CASE WHEN sal.position = 'F' THEN dr.goals*2 + dr.assists*1
                                            WHEN sal.position = 'D' THEN dr.goals*2 + dr.assists*2
                                            WHEN sal.position = 'G' THEN
                                                dr.wins*3 + dr.shutouts*3 + dr.otl*1 + dr.goals*5 + dr.assists*1
                                            ELSE 0 END
                                    ) AS total_points

                                FROM waiver_drops dr
                                LEFT JOIN salaries sal
                                    ON LOWER(TRIM(dr.player)) = LOWER(TRIM(sal.player))
                                GROUP BY dr.gm
                            ),

                            all_totals AS (
                                SELECT
                                    COALESCE(a.gm, d.gm) AS gm_name,

                                    COALESCE(a.total_gp,0)        + COALESCE(d.total_gp,0)        AS total_gp,
                                    COALESCE(a.skater_points,0)   + COALESCE(d.skater_points,0)   AS skater_points,
                                    COALESCE(a.goalie_points,0)   + COALESCE(d.goalie_points,0)   AS goalie_points,
                                    COALESCE(a.total_points,0)    + COALESCE(d.total_points,0)    AS total_points,
                                    ROUND(
                                          (COALESCE(a.total_points,0) + COALESCE(d.total_points,0)) / 
                                           NULLIF(COALESCE(a.total_gp,0) + COALESCE(d.total_gp,0), 0), 2
                                         ) AS avg_pts

                                FROM active_totals a
                                FULL OUTER JOIN dropped_totals d
                                    ON a.gm = d.gm
                            )

                            SELECT *,
                                MAX(total_points) OVER () - total_points AS points_behind
                            FROM all_totals
                            ORDER BY total_points DESC, skater_points DESC, avg_pts DESC NULLS LAST, gm_name ASC;");
?>