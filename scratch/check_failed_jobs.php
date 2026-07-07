<?php
$job = DB::table('failed_jobs')->latest('failed_at')->first();
if ($job) {
    echo "FAILED JOB: " . $job->queue . "\n";
    echo "PAYLOAD: " . $job->payload . "\n";
    echo "EXCEPTION: " . $job->exception . "\n";
} else {
    echo "No failed jobs found.\n";
}
