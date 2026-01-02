<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\BookingRequest;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'booking_request_id' => BookingRequest::factory(),
            'price' => fake()->randomFloat(2, 50, 500),
            'status' => QuoteStatus::GENERATED,
        ];
    }

    /**
     * Indicate that the quote has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::ACCEPTED,
        ]);
    }

    /**
     * Indicate that the quote has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::REJECTED,
        ]);
    }
}
