<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\QuoteStatus;
use App\Exceptions\DuplicateTaskException;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class BookingRequestService
{
    /**
     * Create a new booking request with tasks.
     *
     * @param Customer $customer
     * @param int $serviceProviderId
     * @param array $taskIds
     * 
     * @throws DuplicateTaskException
     */
    public function createBookingRequest(
        Customer $customer,
        int $serviceProviderId,
        array $taskIds
    ): BookingRequest {
        return DB::transaction(function () use ($customer, $serviceProviderId, $taskIds) {
            $existingPendingTaskIds = DB::table('booking_requests')
                ->join('booking_request_tasks', 'booking_requests.id', '=', 'booking_request_tasks.booking_request_id')
                ->where('booking_requests.customer_id', $customer->id)
                ->where('booking_requests.status', BookingRequestStatus::PENDING->value)
                ->lockForUpdate()
                ->pluck('booking_request_tasks.task_id')
                ->toArray();

            $duplicateTaskIds = array_values(array_intersect($taskIds, $existingPendingTaskIds));

            if (!empty($duplicateTaskIds)) {
                $duplicateTaskNames = Task::whereIn('id', $duplicateTaskIds)
                    ->pluck('name')
                    ->toArray();

                throw new DuplicateTaskException($duplicateTaskIds, $duplicateTaskNames);
            }

            $bookingRequest = BookingRequest::create([
                'customer_id' => $customer->id,
                'service_provider_id' => $serviceProviderId,
                'status' => BookingRequestStatus::PENDING,
            ]);

            $bookingRequest->tasks()->attach($taskIds);

            $totalPrice = Task::whereIn('id', $taskIds)->sum('price');

            Quote::create([
                'booking_request_id' => $bookingRequest->id,
                'price' => $totalPrice,
                'status' => QuoteStatus::GENERATED,
            ]);

            $bookingRequest->load(['tasks', 'quote', 'serviceProvider']);

            return $bookingRequest;
        });
    }

    /**
     * Submit a pending booking request.
     *
     * @param BookingRequest $bookingRequest
     *
     * @throws \InvalidArgumentException
     */
    public function submitBookingRequest(BookingRequest $bookingRequest): BookingRequest
    {
        if (!$bookingRequest->isPending()) {
            throw new \InvalidArgumentException('Only pending booking requests can be submitted.');
        }

        $bookingRequest->update([
            'status' => BookingRequestStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        return $bookingRequest->fresh(['tasks', 'quote', 'serviceProvider']);
    }

    /**
     * Cancel a pending booking request.
     *
     * @param BookingRequest $bookingRequest
     *
     * @throws \InvalidArgumentException
     */
    public function cancelBookingRequest(BookingRequest $bookingRequest): BookingRequest
    {
        if (!$bookingRequest->isPending()) {
            throw new \InvalidArgumentException('Only pending booking requests can be cancelled.');
        }

        $bookingRequest->update([
            'status' => BookingRequestStatus::CANCELLED,
        ]);

        return $bookingRequest->fresh(['tasks', 'quote', 'serviceProvider']);
    }
}
