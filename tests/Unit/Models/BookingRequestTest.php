<?php

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\ServiceProvider;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('BookingRequest Model', function () {
    it('has customer relationship', function () {
        $customer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()->for($customer)->create();

        expect($bookingRequest->customer)->toBeInstanceOf(Customer::class)
            ->and($bookingRequest->customer->id)->toBe($customer->id);
    });

    it('has service provider relationship', function () {
        $serviceProvider = ServiceProvider::factory()->create();
        $bookingRequest = BookingRequest::factory()->for($serviceProvider)->create();

        expect($bookingRequest->serviceProvider)->toBeInstanceOf(ServiceProvider::class)
            ->and($bookingRequest->serviceProvider->id)->toBe($serviceProvider->id);
    });

    it('has tasks relationship (many-to-many)', function () {
        $serviceProvider = ServiceProvider::factory()->create();
        $tasks = Task::factory()->count(3)->for($serviceProvider)->create();
        $bookingRequest = BookingRequest::factory()->for($serviceProvider)->create();

        $bookingRequest->tasks()->attach($tasks->pluck('id'));

        expect($bookingRequest->tasks)->toHaveCount(3)
            ->and($bookingRequest->tasks->first())->toBeInstanceOf(Task::class);
    });

    it('has quote relationship (one-to-one)', function () {
        $bookingRequest = BookingRequest::factory()->create();
        $quote = Quote::factory()->for($bookingRequest)->create();

        expect($bookingRequest->quote)->toBeInstanceOf(Quote::class)
            ->and($bookingRequest->quote->id)->toBe($quote->id);
    });

    it('returns true for isPending when status is pending', function () {
        $bookingRequest = BookingRequest::factory()->create([
            'status' => BookingRequestStatus::PENDING,
        ]);

        expect($bookingRequest->isPending())->toBeTrue();
    });

    it('returns false for isPending when status is submitted', function () {
        $bookingRequest = BookingRequest::factory()->submitted()->create();

        expect($bookingRequest->isPending())->toBeFalse();
    });

    it('returns false for isPending when status is cancelled', function () {
        $bookingRequest = BookingRequest::factory()->cancelled()->create();

        expect($bookingRequest->isPending())->toBeFalse();
    });

    it('casts status to BookingRequestStatus enum', function () {
        $bookingRequest = BookingRequest::factory()->create([
            'status' => BookingRequestStatus::PENDING,
        ]);

        expect($bookingRequest->status)->toBeInstanceOf(BookingRequestStatus::class)
            ->and($bookingRequest->status)->toBe(BookingRequestStatus::PENDING);
    });

    it('casts submitted_at to datetime', function () {
        $bookingRequest = BookingRequest::factory()->submitted()->create();

        expect($bookingRequest->submitted_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    it('allows null submitted_at for pending requests', function () {
        $bookingRequest = BookingRequest::factory()->create([
            'status' => BookingRequestStatus::PENDING,
            'submitted_at' => null,
        ]);

        expect($bookingRequest->submitted_at)->toBeNull();
    });
});
