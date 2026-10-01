<?php

$get_waiver_dates = $pdo->query("SELECT waiver1, waiver2, waiver3 FROM base_numbers")->fetch(PDO::FETCH_ASSOC);

// Waiver 1 popup + redirect
$current_time = new DateTime("now");
$waiver1_end = new DateTime($get_waiver_dates['waiver1']); // e.g. 2025-11-07 23:59:00

$waiver1_available = clone $waiver1_end;
$waiver1_available->modify('+1 day')->setTime(0, 0);


if ($current_time < $waiver1_available) {
    $formatted_date = $waiver1_available->format('F jS, Y \a\t H:i');

    echo "<script>
        alert('Round 1 waviver results are not yet available\\n\\n'
            + 'They will be avaialable as of {$formatted_date}');
        window.location.href='waiver_gm_picks.php';
        exit;
    </script>";
}

// Waiver 2 popup (after waiver1 closed, before waiver2 closes)
$waiver2_end = new DateTime($get_waiver_dates['waiver2']); // e.g. 2025-12-07 23:59:00
$waiver2_available = clone $waiver2_end;
$waiver2_available->modify('+1 day')->setTime(0, 0);

if ($current_time >= $waiver1_available && $current_time <= $waiver2_available) {
    $formatted_date = $waiver2_available->format('F jS, Y \a\t H:i');

    echo "<script>
        alert('Round 2 waiver results are not available yet.\\n'
             + 'They will be available as of {$formatted_date}\\n\\n'
             + 'Round 1 waiver results will be displayed.\\n');
    </script>";

    // Force waiver_period to 1 until waiver2 closes
    $waiver_period = 'waiver_1';

    // Force title to match waiver_period override
    $waiver_title = "Round 1";
}

// Waiver 3 pending popup (after waiver2 closed, before waiver3 closes)
$waiver3_end = new DateTime($get_waiver_dates['waiver3']); // e.g., 2026-01-07 23:59:00
$waiver3_available = clone $waiver3_end;
$waiver3_available->modify('+1 day')->setTime(0, 0);


if ($current_time >= $waiver2_available && $current_time < $waiver3_available) {
    $formatted_date = $waiver3_available->format('F jS, Y \a\t H:i');

    echo "<script>
        alert('Round 3 waiver results are not available yet.\\n'
             + 'They will be available as of {$formatted_date}\\n\\n'
             + 'Round 2 waiver results will be displayed.\\n');
    </script>";

    // Force waiver_period to 'waiver_2' until waiver3 closes
    $waiver_period = 'waiver_2';

    // Force title to match waiver_period override
    $waiver_title = "Round 2";
}

// Waiver 3 currently active or closed
if ($current_time >= $waiver3_available) {
    // Waiver 3 has closed, show results normally
    $waiver_period = 'waiver_3';
    $waiver_title = "Round 3";
}
