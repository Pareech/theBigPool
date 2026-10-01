<?php
require_once __DIR__ . '/../db_connections/connection_pdo.php';

$stmt = $pdo->query("SELECT gm_pk, gm_name, user_password FROM gms");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    $plain = $user['user_password'];

    // Skip if already hashed (e.g., starts with $2y$)
    if (str_starts_with($plain, '$2y$') || str_starts_with($plain, '$argon2')) {
        continue;
    }

    $hashed = password_hash($plain, PASSWORD_DEFAULT);

    $update = $pdo->prepare("UPDATE gms SET user_password = :hashed WHERE gm_pk = :id");
    $update->execute(['hashed' => $hashed, 'id' => $user['gm_pk']]);

    echo "Updated password for: {$user['gm_name']}<br>";
}

echo "<strong>✅ All plain-text passwords converted.</strong>";
