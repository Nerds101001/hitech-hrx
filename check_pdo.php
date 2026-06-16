<?php
try {
    \ = new PDO('mysql:host=127.0.0.1;dbname=u989061032_hrx', 'u989061032_hrx', '#Q0iB0~LC');
    \ = \->query("SHOW TABLES LIKE 'travel_claims'");
    \ = \->fetchAll();
    echo "Has travel_claims? " . (count(\) > 0 ? "YES" : "NO") . PHP_EOL;

    \ = \->query("SELECT * FROM migrations ORDER BY id DESC LIMIT 5");
    foreach(\->fetchAll(PDO::FETCH_ASSOC) as \) {
        echo \['migration'] . " (Batch: " . \['batch'] . ")" . PHP_EOL;
    }
} catch (Exception \) {
    echo "Error: " . \->getMessage();
}
