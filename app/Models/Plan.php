<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'code',
        'base_price',
        'billing_cycle',
        'included_usage_units',
        'overage_rate_per_unit',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'included_usage_units' => 'integer',
        'overage_rate_per_unit' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'current_plan_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(SubscriptionSegment::class);
    }
}
