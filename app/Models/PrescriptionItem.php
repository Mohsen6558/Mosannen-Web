<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id', 'drug_variant_id', 'dosage',
        'instructions', 'quantity', 'item_order',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(DrugVariant::class, 'drug_variant_id');
    }
}
