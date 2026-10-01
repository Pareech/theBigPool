<?php
    $hostname="localhost";
    $port="5432";
    $dbname="RAW_HockeyPool_prod";
    $username="Ian";
    $password="1Cafecloppe!26";

    $connection = "pgsql:host=$hostname; port=$port; dbname=$dbname; user=$username; password=$password";
    $pdo = new PDO($connection);
?>