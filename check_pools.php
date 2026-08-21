<?php
$pools = App\Models\Pool::all();
echo "Pool Count: " . $pools->count() . PHP_EOL;
foreach($pools as $p) {
    echo "ID: {$p->id} | Status: {$p->status} | Size: {$p->pool_size}" . PHP_EOL;
}
