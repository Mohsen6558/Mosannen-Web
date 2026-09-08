<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /**
     * Deliver one message.
     *
     * @return array{ok: bool, id: ?string, error: ?string}
     */
    public function send(string $mobile, string $body): array;

    public function name(): string;
}
