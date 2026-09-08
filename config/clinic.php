<?php

return [
    /*
     | Clinic identity. Printed on receipts, prescriptions and SMS signatures.
     */
    'name' => env('CLINIC_NAME', 'کلینیک دندانپزشکی'),
    'phone' => env('CLINIC_PHONE', ''),
    'address' => env('CLINIC_ADDRESS', ''),

    /*
     | Currency. The legacy system stored Rial as a whole number; we keep that
     | and never use floats for money. Display can be toggled to Toman.
     */
    'currency' => [
        'code' => env('CLINIC_CURRENCY', 'IRR'),
        'display' => env('CLINIC_CURRENCY_DISPLAY', 'rial'), // rial | toman
    ],

    /*
     | Patient file numbers started at 10000 in the legacy app. Keep the series
     | continuous so historical paper files still match.
     */
    'patient_code_start' => (int) env('CLINIC_PATIENT_CODE_START', 10000),

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // log | kavenegar | smsir
        'sender' => env('SMS_SENDER', ''),
        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
        ],
        'smsir' => [
            'api_key' => env('SMSIR_API_KEY'),
        ],
        // Templates are stored in the DB so staff can edit them without a deploy.
        'enabled_events' => [
            'treatment_created' => (bool) env('SMS_ON_TREATMENT', true),
            'payment_created' => (bool) env('SMS_ON_PAYMENT', true),
            'appointment_reminder' => (bool) env('SMS_ON_APPOINTMENT', true),
        ],
    ],

    'images' => [
        // Radiography files. 'local' for an on-prem server, 's3' for MinIO/cloud.
        'disk' => env('IMAGES_DISK', 'images'),
        'max_upload_mb' => (int) env('IMAGES_MAX_UPLOAD_MB', 25),
        'thumbnail_width' => 320,
    ],
];
