<?php
// waiver_period_check.php
// -----------------------
// Determine the current waiver period, round title, and max settings

// Ensure $pdo exists
if (!isset($pdo)) {
    throw new RuntimeException('Database connection $pdo not available. Include connection_pdo.php first.');
}

// Fetch waiver period dates and max settings from base_numbers
$stmt = $pdo->query("SELECT waiver1, waiver2, waiver3, max_waiver_slots, max_waivers FROM base_numbers");
$waiver_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$waiver_data) {
    throw new RuntimeException('Could not fetch waiver period data from base_numbers.');
}

// Convert dates to DateTime objects
$waiver1 = new DateTime($waiver_data['waiver1']);
$waiver2 = new DateTime($waiver_data['waiver2']);
$waiver3 = new DateTime($waiver_data['waiver3']);

// Cast integer settings
$max_waiver_slots = (int)$waiver_data['max_waiver_slots']; // max per period
$max_waivers      = (int)$waiver_data['max_waivers'];      // max per season

$today = new DateTime();
$today = $today->format('Y-m-d');
$waiver1 = $waiver1->format('Y-m-d');
$waiver2 = $waiver2->format('Y-m-d');
$waiver3 = $waiver3->format('Y-m-d');

$waiver_period = null;
$waiver_title  = null;

if ($today <= $waiver1) {
    $waiver_period = 'waiver_1';
    $waiver_title  = 'Round 1';
    $drafted_info = 'Round 1 Waiver Pickup';
} elseif ($today <= $waiver2) {
    $waiver_period = 'waiver_2';
    $waiver_title  = 'Round 2';
    $drafted_info = 'Round 2 Waiver Pickup';
} elseif ($today <= $waiver3) {
    $waiver_period = 'waiver_3';
    $waiver_title  = 'Round 3';
    $drafted_info = 'Round 3 Waiver Pickup';
} 
