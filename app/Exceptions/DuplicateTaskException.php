<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DuplicateTaskException extends Exception
{
    public function __construct(
        public readonly array $duplicateTaskIds,
        public readonly array $duplicateTaskNames = [],
        string $message = 'Cannot add tasks that already exist in other pending booking requests'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $response = [
            'message' => $this->getMessage(),
            'duplicate_task_ids' => $this->duplicateTaskIds,
        ];

        if (!empty($this->duplicateTaskNames)) {
            $response['duplicate_task_names'] = $this->duplicateTaskNames;
        }

        return response()->json($response, 409);
    }
}
