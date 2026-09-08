<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

use function Laravel\Prompts\confirm;

class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
        {--dry-run : Read and validate everything, write nothing}
        {--only=* : Limit to named steps (insurances, patients, treatments, ...)}
        {--force : Skip the confirmation prompt (for scripted cutover runs)}';

    protected $description = 'Import the legacy VB.NET SQL Server database into PostgreSQL';

    /** Steps in dependency order, for --only validation and help output. */
    private const STEPS = [
        'insurances', 'payment_types', 'treatment_catalog', 'drugs', 'stock_items',
        'users', 'patients', 'treatments', 'payments', 'stock_movements',
        'radiographs', 'sms',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = array_values(array_filter((array) $this->option('only')));

        if ($invalid = array_diff($only, self::STEPS)) {
            $this->error('مرحله نامعتبر: '.implode(', ', $invalid));
            $this->line('مراحل معتبر: '.implode(', ', self::STEPS));

            return self::FAILURE;
        }

        if (! $this->checkLegacyConnection()) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->line($dryRun
            ? '  <fg=yellow;options=bold>حالت آزمایشی</> — هیچ داده‌ای نوشته نمی‌شود.'
            : '  <fg=red;options=bold>حالت واقعی</> — داده‌ها در PostgreSQL نوشته می‌شوند.');

        if (! $dryRun && ! $this->option('force') && ! confirm('ادامه می‌دهید؟', default: false)) {
            $this->line('لغو شد.');

            return self::SUCCESS;
        }

        $importer = new LegacyImporter(
            dryRun: $dryRun,
            progress: fn (string $message) => $this->line("  <fg=gray>{$message}</>"),
        );

        $started = microtime(true);
        $this->newLine();

        try {
            // One transaction for the whole run: a failure halfway through
            // must not leave the clinic with half its patients.
            if ($dryRun) {
                $importer->run($only);
            } else {
                DB::transaction(fn () => $importer->run($only), attempts: 1);
            }
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('واردات ناموفق بود و همه‌ی تغییرات برگشت خورد.');
            $this->line("  {$e->getMessage()}");
            $this->line("  {$e->getFile()}:{$e->getLine()}");

            return self::FAILURE;
        }

        $this->renderReport($importer, microtime(true) - $started, $dryRun);

        return self::SUCCESS;
    }

    private function checkLegacyConnection(): bool
    {
        $this->line('  بررسی اتصال به SQL Server قدیمی...');

        try {
            DB::connection('legacy')->getPdo();
        } catch (Throwable $e) {
            $this->error('اتصال به دیتابیس قدیمی برقرار نشد.');
            $this->line("  {$e->getMessage()}");
            $this->newLine();
            $this->line('  بررسی کنید:');
            $this->line('   • مقادیر LEGACY_DB_* در فایل .env درست باشد');
            $this->line('   • افزونه‌ی pdo_sqlsrv روی PHP نصب باشد (php -m | grep sqlsrv)');
            $this->line('   • پورت ۱۴۳۳ از این سرور به SQL Server باز باشد');

            return false;
        }

        $database = config('database.connections.legacy.database');
        $this->line("  <fg=green>✓</> متصل شد به <options=bold>{$database}</>");

        return true;
    }

    private function renderReport(LegacyImporter $importer, float $seconds, bool $dryRun): void
    {
        $report = $importer->report();

        $this->newLine();
        $this->table(
            ['مرحله', 'خوانده‌شده', 'ایجاد', 'به‌روزرسانی', 'رد شده'],
            $report->rows(),
        );

        if ($notes = $report->notes()) {
            $this->newLine();
            $this->line('  <fg=yellow;options=bold>دلایل رد شدن رکوردها:</>');

            foreach ($notes as $step => $reasons) {
                $this->line("  <options=bold>{$step}</>");
                foreach ($reasons as $reason) {
                    $this->line("    • {$reason}");
                }
            }
        }

        $this->newLine();
        $this->line(sprintf('  زمان اجرا: %.1f ثانیه', $seconds));

        if ($report->totalSkipped() > 0) {
            $this->line(sprintf(
                '  <fg=yellow>%s رکورد رد شد.</> پیش از مهاجرت نهایی این موارد را بررسی کنید.',
                number_format($report->totalSkipped()),
            ));
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('  برای اجرای واقعی، دستور را بدون <options=bold>--dry-run</> اجرا کنید.');
        }
    }
}
