<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSmsMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(public int $messageId) {}

    public function handle(SmsService $sms): void
    {
        $message = SmsMessage::find($this->messageId);

        // Cancelled from the outbox screen while it sat in the queue.
        if (! $message || $message->status !== 'queued') {
            return;
        }

        $driver = $sms->driver();
        $result = $driver->send($message->mobile, $message->body);

        if ($result['ok']) {
            $message->update([
                'status' => 'sent',
                'provider' => $driver->name(),
                'provider_message_id' => $result['id'],
                'sent_at' => now(),
                'error' => null,
            ]);

            return;
        }

        $message->update([
            'provider' => $driver->name(),
            'error' => $result['error'],
            // Only give up once the retries are spent.
            'status' => $this->attempts() >= $this->tries ? 'failed' : 'queued',
        ]);

        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }
}
