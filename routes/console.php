<?php

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\SendAppointmentReminders;
use Illuminate\Support\Facades\Schedule;

// Nightly dump, kept for two weeks. The legacy app relied on someone
// remembering to click Backup.
Schedule::command(BackupDatabase::class)
    ->dailyAt('02:30')
    ->timezone('Asia/Tehran')
    ->onOneServer();

// Remind tomorrow's patients during working hours, not at dawn.
Schedule::command(SendAppointmentReminders::class)
    ->dailyAt('18:00')
    ->timezone('Asia/Tehran')
    ->onOneServer();
