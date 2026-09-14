<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'subscription_segment_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionSegment::class, 'subscription_segment_id');
    }
}
