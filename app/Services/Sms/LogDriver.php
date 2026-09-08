<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Default driver: records the message instead of spending credit. */
class LogDriver implements SmsDriver
{
    public function send(string $mobile, string $body): array
    {
        Log::channel(config('logging.default'))->info('SMS', compact('mobile', 'body'));

        return ['ok' => true, 'id' => 'log-'.Str::uuid(), 'error' => null];
    }

    public function name(): string
    {
        return 'log';
    }
}
