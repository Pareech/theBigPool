<?php include __DIR__ . '/../misc_files/auth_check.php'; ?>

<?php
$message = '';
if (isset($_GET['proceed']) && $_GET['proceed'] === 'yes') {
    include __DIR__ . '/../db_connections/connection_pdo.php';

    try {
        $pdo->beginTransaction();

        $pdo->query("UPDATE salaries 
                     SET protection_drop = NULL
                     WHERE protection_drop IS NOT NULL");
        $pdo->commit();

        $message = 'Database update complete.';
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
    <title>Clear Protection Drop Names</title>
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
                    '- Remove all entries for reseting protected players.\n' +
                    '  This will use pre-protection final submissions, to restore\n' +
                    '  a GM\'s roster if they wanted to redo their protetion list.\n\n' +
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