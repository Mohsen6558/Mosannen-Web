<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'treatment_created',
                'name' => 'ثبت درمان',
                'body' => "درمان {{service}} در تاریخ {{date}} برای {{patient}} به مبلغ {{amount}} ریال ثبت شد.\n{{clinic}}",
            ],
            [
                'key' => 'payment_created',
                'name' => 'دریافت وجه',
                'body' => "مبلغ {{amount}} ریال در تاریخ {{date}} از {{patient}} دریافت شد.\nمانده حساب: {{balance}} ریال\n{{clinic}}",
            ],
            [
                'key' => 'appointment_reminder',
                'name' => 'یادآوری نوبت',
                'body' => "{{patient}} عزیز، نوبت شما فردا {{date}} ساعت {{time}} است.\n{{clinic}}\n{{phone}}",
            ],
            [
                'key' => 'debt_reminder',
                'name' => 'یادآوری بدهی',
                'body' => "{{patient}} عزیز، مانده حساب شما {{balance}} ریال است.\n{{clinic}}",
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::firstOrCreate(['key' => $template['key']], [
                ...$template,
                'is_active' => true,
            ]);
        }
    }
}
