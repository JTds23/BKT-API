<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequestRequest;
use App\Http\Resources\BookingRequestResource;
use App\Models\BookingRequest;
use App\Models\Customer;
use App\Services\BookingRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerBookingController extends Controller
{
    public function __construct(
        private readonly BookingRequestService $bookingRequestService
    ) {}

    /**
     * List all booking requests for the authenticated customer.
     * 
     * @param Request $request
     * 
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Customer $customer */
        $customer = $request->get('customer');

        $bookingRequests = $customer->bookingRequests()
            ->with(['serviceProvider', 'tasks', 'quote'])
            ->latest()
            ->paginate(15);

        return BookingRequestResource::collection($bookingRequests);
    }

    /**
     * Show a specific booking request.
     * 
     * @param Request $request
     * @param BookingRequest $bookingRequest
     * 
     * @return BookingRequestResource|JsonResponse
     */
    public function show(Request $request, BookingRequest $bookingRequest): BookingRequestResource|JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->get('customer');

        // Ensure customer owns this booking request
        if ($bookingRequest->customer_id !== $customer->id) {
            return response()->json(['message' => 'Booking request not found.'], 404);
        }

        $bookingRequest->load(['serviceProvider', 'tasks', 'quote']);

        return new BookingRequestResource($bookingRequest);
    }

    /**
     * Create a new booking request.
     * 
     * @param StoreBookingRequestRequest $request
     * @return JsonResponse
     * 
     * @throws \App\Exceptions\DuplicateTaskException
     */
    public function store(StoreBookingRequestRequest $request): JsonResponse
    {
        $customer = $request->get('customer');

        $bookingRequest = $this->bookingRequestService->createBookingRequest(
            customer: $customer,
            serviceProviderId: $request->validated('service_provider_id'),
            taskIds: $request->validated('task_ids')
        );

        return (new BookingRequestResource($bookingRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Submit a pending booking request.
     * 
     * @param Request $request
     * @param BookingRequest $bookingRequest
     * 
     * @return BookingRequestResource|JsonResponse
     */
    public function submit(Request $request, BookingRequest $bookingRequest): BookingRequestResource|JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->get('customer');

        // Ensure customer owns this booking request
        if ($bookingRequest->customer_id !== $customer->id) {
            return response()->json(['message' => 'Booking request not found.'], 404);
        }

        if (!$bookingRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending booking requests can be submitted.',
            ], 422);
        }

        $bookingRequest = $this->bookingRequestService->submitBookingRequest($bookingRequest);

        return new BookingRequestResource($bookingRequest);
    }

    /**
     * Cancel a pending booking request.
     * 
     * @param Request $request
     * @param BookingRequest $bookingRequest
     * 
     * @return BookingRequestResource|JsonResponse
     */
    public function cancel(Request $request, BookingRequest $bookingRequest): BookingRequestResource|JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->get('customer');

        // Ensure customer owns this booking request
        if ($bookingRequest->customer_id !== $customer->id) {
            return response()->json(['message' => 'Booking request not found.'], 404);
        }

        if (!$bookingRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending booking requests can be cancelled.',
            ], 422);
        }

        $bookingRequest = $this->bookingRequestService->cancelBookingRequest($bookingRequest);

        return new BookingRequestResource($bookingRequest);
    }
}
