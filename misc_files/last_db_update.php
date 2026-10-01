<!DOCTYPE html>

<!-- Track the last time I change was made to the database
     Will Update base_numbers for last update to the database
     This is only for Dev and PreProd. The information will not be used once in Production.
     Used to avoid confusion of what version DB is at while doing Pre-Darft Testing. -->

<?php
$pdo->prepare("UPDATE base_numbers SET last_db_update = now()")->execute();
?>