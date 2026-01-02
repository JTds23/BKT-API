<?php

use App\Enums\BookingRequestStatus;
use App\Enums\QuoteStatus;
use App\Exceptions\DuplicateTaskException;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\ServiceProvider;
use App\Models\Task;
use App\Services\BookingRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new BookingRequestService();
});

describe('BookingRequestService::createBookingRequest', function () {
    it('creates a booking request with tasks', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $tasks = Task::factory()->count(2)->for($serviceProvider)->create();

        $bookingRequest = $this->service->createBookingRequest(
            $customer,
            $serviceProvider->id,
            $tasks->pluck('id')->toArray()
        );

        expect($bookingRequest)->toBeInstanceOf(BookingRequest::class)
            ->and($bookingRequest->customer_id)->toBe($customer->id)
            ->and($bookingRequest->service_provider_id)->toBe($serviceProvider->id)
            ->and($bookingRequest->status)->toBe(BookingRequestStatus::PENDING)
            ->and($bookingRequest->tasks)->toHaveCount(2);
    });

    it('calculates quote price as sum of task prices', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task1 = Task::factory()->for($serviceProvider)->create(['price' => 25.00]);
        $task2 = Task::factory()->for($serviceProvider)->create(['price' => 35.50]);
        $task3 = Task::factory()->for($serviceProvider)->create(['price' => 19.50]);

        $bookingRequest = $this->service->createBookingRequest(
            $customer,
            $serviceProvider->id,
            [$task1->id, $task2->id, $task3->id]
        );

        expect($bookingRequest->quote)->not->toBeNull()
            ->and($bookingRequest->quote->price)->toBe('80.00')
            ->and($bookingRequest->quote->status)->toBe(QuoteStatus::GENERATED);
    });

    it('throws DuplicateTaskException when task exists in another pending request', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $existingTask = Task::factory()->for($serviceProvider)->create(['name' => 'Oven Clean']);
        $newTask = Task::factory()->for($serviceProvider)->create();

        // Create existing pending booking request with the task
        $existingBookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $existingBookingRequest->tasks()->attach($existingTask);
        Quote::factory()->for($existingBookingRequest)->create();

        // Attempt to create new booking request with same task
        $this->service->createBookingRequest(
            $customer,
            $serviceProvider->id,
            [$existingTask->id, $newTask->id]
        );
    })->throws(DuplicateTaskException::class);

    it('includes duplicate task ids and names in exception', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $existingTask = Task::factory()->for($serviceProvider)->create(['name' => 'Oven Clean']);

        // Create existing pending booking request
        $existingBookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $existingBookingRequest->tasks()->attach($existingTask);
        Quote::factory()->for($existingBookingRequest)->create();

        try {
            $this->service->createBookingRequest(
                $customer,
                $serviceProvider->id,
                [$existingTask->id]
            );
        } catch (DuplicateTaskException $e) {
            expect($e->duplicateTaskIds)->toBe([$existingTask->id])
                ->and($e->duplicateTaskNames)->toBe(['Oven Clean']);
            return;
        }

        $this->fail('Expected DuplicateTaskException was not thrown');
    });

    it('allows same task after previous request is submitted', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        // Create existing SUBMITTED booking request
        $existingBookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->submitted()
            ->create();
        $existingBookingRequest->tasks()->attach($task);
        Quote::factory()->for($existingBookingRequest)->create();

        // Should not throw
        $bookingRequest = $this->service->createBookingRequest(
            $customer,
            $serviceProvider->id,
            [$task->id]
        );

        expect($bookingRequest)->toBeInstanceOf(BookingRequest::class)
            ->and($bookingRequest->tasks)->toHaveCount(1);
    });

    it('allows same task for different customers', function () {
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        // Create pending booking request for customer 1
        $existingBookingRequest = BookingRequest::factory()
            ->for($customer1)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $existingBookingRequest->tasks()->attach($task);
        Quote::factory()->for($existingBookingRequest)->create();

        // Customer 2 should be able to book the same task
        $bookingRequest = $this->service->createBookingRequest(
            $customer2,
            $serviceProvider->id,
            [$task->id]
        );

        expect($bookingRequest)->toBeInstanceOf(BookingRequest::class);
    });

    it('loads relationships on returned booking request', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        $bookingRequest = $this->service->createBookingRequest(
            $customer,
            $serviceProvider->id,
            [$task->id]
        );

        expect($bookingRequest->relationLoaded('tasks'))->toBeTrue()
            ->and($bookingRequest->relationLoaded('quote'))->toBeTrue()
            ->and($bookingRequest->relationLoaded('serviceProvider'))->toBeTrue();
    });
});

describe('BookingRequestService::submitBookingRequest', function () {
    it('submits a pending booking request', function () {
        $bookingRequest = BookingRequest::factory()
            ->create(['status' => BookingRequestStatus::PENDING]);

        $result = $this->service->submitBookingRequest($bookingRequest);

        expect($result->status)->toBe(BookingRequestStatus::SUBMITTED)
            ->and($result->submitted_at)->not->toBeNull();
    });

    it('sets submitted_at timestamp', function () {
        $bookingRequest = BookingRequest::factory()
            ->create(['status' => BookingRequestStatus::PENDING]);

        expect($bookingRequest->submitted_at)->toBeNull();

        $result = $this->service->submitBookingRequest($bookingRequest);

        expect($result->submitted_at)->not->toBeNull()
            ->and($result->submitted_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    it('throws exception when submitting non-pending request', function () {
        $bookingRequest = BookingRequest::factory()
            ->submitted()
            ->create();

        $this->service->submitBookingRequest($bookingRequest);
    })->throws(InvalidArgumentException::class, 'Only pending booking requests can be submitted.');

    it('throws exception when submitting cancelled request', function () {
        $bookingRequest = BookingRequest::factory()
            ->cancelled()
            ->create();

        $this->service->submitBookingRequest($bookingRequest);
    })->throws(InvalidArgumentException::class, 'Only pending booking requests can be submitted.');

    it('persists changes to database', function () {
        $bookingRequest = BookingRequest::factory()
            ->create(['status' => BookingRequestStatus::PENDING]);

        $this->service->submitBookingRequest($bookingRequest);

        $this->assertDatabaseHas('booking_requests', [
            'id' => $bookingRequest->id,
            'status' => BookingRequestStatus::SUBMITTED->value,
        ]);
    });

    it('returns fresh model with loaded relationships', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $bookingRequest->tasks()->attach($task);
        Quote::factory()->for($bookingRequest)->create();

        $result = $this->service->submitBookingRequest($bookingRequest);

        expect($result->relationLoaded('tasks'))->toBeTrue()
            ->and($result->relationLoaded('quote'))->toBeTrue()
            ->and($result->relationLoaded('serviceProvider'))->toBeTrue();
    });
});

describe('BookingRequestService::cancelBookingRequest', function () {
    it('cancels a pending booking request', function () {
        $bookingRequest = BookingRequest::factory()
            ->create(['status' => BookingRequestStatus::PENDING]);

        $result = $this->service->cancelBookingRequest($bookingRequest);

        expect($result->status)->toBe(BookingRequestStatus::CANCELLED);
    });

    it('throws exception when cancelling non-pending request', function () {
        $bookingRequest = BookingRequest::factory()
            ->submitted()
            ->create();

        $this->service->cancelBookingRequest($bookingRequest);
    })->throws(InvalidArgumentException::class, 'Only pending booking requests can be cancelled.');

    it('throws exception when cancelling already cancelled request', function () {
        $bookingRequest = BookingRequest::factory()
            ->cancelled()
            ->create();

        $this->service->cancelBookingRequest($bookingRequest);
    })->throws(InvalidArgumentException::class, 'Only pending booking requests can be cancelled.');

    it('persists changes to database', function () {
        $bookingRequest = BookingRequest::factory()
            ->create(['status' => BookingRequestStatus::PENDING]);

        $this->service->cancelBookingRequest($bookingRequest);

        $this->assertDatabaseHas('booking_requests', [
            'id' => $bookingRequest->id,
            'status' => BookingRequestStatus::CANCELLED->value,
        ]);
    });

    it('returns fresh model with loaded relationships', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $bookingRequest->tasks()->attach($task);
        Quote::factory()->for($bookingRequest)->create();

        $result = $this->service->cancelBookingRequest($bookingRequest);

        expect($result->relationLoaded('tasks'))->toBeTrue()
            ->and($result->relationLoaded('quote'))->toBeTrue()
            ->and($result->relationLoaded('serviceProvider'))->toBeTrue();
    });
});
