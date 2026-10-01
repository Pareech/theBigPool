<?php
include __DIR__ . '/../db_connections/connection_pdo.php';

$get_waiver_dates = $pdo->query("SELECT waiver1, waiver2, waiver3 FROM base_numbers")->fetch(PDO::FETCH_ASSOC);

// Waiver 1 popup + redirect
$current_time = new DateTime("now");
$waiver1_end = new DateTime($get_waiver_dates['waiver1']); // e.g. 2025-11-07 23:59:00

$waiver1_available = clone $waiver1_end;
$waiver1_available->modify('+1 day')->setTime(0, 0);


$waiver2_end = new DateTime($get_waiver_dates['waiver2']); // e.g. 2025-12-07 23:59:00
$waiver2_available = clone $waiver2_end;
$waiver2_available->modify('+1 day')->setTime(0, 0);

if ($current_time >= $waiver1_available && $current_time <= $waiver2_available) {

    // Force waiver_period to 1 until waiver2 closes
    $waiver_period = 'waiver_1';
    $drafted_info = 'Waiver 1';
}

// Waiver 3 pending popup (after waiver2 closed, before waiver3 closes)
$waiver3_end = new DateTime($get_waiver_dates['waiver3']); // e.g., 2026-01-07 23:59:00
$waiver3_available = clone $waiver3_end;
$waiver3_available->modify('+1 day')->setTime(0, 0);


if ($current_time >= $waiver2_available && $current_time < $waiver3_available) {

    // Force waiver_period to 'waiver_2' until waiver3 closes
    $waiver_period = 'waiver_2';
    $drafted_info = 'Waiver 2';
}

// Waiver 3 currently active or closed
if ($current_time >= $waiver3_available) {
    // Waiver 3 has closed, show results normally
    $waiver_period = 'waiver_3';
    $drafted_info = 'Waiver 3';
}


$waiver_winners_query = $pdo->prepare("WITH round1_winners AS (
    SELECT DISTINCT ON (waiver_pick) *, waiver_option::int AS waiver_option_int
    FROM waiver_draft
    WHERE waiver_option::int = 1
      AND waiver_period = :waiver_period
    ORDER BY waiver_pick, waiver_bid DESC, waiver_bid_time ASC
),
round2_candidates AS (
    SELECT *
    FROM waiver_draft
    WHERE waiver_option::int = 2
      AND waiver_period = :waiver_period
      AND gm NOT IN (SELECT gm FROM round1_winners)
      AND waiver_pick NOT IN (SELECT waiver_pick FROM round1_winners)
),
round2_winners AS (
    SELECT DISTINCT ON (waiver_pick) *, waiver_option::int AS waiver_option_int
    FROM round2_candidates
    ORDER BY waiver_pick, waiver_bid DESC, waiver_bid_time ASC
),
round3_candidates AS (
    SELECT *
    FROM waiver_draft
    WHERE waiver_option::int = 3
      AND waiver_period = :waiver_period
      AND gm NOT IN (
          SELECT gm FROM round1_winners
          UNION
          SELECT gm FROM round2_winners
      )
      AND waiver_pick NOT IN (
          SELECT waiver_pick FROM round1_winners
          UNION
          SELECT waiver_pick FROM round2_winners
      )
),
round3_winners AS (
    SELECT DISTINCT ON (waiver_pick) *, waiver_option::int AS waiver_option_int
    FROM round3_candidates
    WHERE waiver_period = :waiver_period
    ORDER BY waiver_pick, waiver_bid DESC, waiver_bid_time ASC
)

-- Final result: All winning bids with casted option for sorting
SELECT waiver_pick, waiver_bid, waiver_drop, waiver_option, waiver_option_int, gm, waiver_period FROM round1_winners
UNION ALL
SELECT waiver_pick, waiver_bid, waiver_drop, waiver_option, waiver_option_int, gm, waiver_period FROM round2_winners
UNION ALL
SELECT waiver_pick, waiver_bid, waiver_drop, waiver_option, waiver_option_int, gm, waiver_period FROM round3_winners
ORDER BY waiver_option_int, waiver_pick;");

$waiver_winners_query->execute(['waiver_period' => $waiver_period]);
$waiver_winners = $waiver_winners_query->fetchAll(PDO::FETCH_ASSOC);
