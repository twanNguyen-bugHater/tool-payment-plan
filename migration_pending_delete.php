<?php
require 'db.php';
try {
    $pdo->exec("ALTER TABLE customers ADD COLUMN pending_delete TINYINT(1) DEFAULT 0 AFTER debt_status");
    echo "SUCCESS: Added pending_delete column.";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "SUCCESS: Column pending_delete already exists.";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
?>
