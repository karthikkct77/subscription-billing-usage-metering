<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'subscription_id',
        'subscription_segment_id',
        'usage_date',
        'total_usage_units',
    ];

    protected $casts = [
        'usage_date' => 'date:Y-m-d',
        'total_usage_units' => 'integer',
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
