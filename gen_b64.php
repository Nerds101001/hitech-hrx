<?php
$code = <<<'PHP'
$pdo = new PDO('mysql:host=localhost;dbname=u561220093_app;charset=utf8mb4', 'u561220093_app', 'ptk;2k6H#C8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $pdo->exec("ALTER TABLE classes ADD COLUMN class_type VARCHAR(191) DEFAULT 'Beginner'");
    echo 'Success';
} catch(Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo 'Success (already exists)';
    } else {
        echo $e->getMessage();
    }
}
PHP;

echo base64_encode($code);
