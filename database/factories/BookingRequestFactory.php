<?php

namespace Database\Factories;

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\ServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    protected $model = BookingRequest::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_provider_id' => ServiceProvider::factory(),
            'status' => BookingRequestStatus::PENDING,
            'submitted_at' => null,
        ];
    }

    /**
     * Indicate that the booking request has been submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingRequestStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that the booking request has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingRequestStatus::CANCELLED,
        ]);
    }
}
