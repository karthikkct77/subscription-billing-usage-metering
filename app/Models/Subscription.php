<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'current_plan_id',
        'starts_at',
        'ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(SubscriptionSegment::class);
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class);
    }

    public function dailyUsages(): HasMany
    {
        return $this->hasMany(DailyUsage::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
