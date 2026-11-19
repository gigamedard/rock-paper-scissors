<?php

namespace App\Services;

class WaveTelegramService extends TelegramService
{
    public function __construct()
    {
        parent::__construct('wave.telegram');
    }
}
