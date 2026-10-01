<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Season Data</title>
    <link rel='stylesheet' type='text/css' href='../css/season_start.css' />
    <link rel="icon" type="image/x-icon" href="../misc_files/rhcp_logo.ico" />
</head>

<body>

    <div class="header">
        <h1 style="color:#ffa500">Update Season<br>Key Dates and Salary Cap</h1>

        <?php
        include __DIR__ . '/../db_connections/connection_pdo.php';
        include __DIR__ . '/../misc_files/pre_draft_check.php';
        ?>
    </div>

    <?php
    include __DIR__ . '/../misc_files/nav_bar_links.php';

    $money = new NumberFormatter('en', NumberFormatter::CURRENCY);

    $get_info = $pdo->query("SELECT cap_number, season_start, waiver1, waiver2, waiver3, protection_list FROM base_numbers");

    foreach ($get_info as $row) {
        $current_start       = $row['season_start'];
        $get_current_salaryCap = $row['cap_number'];
        $get_waiver1         = $row['waiver1'];
        $get_waiver2         = $row['waiver2'];
        $get_waiver3         = $row['waiver3'];
        $get_protection_list = $row['protection_list'];
    }
    ?>

    <form name="date_change" action="" method="POST">
        <div class="grid-container">

            <div class="set_start_date">
                <H1>Update Season<br>Start Date</H1>
                <input type="date" id="textboxid" name="date" />
            </div>

            <div class="protection_list">
                <H1>Protection List<br>Submission</H1>
                <input type="date" id="textboxid" name="protection_list" />
            </div>

            <div class="set_nhl_start">
                <H1>NHL Season<br>Start Date</H1>
                <input type="date" id="textboxid" name="nhl_start_date" />
            </div>

            <div class="set_waivers">
                <H1>Waiver 1</H1>
                <input type="date" id="textboxid" name="waiver1" />
            </div>

            <div class="set_waivers">
                <H1>Waiver 2</H1>
                <input type="date" id="textboxid" name="waiver2" />
            </div>

            <div class="set_waivers">
                <H1>Waiver 3</H1>
                <input type="date" id="textboxid" name="waiver3" />
            </div>

            <div class="new_cap">
                <H1>Update the<br>Salary Cap</H1>
                <input type="text" id="textboxid" placeholder="Enter Salary Cap" name="salary_cap" />
            </div>

            <div class="update_button">
                <button type="submit" name="submit_date" class="buttonSet">Update<br>Season Data</button>
            </div>

        </div>
    </form>

    <?php
    function formatDateOrNA(?string $date): string
    {
        if (empty($date)) {
            return "Not Set";
        }
        return (new DateTime($date))->format('F jS, Y');
    }

    function formatCurrencyOrNA($amount, NumberFormatter $formatter, string $currency = "USD"): string
    {
        if ($amount === null || $amount === '') {
            return "Not Set";
        }
        return $formatter->formatCurrency((float)$amount, $currency);
    }

    if (isset($_POST['submit_date'])) {

        $start_date      = $_POST['date'] ?? null;
        $salary_cap      = $_POST['salary_cap'] ?? null;
        $waiver1         = $_POST['waiver1'] ?? null;
        $waiver2         = $_POST['waiver2'] ?? null;
        $waiver3         = $_POST['waiver3'] ?? null;
        $protection_list = $_POST['protection_list'] ?? null;
        $nhl_start_date  = $_POST['nhl_start_date'] ?? null;

        $salary_cap = preg_replace('/[^0-9]/', '', $salary_cap);
        $salary_cap = (int)$salary_cap;

        if (empty($salary_cap)) {
            $salary_cap = $get_current_salaryCap;
        }
        if (empty($start_date)) {
            $start_date = $current_start;
        }
        if (empty($waiver1)) {
            $waiver1 = $get_waiver1;
        }
        if (empty($waiver2)) {
            $waiver2 = $get_waiver2;
        }
        if (empty($waiver3)) {
            $waiver3 = $get_waiver3;
        }
        if (empty($protection_list)) {
            $protection_list = $get_protection_list;
        }

        $date = new DateTime($start_date);
        $date->sub(new DateInterval('P1D'));
        $franchise_due = $date->format('Y-m-d');

        $update_start = $pdo->prepare("UPDATE base_numbers
                                       SET cap_number      = :salary_cap,
                                           season_start    = :season_start,
                                           waiver1         = :waiver1,
                                           waiver2         = :waiver2,
                                           waiver3         = :waiver3,
                                           protection_list = :protection,
                                           franchise_due   = :franchise_due,
                                           nhl_start_date  = :nhl_start_date");
        $update_start->execute([
            'season_start'   => $start_date,
            'salary_cap'     => $salary_cap,
            'waiver1'        => $waiver1,
            'waiver2'        => $waiver2,
            'waiver3'        => $waiver3,
            'protection'     => $protection_list,
            'franchise_due'  => $franchise_due,
            'nhl_start_date' => !empty($nhl_start_date) ? $nhl_start_date : null,
        ]);

        $pdo->query("UPDATE base_numbers
                     SET season_end = make_date(EXTRACT(YEAR FROM season_start)::int + 1, 6, 30)
                     WHERE season_start IS NOT NULL;");

        $start_date_fmt      = formatDateOrNA($start_date);
        $waiver1_fmt         = formatDateOrNA($waiver1);
        $waiver2_fmt         = formatDateOrNA($waiver2);
        $waiver3_fmt         = formatDateOrNA($waiver3);
        $protection_list_fmt = formatDateOrNA($protection_list);
        $franchise_due_fmt   = formatDateOrNA($franchise_due);
        $salary_cap_fmt      = formatCurrencyOrNA($salary_cap, $money);
        $nhl_start_date_fmt  = formatDateOrNA($nhl_start_date);

        include __DIR__ . '/../misc_files/last_db_update.php';

        echo "<script>
            alert('Protection List Submission is set to $protection_list_fmt.\\n'
                + 'Franchise Submission Date is Set to $franchise_due_fmt.\\n'
                + 'Season Start Date is Set to $start_date_fmt.\\n\\n'
                + 'Salary Cap is Set to $salary_cap_fmt.\\n\\n'
                + 'NHL Season Start is Set to $nhl_start_date_fmt.\\n\\n'
                + 'Waiver 1 is $waiver1_fmt.\\n'
                + 'Waiver 2 is $waiver2_fmt.\\n'
                + 'Waiver 3 is $waiver3_fmt.');
            window.location.href = 'draft_info.php';
        </script>";
        exit();
    }
    ?>

</body>

</html>