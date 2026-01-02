# BKT - Booking Request API

A Laravel REST API for managing customer booking requests to service providers for home services (lawn mowing, oven cleaning, tree trimming, etc.).

## Features

- Customers can create booking requests with one or more tasks
- Automatic quote generation with price calculation
- Duplicate task prevention (customers cannot have the same task in multiple pending requests)
- Service provider and task browsing
- Booking request submission workflow

## Requirements

- PHP 8.2+
- Composer 2.x
- MySQL

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd BKT-API

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed the database with sample data
php artisan db:seed
```

## Running the Application

```bash
# Start the development server
php artisan serve

# The API will be available at http://localhost:8000
```

## Running Tests

```bash
# Run all tests
composer test

# Or directly with artisan
php artisan test
```

## API Endpoints

### Public Endpoints

#### List Service Providers
```http
GET /api/service-providers
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "business_name": "CleanPro Services",
      "created_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

#### List Tasks for a Service Provider
```http
GET /api/service-providers/{id}/tasks
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Oven Cleaning",
      "price": "55.00",
      "service_provider_id": 1
    }
  ]
}
```

### Protected Endpoints (Require Authentication)

All `/api/customer/*` endpoints require the `X-Customer-Id` header (see [Authentication](#authentication)).

#### List Customer's Booking Requests (Paginated)
```http
GET /api/customer/booking-requests
GET /api/customer/booking-requests?page=2
```

**Headers:**
```
X-Customer-Id: 1
```

**Query Parameters:**
- `page` (optional): Page number for pagination (default: 1, 15 items per page)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "status": "pending",
      "status_label": "Pending",
      "submitted_at": null,
      "created_at": "2024-01-01T12:00:00.000000Z",
      "updated_at": "2024-01-01T12:00:00.000000Z",
      "service_provider": {
        "id": 1,
        "business_name": "CleanPro Services"
      },
      "tasks": [
        {
          "id": 1,
          "name": "Oven Cleaning",
          "price": "55.00"
        }
      ],
      "quote": {
        "id": 1,
        "price": "55.00",
        "status": "generated",
        "status_label": "Generated"
      }
    }
  ],
  "links": {
    "first": "http://localhost/api/customer/booking-requests?page=1",
    "last": "http://localhost/api/customer/booking-requests?page=2",
    "prev": null,
    "next": "http://localhost/api/customer/booking-requests?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 20
  }
}
```

#### Create Booking Request
```http
POST /api/customer/booking-requests
```

**Headers:**
```
X-Customer-Id: 1
Content-Type: application/json
```

**Request Body:**
```json
{
  "service_provider_id": 1,
  "task_ids": [1, 2, 3]
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "quote": {
      "price": "90.00"
    }
  }
}
```

**Error Response (409 Conflict - Duplicate Tasks):**
```json
{
  "message": "You already have pending booking requests containing some of these tasks.",
  "duplicate_task_ids": [1, 3],
  "duplicate_task_names": ["Oven Cleaning", "Window Cleaning"]
}
```

**Error Response (422 Validation Error):**
```json
{
  "message": "The service provider id field is required.",
  "errors": {
    "service_provider_id": ["The service provider id field is required."]
  }
}
```

#### Show Booking Request
```http
GET /api/customer/booking-requests/{id}
```

**Headers:**
```
X-Customer-Id: 1
```

#### Submit Booking Request
```http
POST /api/customer/booking-requests/{id}/submit
```

**Headers:**
```
X-Customer-Id: 1
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "status": "submitted",
    "submitted_at": "2024-01-01T14:30:00.000000Z"
  }
}
```

**Error Response (422 - Already Submitted):**
```json
{
  "message": "Only pending booking requests can be submitted."
}
```

#### Cancel Booking Request
```http
POST /api/customer/booking-requests/{id}/cancel
```

**Headers:**
```
X-Customer-Id: 1
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "status": "cancelled"
  }
}
```

## Authentication

For demonstration purposes, this API uses a simplified header-based authentication system. Include the customer's ID in the `X-Customer-Id` header for all protected endpoints.

```bash
curl -X GET http://localhost:8000/api/customer/booking-requests \
  -H "X-Customer-Id: 1"
```

**Note:** In a production environment, this would be replaced with a proper authentication system (JWT, OAuth2, Laravel Sanctum, etc.). The current implementation demonstrates the authorization logic while keeping the focus on the booking request functionality.

## Database Schema

```
customers
├── id (PK)
├── name
└── timestamps

service_providers
├── id (PK)
├── business_name
└── timestamps

tasks
├── id (PK)
├── service_provider_id (FK → service_providers)
├── name
├── price (decimal 10,2)
└── timestamps

booking_requests
├── id (PK)
├── customer_id (FK → customers)
├── service_provider_id (FK → service_providers)
├── status (enum: pending, submitted, cancelled)
├── submitted_at
└── timestamps

booking_request_tasks (pivot)
├── id (PK)
├── booking_request_id (FK → booking_requests)
├── task_id (FK → tasks)
└── timestamps
└── UNIQUE(booking_request_id, task_id)

quotes
├── id (PK)
├── booking_request_id (FK → booking_requests, UNIQUE)
├── price (decimal 10,2)
├── status (enum: generated, accepted, rejected)
└── timestamps
```

## Assumptions

The following assumptions were made during implementation:

1. **Authentication is pre-handled**: The spec states we can assume the customer is authenticated. This implementation uses a demo `X-Customer-Id` header to simulate an authenticated customer context.

2. **Single service provider per booking request**: Each booking request is associated with one service provider, and all tasks in that request must belong to that provider.

3. **Tasks have fixed pricing**: Task prices are set by service providers and do not vary based on quantity, time, or other factors.

4. **No task quantity**: Each task can only appear once per booking request (enforced by unique constraint).

5. **Booking requests are immutable after creation**: Once created, tasks cannot be added or removed from a booking request. Customers must cancel and create a new request.

6. **Customer data isolation**: Customers can only view and manage their own booking requests. Attempting to access another customer's data returns 404 (not 403) for security through obscurity.

## Design Decisions

### 1. Quote Generated at Creation (Deviation from Spec)

The specification states: *"When the customer submits a booking request, the system will generate a quote."*

**Implementation:** Quotes are generated immediately when the booking request is created, not when submitted.

**Justification:** This provides a better user experience as customers can see the total price before committing to submit. The "submit" action then becomes a confirmation step. This is more aligned with real-world booking flows where price visibility is important for customer decision-making.

### 2. Quote Relationship Direction (Deviation from Spec)

The specification shows `quoteId` as a field on `BookingRequest`.

**Implementation:** The `Quote` model has a `booking_request_id` foreign key instead.

**Justification:** This is a more normalized database design. Since a quote cannot exist without a booking request, the foreign key belongs on the dependent entity (Quote). This also allows for potential future features like quote versioning or multiple quote proposals.

### 3. Application-Level Duplicate Task Enforcement

The duplicate task rule (customers cannot have the same task in multiple pending requests) is enforced at the application layer using database transactions and `SELECT FOR UPDATE` locking.

**Justification:** While database triggers could enforce this, application-level enforcement provides:
- Better error messages (can return specific duplicate task IDs)
- Easier testing and debugging
- Database portability
- The locking mechanism prevents race conditions in concurrent requests

### 4. Snake_case Column Naming

The specification uses camelCase (`businessName`, `serviceProviderId`).

**Implementation:** Uses Laravel's snake_case convention (`business_name`, `service_provider_id`).

**Justification:** Following Laravel/Eloquent conventions ensures compatibility with Laravel's automatic attribute handling, relationship resolution, and reduces configuration overhead. The API responses use snake_case for consistency.

### 5. Added Status Fields

Additional fields not in the original spec:
- `booking_requests.status` - Tracks workflow state (pending → submitted → cancelled)
- `booking_requests.submitted_at` - Timestamp of submission
- `quotes.status` - Tracks quote lifecycle (generated → accepted/rejected)

**Justification:** These fields support a complete booking workflow and provide audit capability. The status enums allow for future workflow extensions.

## Architecture

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── CustomerBookingController.php  # Booking request CRUD
│   │   └── ServiceProviderController.php  # SP and task listing
│   ├── Middleware/
│   │   └── CustomerAuthMiddleware.php     # Demo authentication
│   ├── Requests/
│   │   └── StoreBookingRequestRequest.php # Input validation
│   └── Resources/                         # JSON response formatting
├── Models/                                # Eloquent models with relationships
├── Services/
│   └── BookingRequestService.php          # Business logic layer
├── Enums/                                 # Status enumerations
└── Exceptions/
    └── DuplicateTaskException.php         # Custom exception for 409 responses
```

**Separation of Concerns:**
- **Controllers:** Handle HTTP request/response, delegate to services
- **Services:** Contain business logic, database transactions
- **Form Requests:** Input validation and authorization
- **Resources:** Response formatting and data transformation
- **Models:** Data access, relationships, scopes

## Future Considerations

The following features were considered but not implemented to keep the scope focused:

- **API Versioning** (`/api/v1/`) - Would be added before production release
- **Rate Limiting** - Throttle middleware for API protection
- **Real Authentication** - JWT or Laravel Sanctum integration
- **Update Booking Request** - Allow modifying tasks before submission
- **Quote Accept/Reject Endpoints** - Complete the quote workflow
- **Filtering & Sorting** - Query parameters for booking request lists
- **Notifications** - Email/SMS when booking status changes
- **Logging** - Structured logging for booking operations
