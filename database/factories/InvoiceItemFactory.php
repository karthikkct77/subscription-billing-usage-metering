<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'subscription_segment_id' => null,
            'type' => 'base_fee',
            'description' => 'Base Subscription Fee',
            'quantity' => 1,
            'unit_price' => 49.99,
            'amount' => 49.99,
        ];
    }
}
