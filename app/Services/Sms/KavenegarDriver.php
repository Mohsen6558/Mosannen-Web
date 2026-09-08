<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

class KavenegarDriver implements SmsDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $sender = null,
    ) {}

    public function send(string $mobile, string $body): array
    {
        try {
            $response = Http::timeout(15)
                ->asForm()
                ->post("https://api.kavenegar.com/v1/{$this->apiKey}/sms/send.json", array_filter([
                    'receptor' => $mobile,
                    'message' => $body,
                    'sender' => $this->sender,
                ]));

            $json = $response->json();
            $status = (int) data_get($json, 'return.status');

            if ($response->successful() && $status === 200) {
                return [
                    'ok' => true,
                    'id' => (string) data_get($json, 'entries.0.messageid'),
                    'error' => null,
                ];
            }

            return [
                'ok' => false,
                'id' => null,
                'error' => (string) (data_get($json, 'return.message') ?? "HTTP {$response->status()}"),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage()];
        }
    }

    public function name(): string
    {
        return 'kavenegar';
    }
}
