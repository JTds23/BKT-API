<?php

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Customer Model', function () {
    it('has booking requests relationship', function () {
        $customer = Customer::factory()->create();
        BookingRequest::factory()->count(3)->for($customer)->create();

        expect($customer->bookingRequests)->toBeInstanceOf(Collection::class)
            ->and($customer->bookingRequests)->toHaveCount(3)
            ->and($customer->bookingRequests->first())->toBeInstanceOf(BookingRequest::class);
    });

    it('has pending booking requests scope', function () {
        $customer = Customer::factory()->create();

        // Create 2 pending, 1 submitted, 1 cancelled
        BookingRequest::factory()->count(2)->for($customer)->create([
            'status' => BookingRequestStatus::PENDING,
        ]);
        BookingRequest::factory()->for($customer)->submitted()->create();
        BookingRequest::factory()->for($customer)->cancelled()->create();

        expect($customer->pendingBookingRequests)->toHaveCount(2);
    });

    it('returns empty collection when customer has no booking requests', function () {
        $customer = Customer::factory()->create();

        expect($customer->bookingRequests)->toBeInstanceOf(Collection::class)
            ->and($customer->bookingRequests)->toHaveCount(0);
    });

    it('returns empty collection when customer has no pending booking requests', function () {
        $customer = Customer::factory()->create();
        BookingRequest::factory()->for($customer)->submitted()->create();

        expect($customer->pendingBookingRequests)->toHaveCount(0);
    });

    it('fillable includes name', function () {
        $customer = Customer::factory()->create(['name' => 'John Doe']);

        expect($customer->name)->toBe('John Doe');
    });

    it('booking requests belong to customer', function () {
        $customer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()->for($customer)->create();

        expect($bookingRequest->customer_id)->toBe($customer->id)
            ->and($customer->bookingRequests->contains($bookingRequest))->toBeTrue();
    });
});
