<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

class SmsIrDriver implements SmsDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $lineNumber = null,
    ) {}

    public function send(string $mobile, string $body): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['x-api-key' => $this->apiKey, 'Accept' => 'application/json'])
                ->post('https://api.sms.ir/v1/send/bulk', array_filter([
                    'lineNumber' => $this->lineNumber,
                    'messageText' => $body,
                    'mobiles' => [$mobile],
                ]));

            $json = $response->json();

            if ($response->successful() && (int) data_get($json, 'status') === 1) {
                return ['ok' => true, 'id' => (string) data_get($json, 'data.packId'), 'error' => null];
            }

            return [
                'ok' => false,
                'id' => null,
                'error' => (string) (data_get($json, 'message') ?? "HTTP {$response->status()}"),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'smsir';
    }
}
