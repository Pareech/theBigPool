<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Key Numbers</title>
    <link rel='stylesheet' type='text/css' href='../css/round_npick.css' />
    <link rel='stylesheet' type='text/css' href='../css/season_start.css' />
    <link rel="icon" type="image/x-icon" href='../misc_files/rhcp_logo.ico' />
</head>

<body>

    <div class="grid-container-top">
        <div class="title">
            <h1>Budgets, Trades<br>and Key Dates</h1>
        </div>
        <div class="draft_status">
            <?php
            $money = new NumberFormatter('en', NumberFormatter::CURRENCY);
            include __DIR__ . '/../db_connections/connection_pdo.php';
            include __DIR__ . '/../draft_state/round_npick.php';
            ?>
        </div>
    </div>

    <?php include __DIR__ . '/../misc_files/nav_bar_links.php'; ?>

    <?php
    //Get Draft info
    $draft_info = $pdo->query("SELECT season_start, 
                                  cap_number,
                                  waiver_number,
                                  max_trades,
                                  max_waivers,
                                  waiver1, waiver2, waiver3,
                                  protection_list,
                                  franchise_due,
                                  nhl_start_date
                            FROM base_numbers");

    $dateTimeObj = new DateTime();
    $money = new NumberFormatter('en', NumberFormatter::CURRENCY);
    ?>

    <div class="grid-container_key_numbers">
        <table class="table_recap">
            <tbody>
                <?php foreach ($draft_info as $row): ?>
                    <tr class="tr_recap">
                        <td class="td_heading">Protection List<br>Submission Deadline</td>
                        <td class="td_heading"><?= htmlspecialchars(formatDateOrNA($row['protection_list'])) ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Franchise Player<br>Submission Deadline</td>
                        <td class="td_heading"><?= htmlspecialchars(formatDateOrNA($row['franchise_due'])) ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Draft Date</td>
                        <td class="td_heading"><?= htmlspecialchars(new DateTime($row['season_start'])->format('F jS, Y')) ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">First Day of Stats</td>
                        <td class="td_heading"><?= htmlspecialchars(new DateTime($row['nhl_start_date'])->format('F jS, Y')) ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Salary Cap Budget</td>
                        <td class="td_heading"><?= formatNumberOrNA($row['cap_number'], 1000000, "M$") ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Base Waiver Budget</td>
                        <td class="td_heading"><?= formatNumberOrNA($row['waiver_number'], 1000000, "M$") ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Max Trades Allowed</td>
                        <td class="td_heading"><?= htmlspecialchars($row['max_trades']) ?? '' ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Max Waivers Allowed</td>
                        <td class="td_heading"><?= htmlspecialchars($row['max_waivers']) ?? '' ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Waiver 1<br>Submission Deadline</td>
                        <td class="td_heading"><?= htmlspecialchars(formatDateOrNA($row['waiver1'])) ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Waiver 2<br>Submission Deadline</td>
                        <td class="td_heading"><?= htmlspecialchars(formatDateOrNA($row['waiver2'])) ?></td>
                    </tr>
                    <tr class="tr_recap">
                        <td class="td_heading">Waiver 3<br>Submission Deadline</td>
                        <td class="td_heading"><?= htmlspecialchars(formatDateOrNA($row['waiver3'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    include __DIR__ . '/../misc_files/last_db_update.php';

    function formatDateOrNA(?string $date): string
    {
        if (empty($date)) {
            return "Not Set";
        }
        return (new DateTime($date))->format('F jS, Y') . " by 23h59";
    }

    function formatNumberOrNA($number, int $divisor = 1, string $suffix = ''): string
    {
        if ($number === null || $number === '') {
            return "Not Set";
        }
        $value = $number / $divisor;
        return htmlspecialchars($value) . $suffix;
    }
    ?>

</body>

</html>