<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$f = App\Models\Fight::find(41);
if ($f) {
    print_r($f->toArray());
} else {
    echo "Fight 41 not found.\n";
}
