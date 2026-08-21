<?php
$pools = App\Models\Pool::all();
foreach($pools as $p) {
    echo "ID: {$p->id} | PoolID: {$p->pool_id}" . PHP_EOL;
}
