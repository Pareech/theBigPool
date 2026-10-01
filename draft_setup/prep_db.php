<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<?php
$message = '';
if (isset($_GET['proceed']) && $_GET['proceed'] === 'yes') {
    include __DIR__ . '/../db_connections/connection_pdo.php';

    try {
        $pdo->beginTransaction();

        $pdo->query("UPDATE gms SET trades_made = 0 WHERE trades_made !=0;");

        $pdo->query("TRUNCATE trades_completed;");
        $pdo->query("ALTER SEQUENCE trades_done_trade_id_seq RESTART WITH 1;");

        $pdo->query("TRUNCATE waiver_draft;");
        $pdo->query("ALTER SEQUENCE waiver_draft_waivers_pk_seq RESTART WITH 1;");

        $pdo->query("TRUNCATE draft_preparation;");
        $pdo->query("ALTER SEQUENCE draft_preparation1_prep_pk_seq RESTART WITH 1;");

        $pdo->query("TRUNCATE waiver_moves;");
        $pdo->query("ALTER SEQUENCE waiver_moves_id_seq RESTART WITH 1;");

        $pdo->query("INSERT INTO draft_preparation (player,team,cap_hit,current_salary,position,gm,franchise,franchise_date,drafted)
                     SELECT player,team,cap_hit,current_salary,position,gm,franchise,franchise_date,drafted
                     FROM salaries
                     WHERE GM IS NOT NULL;");

        $pdo->query("UPDATE salaries 
                     SET waiver_bid = NULL, 
                         salary_retained = NULL, 
                         latest_pick = NULL,
                         protection_drop = NULL,
                         draft_research = NULL, 
                         draft_research_notes = NULL,
                         picked_up_date = NULL
                     WHERE drafted = 'Waiver'
                        OR salary_retained IS NOT NULL
                        OR latest_pick IS NOT NULL
                        OR draft_research IS NOT NULL
                        OR protection_drop IS NOT NULL
                        OR draft_research_notes IS NOT NULL
                        OR picked_up_date IS NOT NULL");
        $pdo->commit();

        $message = 'Database updates complete.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Confirm Database Update</title>
</head>

<body>

    <?php if (isset($_GET['proceed']) && $_GET['proceed'] === 'yes'): ?>
        <script>
            alert("<?= addslashes($message) ?>");
            window.location.href = '../draft_state/draft_order.php'; // redirect after alert
        </script>
    <?php else: ?>
        <script>
            if (confirm(
                    'The following updates will be done:\n' +
                    '- Draft Preparation DB will be Truncated.\n' +
                    '- Protection list reset lists, will be cleared.\n' +
                    '- Remove all research notes made for draft pick wants.\n' +
                    '- Remove date a player was added to a GM\'s roster.\n' +
                    '- Trades Database will be Truncated.\n' +
                    '- Waivers Database will be Truncated.\n' +
                    '- Waiver payments and retained salaries will be cleared.\n' +
                    '- Last Year\'s drafted players will be copied to Draft Preparation DB\n\n' +
                    'Do you want to proceed?'
                )) {
                window.location.href = '?proceed=yes';
            } else {
                window.location.href = '../draft_state/draft_order.php';
            }
        </script>
    <?php endif; ?>

</body>

</html>