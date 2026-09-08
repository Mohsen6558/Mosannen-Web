<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\RadiographController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Guest ────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

// ── Authenticated ────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Reachable even while must_change_password is set.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/', DashboardController::class)->name('dashboard');

    // ── Patients ─────────────────────────────────────────────────────────
    Route::get('/patients/search', [PatientController::class, 'search'])->name('patients.search');
    Route::resource('patients', PatientController::class);

    // ── Treatments ───────────────────────────────────────────────────────
    Route::get('/treatments', [TreatmentController::class, 'index'])->name('treatments.index');
    Route::post('/treatments', [TreatmentController::class, 'store'])->name('treatments.store');
    Route::put('/treatments/{treatment}', [TreatmentController::class, 'update'])->name('treatments.update');
    Route::delete('/treatments/{treatment}', [TreatmentController::class, 'destroy'])->name('treatments.destroy');
    Route::get('/treatments/service/{service}/price', [TreatmentController::class, 'servicePrice'])
        ->name('treatments.service-price');

    // ── Payments ─────────────────────────────────────────────────────────
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    // ── Radiography ──────────────────────────────────────────────────────
    Route::get('/images', [RadiographController::class, 'index'])->name('images.index');
    Route::post('/images', [RadiographController::class, 'store'])->name('images.store');
    Route::delete('/images/{radiograph}', [RadiographController::class, 'destroy'])->name('images.destroy');
    // Files are streamed through the app, never served from a public path.
    Route::get('/images/{radiograph}/file', [RadiographController::class, 'show'])->name('images.show');
    Route::get('/images/{radiograph}/thumb', [RadiographController::class, 'thumbnail'])->name('images.thumbnail');

    // ── Prescriptions ────────────────────────────────────────────────────
    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');

    // ── Appointments ─────────────────────────────────────────────────────
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');

    // ── Stock ────────────────────────────────────────────────────────────
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('/stock/items', [StockController::class, 'storeItem'])->name('stock.items.store');
    Route::put('/stock/items/{item}', [StockController::class, 'updateItem'])->name('stock.items.update');
    Route::post('/stock/movements', [StockController::class, 'storeMovement'])->name('stock.movements.store');

    // ── SMS ──────────────────────────────────────────────────────────────
    Route::get('/sms', [SmsController::class, 'index'])->name('sms.index');
    Route::post('/sms', [SmsController::class, 'send'])->name('sms.send');
    Route::post('/sms/{message}/retry', [SmsController::class, 'retry'])->name('sms.retry');
    Route::post('/sms/{message}/cancel', [SmsController::class, 'cancel'])->name('sms.cancel');
    Route::put('/sms/templates/{template}', [SmsController::class, 'updateTemplate'])->name('sms.templates.update');

    // ── Reports ──────────────────────────────────────────────────────────
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/debtors.csv', [ReportController::class, 'exportDebtors'])->name('reports.debtors.export');

    // ── Base data ────────────────────────────────────────────────────────
    Route::prefix('catalog')->name('catalog.')->controller(CatalogController::class)->group(function () {
        Route::get('/treatments', 'treatments')->name('treatments.index');
        Route::post('/categories', 'storeCategory')->name('categories.store');
        Route::put('/categories/{category}', 'updateCategory')->name('categories.update');
        Route::post('/services', 'storeService')->name('services.store');
        Route::put('/services/{service}', 'updateService')->name('services.update');

        Route::get('/drugs', 'drugs')->name('drugs.index');
        Route::post('/drugs', 'storeDrug')->name('drugs.store');
        Route::put('/drugs/{drug}', 'updateDrug')->name('drugs.update');
        Route::post('/variants', 'storeVariant')->name('variants.store');
        Route::put('/variants/{variant}', 'updateVariant')->name('variants.update');

        Route::get('/insurances', 'insurances')->name('insurances.index');
        Route::post('/insurances', 'storeInsurance')->name('insurances.store');
        Route::put('/insurances/{insurance}', 'updateInsurance')->name('insurances.update');

        Route::get('/payment-types', 'paymentTypes')->name('payment-types.index');
        Route::post('/payment-types', 'storePaymentType')->name('payment-types.store');
        Route::put('/payment-types/{paymentType}', 'updatePaymentType')->name('payment-types.update');
    });

    // ── Users and audit ──────────────────────────────────────────────────
    Route::get('/users/activity', [UserController::class, 'activity'])->name('users.activity');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
