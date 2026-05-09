<?php
$logs = ['storage/logs/tracked_users.log', 'storage/logs/laravel.log', 'storage/logs/batch_polling.log'];
foreach($logs as $log) {
    if(file_exists($log)) {
        file_put_contents($log, "");
        echo "Cleared $log\n";
    }
}
