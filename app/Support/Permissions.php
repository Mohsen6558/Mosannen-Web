<?php

namespace App\Support;

/**
 * The permission catalogue.
 *
 * The legacy application stored permissions as a dash-separated string of
 * magic numbers ("1-2-7-8") decoded by a chain of string comparisons. Those
 * nine coarse flags are expanded here into named, per-module abilities.
 *
 * The `legacy` key on each group records which old flag the ability came
 * from, so `legacy:import` can map existing accounts without guesswork.
 */
final class Permissions
{
    /**
     * @return array<string, array{label: string, abilities: array<string, string>}>
     */
    public static function catalogue(): array
    {
        return [
            'patients' => [
                'label' => 'پرونده بیماران',
                'abilities' => [
                    'patients.view' => 'مشاهده پرونده‌ها',
                    'patients.create' => 'ثبت بیمار جدید',
                    'patients.update' => 'ویرایش مشخصات بیمار',
                    'patients.delete' => 'حذف بیمار',
                ],
            ],
            'treatments' => [
                'label' => 'درمان',
                'abilities' => [
                    'treatments.view' => 'مشاهده درمان‌ها',
                    'treatments.create' => 'ثبت درمان',
                    'treatments.update' => 'ویرایش درمان',
                    'treatments.delete' => 'حذف درمان',
                ],
            ],
            'payments' => [
                'label' => 'مالی',
                'abilities' => [
                    'payments.view' => 'مشاهده پرداخت‌ها',
                    'payments.create' => 'ثبت پرداخت',
                    'payments.update' => 'ویرایش پرداخت',
                    'payments.delete' => 'حذف پرداخت',
                    'payments.change-date' => 'تغییر تاریخ پرداخت',
                    'payments.discount' => 'اعمال تخفیف',
                ],
            ],
            'images' => [
                'label' => 'عکس‌برداری',
                'abilities' => [
                    'images.view' => 'مشاهده تصاویر',
                    'images.upload' => 'بارگذاری تصویر',
                    'images.delete' => 'حذف تصویر',
                ],
            ],
            'prescriptions' => [
                'label' => 'نسخه',
                'abilities' => [
                    'prescriptions.view' => 'مشاهده نسخه‌ها',
                    'prescriptions.manage' => 'ثبت و ویرایش نسخه',
                ],
            ],
            'appointments' => [
                'label' => 'نوبت‌دهی',
                'abilities' => [
                    'appointments.view' => 'مشاهده نوبت‌ها',
                    'appointments.manage' => 'ثبت و ویرایش نوبت',
                ],
            ],
            'stock' => [
                'label' => 'انبار',
                'abilities' => [
                    'stock.view' => 'مشاهده انبار',
                    'stock.manage' => 'ثبت ورود و خروج کالا',
                ],
            ],
            'sms' => [
                'label' => 'پیامک',
                'abilities' => [
                    'sms.view' => 'مشاهده پیامک‌ها',
                    'sms.send' => 'ارسال پیامک',
                ],
            ],
            'reports' => [
                'label' => 'گزارش‌ها',
                'abilities' => [
                    'reports.view' => 'مشاهده گزارش‌ها',
                    'reports.financial' => 'گزارش‌های مالی',
                    'reports.export' => 'خروجی گرفتن',
                ],
            ],
            'catalog' => [
                'label' => 'اطلاعات پایه',
                'abilities' => [
                    'catalog.manage' => 'مدیریت خدمات، تعرفه، دارو، بیمه',
                ],
            ],
            'users' => [
                'label' => 'کاربران',
                'abilities' => [
                    'users.manage' => 'مدیریت کاربران و سطوح دسترسی',
                    'settings.manage' => 'تنظیمات کلینیک',
                    'audit.view' => 'مشاهده گزارش فعالیت‌ها',
                ],
            ],
        ];
    }

    /** @return list<string> every permission name */
    public static function all(): array
    {
        $out = [];

        foreach (self::catalogue() as $group) {
            $out = [...$out, ...array_keys($group['abilities'])];
        }

        return $out;
    }

    /**
     * Roles shipped with the application. `admin` is granted everything via
     * a Gate::before rule rather than by listing permissions.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            'admin' => [],

            'doctor' => [
                'patients.view', 'patients.create', 'patients.update',
                'treatments.view', 'treatments.create', 'treatments.update',
                'payments.view',
                'images.view', 'images.upload',
                'prescriptions.view', 'prescriptions.manage',
                'appointments.view', 'appointments.manage',
                'sms.view', 'sms.send',
                'reports.view', 'reports.financial',
            ],

            'reception' => [
                'patients.view', 'patients.create', 'patients.update',
                'treatments.view',
                'payments.view', 'payments.create',
                'images.view',
                'appointments.view', 'appointments.manage',
                'sms.view', 'sms.send',
                'reports.view',
            ],

            'accountant' => [
                'patients.view',
                'treatments.view',
                'payments.view', 'payments.create', 'payments.update', 'payments.discount',
                'reports.view', 'reports.financial', 'reports.export',
            ],

            'assistant' => [
                'patients.view',
                'treatments.view',
                'images.view', 'images.upload',
                'stock.view', 'stock.manage',
                'appointments.view',
            ],
        ];
    }

    /**
     * Legacy flag → permissions granted. Derived from `ConvertPermession` and
     * the `HaveInPermession` call sites in the old Functions.vb.
     *
     * @return array<string, list<string>>
     */
    public static function legacyMap(): array
    {
        return [
            // "اطلاعات اولیه" gated the whole base-data menu, which in the old
            // app also contained user management and DB backup/restore.
            '1' => ['catalog.manage', 'users.manage', 'settings.manage'],
            '2' => ['payments.update'],                 // ویرایش مالی
            '4' => ['treatments.update'],               // ویرایش درمان
            '5' => ['payments.delete'],                 // حذف مالی
            '6' => ['treatments.delete'],               // حذف درمان
            '7' => ['reports.view', 'reports.financial', 'reports.export'],
            '8' => ['patients.update'],                 // ویرایش مشخصات مریض
            '9' => ['payments.change-date'],            // تغییر تاریخ پرداخت
            '10' => ['patients.delete'],                // حذف مریض
        ];
    }

    /**
     * Everyone who could open the old application could at least read and
     * create the day-to-day records; there was no flag for it.
     *
     * @return list<string>
     */
    public static function legacyBaseline(): array
    {
        return [
            'patients.view', 'patients.create',
            'treatments.view', 'treatments.create',
            'payments.view', 'payments.create',
            'images.view', 'images.upload',
            'prescriptions.view', 'prescriptions.manage',
            'stock.view', 'stock.manage',
            'sms.view', 'sms.send',
            'appointments.view', 'appointments.manage',
        ];
    }
}
