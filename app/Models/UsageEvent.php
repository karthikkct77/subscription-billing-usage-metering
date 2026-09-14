<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'subscription_id',
        'subscription_segment_id',
        'idempotency_key',
        'usage_units',
        'occurred_at',
    ];

    protected $casts = [
        'usage_units' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionSegment::class, 'subscription_segment_id');
    }
}
