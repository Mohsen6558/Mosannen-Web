<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'first_name', 'last_name', 'father_name', 'birth_date',
        'registered_on', 'gender', 'national_code', 'mobile', 'home_phone',
        'work_phone', 'home_address', 'work_address', 'job', 'referrer_name',
        'binder_code', 'medical_summary', 'description', 'notes',
        'insurance_id', 'created_by', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'registered_on' => 'date',
        ];
    }

    protected $appends = ['full_name'];

    // ── Relations ────────────────────────────────────────────────────────
    public function insurance(): BelongsTo
    {
        return $this->belongsTo(Insurance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function radiographs(): HasMany
    {
        return $this->hasMany(Radiograph::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    // ── Accessors ────────────────────────────────────────────────────────
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Outstanding balance in Rial: billed treatments minus what was received
     * and minus what was written off as a discount.
     *
     * Positive means the patient owes the clinic. This replaces the legacy
     * `dbo.getReminderMoney` scalar UDF.
     */
    public function getBalanceAttribute(): int
    {
        $billed = $this->treatments()->sum('amount');
        $settled = $this->payments()->sum(DB::raw('amount + discount'));

        return (int) $billed - (int) $settled;
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** Fuzzy search across name, file code, mobile and national code. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/\D/', '', $term);

        return $query->where(function (Builder $q) use ($term, $digits) {
            $q->whereRaw("(first_name || ' ' || last_name) ILIKE ?", ["%{$term}%"]);

            if ($digits !== '') {
                $q->orWhere('mobile', 'like', "%{$digits}%")
                    ->orWhere('national_code', 'like', "%{$digits}%")
                    ->orWhere('code', (int) $digits)
                    ->orWhere('binder_code', 'like', "%{$digits}%");
            }
        });
    }

    /** Attach billed/paid/balance aggregates without an N+1 per row. */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query
            ->withSum('treatments as billed_total', 'amount')
            ->withSum('payments as paid_total', 'amount')
            ->withSum('payments as discount_total', 'discount');
    }

    /** Next file number, continuing the legacy series. */
    public static function nextCode(): int
    {
        $max = (int) static::withTrashed()->max('code');

        return $max > 0 ? $max + 1 : (int) config('clinic.patient_code_start', 10000);
    }
}
