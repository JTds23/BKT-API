<?php

use App\Enums\BookingRequestStatus;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\ServiceProvider;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Create Booking Request', function () {
    it('creates a booking request with tasks and generates quote with correct sum', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $tasks = Task::factory()->count(3)->for($serviceProvider)->create([
            'price' => 25.00,
        ]);
        $expectedTotal = 75.00; // 3 * 25.00

        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => $serviceProvider->id,
            'task_ids' => $tasks->pluck('id')->toArray(),
        ], ['X-Customer-Id' => $customer->id]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.service_provider.id', $serviceProvider->id)
            ->assertJsonCount(3, 'data.tasks')
            ->assertJsonPath('data.quote.status', 'generated')
            ->assertJsonPath('data.quote.price', '75.00');

        // Verify database state
        $this->assertDatabaseHas('booking_requests', [
            'customer_id' => $customer->id,
            'service_provider_id' => $serviceProvider->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('quotes', [
            'price' => 75.00,
            'status' => 'generated',
        ]);
    });

    it('returns 409 when task already exists in another pending request for the same customer', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $existingTask = Task::factory()->for($serviceProvider)->create();
        $newTask = Task::factory()->for($serviceProvider)->create();

        // Create existing pending booking request with the task
        $existingBookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $existingBookingRequest->tasks()->attach($existingTask);
        Quote::factory()->for($existingBookingRequest)->create();

        // Attempt to create new booking request with same task
        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => $serviceProvider->id,
            'task_ids' => [$existingTask->id, $newTask->id],
        ], ['X-Customer-Id' => $customer->id]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Cannot add tasks that already exist in other pending booking requests')
            ->assertJsonPath('duplicate_task_ids.0', $existingTask->id);
    });

    it('allows same task in new request when previous request is submitted', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        // Create existing SUBMITTED booking request with the task
        $existingBookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->submitted()
            ->create();
        $existingBookingRequest->tasks()->attach($task);
        Quote::factory()->for($existingBookingRequest)->create();

        // Should succeed since previous request is not pending
        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => $serviceProvider->id,
            'task_ids' => [$task->id],
        ], ['X-Customer-Id' => $customer->id]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
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
        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => $serviceProvider->id,
            'task_ids' => [$task->id],
        ], ['X-Customer-Id' => $customer2->id]);

        $response->assertStatus(201);
    });

    it('returns 422 for validation errors when service provider does not exist', function () {
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => 9999,
            'task_ids' => [1],
        ], ['X-Customer-Id' => $customer->id]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_provider_id']);
    });

    it('returns 422 when task_ids is empty', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();

        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => $serviceProvider->id,
            'task_ids' => [],
        ], ['X-Customer-Id' => $customer->id]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids']);
    });

    it('returns 422 when task does not belong to service provider', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $otherProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($otherProvider)->create();

        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => $serviceProvider->id,
            'task_ids' => [$task->id],
        ], ['X-Customer-Id' => $customer->id]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids.0']);
    });

    it('returns 401 without X-Customer-Id header', function () {
        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => 1,
            'task_ids' => [1],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'X-Customer-Id header is required.');
    });

    it('returns 404 when customer does not exist', function () {
        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => 1,
            'task_ids' => [1],
        ], ['X-Customer-Id' => 9999]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Customer not found.');
    });
});

describe('Submit Booking Request', function () {
    it('submits a pending booking request', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $bookingRequest->tasks()->attach($task);
        Quote::factory()->for($bookingRequest)->create();

        $response = $this->postJson(
            "/api/customer/booking-requests/{$bookingRequest->id}/submit",
            [],
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');

        // Verify submitted_at is not null
        expect($response->json('data.submitted_at'))->not->toBeNull();

        // Verify database state
        $this->assertDatabaseHas('booking_requests', [
            'id' => $bookingRequest->id,
            'status' => 'submitted',
        ]);
    });

    it('returns 422 when trying to submit already submitted request', function () {
        $customer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->submitted()
            ->create();

        $response = $this->postJson(
            "/api/customer/booking-requests/{$bookingRequest->id}/submit",
            [],
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only pending booking requests can be submitted.');
    });

    it('returns 404 when booking request belongs to another customer', function () {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()
            ->for($otherCustomer)
            ->create(['status' => BookingRequestStatus::PENDING]);

        $response = $this->postJson(
            "/api/customer/booking-requests/{$bookingRequest->id}/submit",
            [],
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Booking request not found.');
    });
});

describe('List Booking Requests', function () {
    it('lists only the authenticated customer booking requests', function () {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        BookingRequest::factory()->count(3)->for($customer)->create();
        BookingRequest::factory()->count(2)->for($otherCustomer)->create();

        $response = $this->getJson('/api/customer/booking-requests', [
            'X-Customer-Id' => $customer->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    it('includes service provider, tasks, and quote in response', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create();
        $bookingRequest->tasks()->attach($task);
        Quote::factory()->for($bookingRequest)->create();

        $response = $this->getJson('/api/customer/booking-requests', [
            'X-Customer-Id' => $customer->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'service_provider' => ['id', 'business_name'],
                        'tasks' => [['id', 'name', 'price']],
                        'quote' => ['id', 'price', 'status'],
                    ],
                ],
            ]);
    });

    it('paginates results with 15 items per page', function () {
        $customer = Customer::factory()->create();
        BookingRequest::factory()->count(20)->for($customer)->create();

        $response = $this->getJson('/api/customer/booking-requests', [
            'X-Customer-Id' => $customer->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);
    });

    it('returns second page of results', function () {
        $customer = Customer::factory()->create();
        BookingRequest::factory()->count(20)->for($customer)->create();

        $response = $this->getJson('/api/customer/booking-requests?page=2', [
            'X-Customer-Id' => $customer->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    });
});

describe('Show Booking Request', function () {
    it('shows a single booking request', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create();
        $bookingRequest->tasks()->attach($task);
        Quote::factory()->for($bookingRequest)->create();

        $response = $this->getJson(
            "/api/customer/booking-requests/{$bookingRequest->id}",
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $bookingRequest->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'service_provider',
                    'tasks',
                    'quote',
                ],
            ]);
    });

    it('returns 404 when booking request belongs to another customer', function () {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()
            ->for($otherCustomer)
            ->create();

        $response = $this->getJson(
            "/api/customer/booking-requests/{$bookingRequest->id}",
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Booking request not found.');
    });
});

describe('Cancel Booking Request', function () {
    it('cancels a pending booking request', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->create(['status' => BookingRequestStatus::PENDING]);
        $bookingRequest->tasks()->attach($task);
        Quote::factory()->for($bookingRequest)->create();

        $response = $this->postJson(
            "/api/customer/booking-requests/{$bookingRequest->id}/cancel",
            [],
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Verify database state
        $this->assertDatabaseHas('booking_requests', [
            'id' => $bookingRequest->id,
            'status' => 'cancelled',
        ]);
    });

    it('returns 422 when trying to cancel already submitted request', function () {
        $customer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->submitted()
            ->create();

        $response = $this->postJson(
            "/api/customer/booking-requests/{$bookingRequest->id}/cancel",
            [],
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only pending booking requests can be cancelled.');
    });

    it('returns 422 when trying to cancel already cancelled request', function () {
        $customer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->cancelled()
            ->create();

        $response = $this->postJson(
            "/api/customer/booking-requests/{$bookingRequest->id}/cancel",
            [],
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only pending booking requests can be cancelled.');
    });

    it('returns 404 when booking request belongs to another customer', function () {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $bookingRequest = BookingRequest::factory()
            ->for($otherCustomer)
            ->create(['status' => BookingRequestStatus::PENDING]);

        $response = $this->postJson(
            "/api/customer/booking-requests/{$bookingRequest->id}/cancel",
            [],
            ['X-Customer-Id' => $customer->id]
        );

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Booking request not found.');
    });

    it('allows same task to be booked after cancellation', function () {
        $customer = Customer::factory()->create();
        $serviceProvider = ServiceProvider::factory()->create();
        $task = Task::factory()->for($serviceProvider)->create();

        // Create and cancel a booking request
        $bookingRequest = BookingRequest::factory()
            ->for($customer)
            ->for($serviceProvider)
            ->cancelled()
            ->create();
        $bookingRequest->tasks()->attach($task);
        Quote::factory()->for($bookingRequest)->create();

        // Should be able to book the same task again
        $response = $this->postJson('/api/customer/booking-requests', [
            'service_provider_id' => $serviceProvider->id,
            'task_ids' => [$task->id],
        ], ['X-Customer-Id' => $customer->id]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    });
});

describe('Service Providers', function () {
    it('lists all service providers', function () {
        ServiceProvider::factory()->count(3)->create();

        $response = $this->getJson('/api/service-providers');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    it('lists tasks for a service provider', function () {
        $serviceProvider = ServiceProvider::factory()->create();
        Task::factory()->count(4)->for($serviceProvider)->create();

        $response = $this->getJson("/api/service-providers/{$serviceProvider->id}/tasks");

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price', 'service_provider_id'],
                ],
            ]);
    });
});
