<?php

namespace App\Observers;

use App\Models\PreMove;
use Illuminate\Support\Facades\Log;

class PreMoveObserver
{
    /**
     * Handle the PreMove "created" event.
     */
    public function created(PreMove $preMove): void
    {
        //
    }

    /**
     * Handle the PreMove "updated" event.
     */
    public function updated(PreMove $preMove): void
    {
            Log::info('PreMove updated', [
            'id' => $preMove->id,

            'url' => request()->fullUrl(),
            'ip' => request()->ip(),
            'method' => request()->method(),
            'input' => request()->all(),
            'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))->pluck('function')
        ]);
    }

    /**
     * Handle the PreMove "deleted" event.
     */
    public function deleted(PreMove $preMove): void
    {
               Log::info('PreMove deleting', [
            'id' => $preMove->id,
            'url' => request()->fullUrl(),
            'ip' => request()->ip(),
            'method' => request()->method(),
            'input' => request()->all(),
            'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))->pluck('function')
        ]);
    }


    public function deleting(PreMove $preMove): void
    {

    }

    /**
     * Handle the PreMove "force deleted" event.
     */
    public function forceDeleted(PreMove $preMove): void
    {
        //
    }
}
