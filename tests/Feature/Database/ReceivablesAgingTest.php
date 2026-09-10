<?php

use App\Models\Patient;
use App\Models\Payment;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $category = TreatmentCategory::create(['name' => 'ترمیمی']);
    $this->service = TreatmentService::create([
        'treatment_category_id' => $category->id,
        'name' => 'ترمیم',
        'price' => 1_000_000,
    ]);
    $this->reports = app(ReportService::class);
});

function makePatient(): Patient
{
    return Patient::create([
        'code' => Patient::nextCode(),
        'first_name' => 'آزمون',
        'last_name' => 'بیمار',
        'registered_on' => now()->subYears(2)->toDateString(),
    ]);
}

function bill(Patient $patient, int $amount, int $daysAgo): Treatment
{
    return Treatment::create([
        'patient_id' => $patient->id,
        'treatment_service_id' => test()->service->id,
        'performed_on' => now()->subDays($daysAgo)->toDateString(),
        'amount' => $amount,
    ]);
}

function pay(Patient $patient, int $amount, int $discount = 0): Payment
{
    return Payment::create([
        'patient_id' => $patient->id,
        'paid_on' => now()->toDateString(),
        'amount' => $amount,
        'discount' => $discount,
    ]);
}

it('reports nothing when every patient has settled', function () {
    $patient = makePatient();
    bill($patient, 5_000_000, 200);
    pay($patient, 5_000_000);

    $aging = $this->reports->receivablesAging();

    expect($aging['total'])->toBe(0)
        ->and($aging['rows'])->toBeEmpty();
});

it('buckets unpaid work by the age of the treatment it belongs to', function () {
    $patient = makePatient();
    bill($patient, 1_000_000, 10);   // within 30 days
    bill($patient, 2_000_000, 45);   // 31–60
    bill($patient, 4_000_000, 200);  // 90+

    $byKey = collect($this->reports->receivablesAging()['buckets'])->keyBy('key');

    expect($this->reports->receivablesAging()['total'])->toBe(7_000_000)
        ->and($byKey['0-30']['total'])->toBe(1_000_000)
        ->and($byKey['31-60']['total'])->toBe(2_000_000)
        ->and($byKey['90+']['total'])->toBe(4_000_000);
});

/**
 * The point of FIFO: money received settles the earliest work, so a patient
 * who paid recently but still owes for old work stays in the old bucket.
 */
it('applies payments to the oldest treatment first', function () {
    $patient = makePatient();
    bill($patient, 3_000_000, 200);  // oldest
    bill($patient, 3_000_000, 10);   // recent

    pay($patient, 3_000_000);

    $aging = $this->reports->receivablesAging();
    $byKey = collect($aging['buckets'])->keyBy('key');

    // The old treatment is now covered; what remains is the recent one.
    expect($aging['total'])->toBe(3_000_000)
        ->and($byKey['90+']['total'])->toBe(0)
        ->and($byKey['0-30']['total'])->toBe(3_000_000);
});

it('counts a discount as settling debt', function () {
    $patient = makePatient();
    bill($patient, 5_000_000, 100);
    pay($patient, 3_000_000, discount: 2_000_000);

    expect($this->reports->receivablesAging()['total'])->toBe(0);
});

it('splits a payment that only partly covers a treatment', function () {
    $patient = makePatient();
    bill($patient, 10_000_000, 120);
    pay($patient, 4_000_000);

    $aging = $this->reports->receivablesAging();

    expect($aging['total'])->toBe(6_000_000)
        ->and(collect($aging['buckets'])->keyBy('key')['90+']['total'])->toBe(6_000_000);
});

it('ignores patients who have overpaid rather than reporting a negative', function () {
    $owing = makePatient();
    bill($owing, 2_000_000, 40);

    $credit = makePatient();
    bill($credit, 1_000_000, 40);
    pay($credit, 5_000_000);

    $aging = $this->reports->receivablesAging();

    expect($aging['total'])->toBe(2_000_000)
        ->and($aging['rows'])->toHaveCount(1);
});

it('reconciles the buckets against billed minus settled', function () {
    foreach (range(1, 6) as $i) {
        $patient = makePatient();
        bill($patient, $i * 1_000_000, $i * 30);
        pay($patient, $i * 400_000);
    }

    $aging = $this->reports->receivablesAging();
    $billed = (int) Treatment::sum('amount');
    $settled = (int) Payment::sum('amount') + (int) Payment::sum('discount');

    expect($aging['total'])->toBe($billed - $settled);
});
