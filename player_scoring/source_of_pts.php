<?php
$pts_source = $pdo->query("WITH waiver_totals AS (
                                    SELECT player, gm, move_type, team,
                                        SUM(goals) AS goals,
                                        SUM(assists) AS assists,
                                        SUM(wins) AS wins,
                                        SUM(shutouts) AS shutouts,
                                        SUM(otl) AS otl
                                    FROM waiver_moves
                                    GROUP BY player, gm, move_type, team
                                ),
                                active_totals AS (
                                    SELECT
                                        sal.gm,
										SUM(COALESCE(stat.goals,0)    - COALESCE(wt.goals,0))    AS total_goals,
										SUM(COALESCE(stat.assists,0)  - COALESCE(wt.assists,0))  AS total_assists,
										SUM(COALESCE(stat.wins,0)     - COALESCE(wt.wins,0))     AS total_wins,
										SUM(COALESCE(stat.shutouts,0) - COALESCE(wt.shutouts,0)) AS total_shutouts,
										SUM(COALESCE(stat.otl,0)      - COALESCE(wt.otl,0))      AS total_otls,
                                        SUM(
                                            CASE 
                                                WHEN sal.position = 'F' THEN (COALESCE(stat.goals,0)-COALESCE(wt.goals,0))*2 + (COALESCE(stat.assists,0)-COALESCE(wt.assists,0))*1
                                                WHEN sal.position = 'D' THEN (COALESCE(stat.goals,0)-COALESCE(wt.goals,0))*2 + (COALESCE(stat.assists,0)-COALESCE(wt.assists,0))*2
                                                WHEN sal.position = 'G' THEN (COALESCE(stat.wins,0)-COALESCE(wt.wins,0))*3 + (COALESCE(stat.shutouts,0)-COALESCE(wt.shutouts,0))*3 + (COALESCE(stat.otl,0)-COALESCE(wt.otl,0))*1 + (COALESCE(stat.goals,0)-COALESCE(wt.goals,0))*5 + (COALESCE(stat.assists,0)-COALESCE(wt.assists,0))*1
                                                ELSE 0
                                            END
                                        ) AS total_points
                                    FROM salaries sal
                                    LEFT JOIN player_stats stat
                                        ON LOWER(TRIM(sal.player)) = LOWER(TRIM(stat.player)) 
                                        AND sal.position = stat.position
                                        AND TRIM(stat.team) ILIKE TRIM(sal.team)
                                    LEFT JOIN waiver_totals wt
                                        ON LOWER(TRIM(sal.player)) = LOWER(TRIM(wt.player))
                                        AND ((wt.move_type='drop' AND sal.salary_retained = wt.gm)
                                            OR (wt.move_type='pickup' AND sal.gm = wt.gm))
                             	       WHERE sal.gm IS NOT NULL
                                    GROUP BY sal.gm
                                ),
                                dropped_totals AS (
                                    SELECT
                                        wt.gm AS gm,
                                        SUM(wt.goals) AS total_goals,
                                        SUM(wt.assists) AS total_assists,
                                        SUM(wt.wins) AS total_wins,
                                        SUM(wt.shutouts) AS total_shutouts,
                                        SUM(wt.otl) AS total_otls,
                                        SUM(
                                            CASE 
                                                WHEN sal.position = 'F' THEN wt.goals*2 + wt.assists*1
                                                WHEN sal.position = 'D' THEN wt.goals*2 + wt.assists*2
                                                WHEN sal.position = 'G' THEN wt.wins*3 + wt.shutouts*3 + wt.otl*1 + wt.goals*5 + wt.assists*1
                                                ELSE 0
                                            END
                                        ) AS total_points
                                    FROM waiver_totals wt
                                    LEFT JOIN salaries sal
                                        ON LOWER(TRIM(sal.player)) = LOWER(TRIM(wt.player))
                                        AND TRIM(sal.team) ILIKE TRIM(wt.team)

                                    WHERE wt.move_type='drop'
                                    GROUP BY wt.gm
                                )
                                SELECT 
                                    COALESCE(a.gm, d.gm) AS gm_name,
                                    COALESCE(a.total_goals,0) + COALESCE(d.total_goals,0) AS total_goals,
                                    COALESCE(a.total_assists,0) + COALESCE(d.total_assists,0) AS total_assists,
                                    COALESCE(a.total_wins,0) + COALESCE(d.total_wins,0) AS total_wins,
                                    COALESCE(a.total_shutouts,0) + COALESCE(d.total_shutouts,0) AS total_shutouts,
                                    COALESCE(a.total_otls,0) + COALESCE(d.total_otls,0) AS total_otls,
                                    COALESCE(a.total_points,0) + COALESCE(d.total_points,0) AS total_points
                                FROM active_totals a
                                FULL OUTER JOIN dropped_totals d
                                    ON a.gm = d.gm
                                ORDER BY total_points DESC;");
?>