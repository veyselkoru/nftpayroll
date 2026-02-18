<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function ok(mixed $data = null, string $message = 'OK', array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
            'message' => $message,
        ]);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass, string $message = 'OK', array $extraMeta = []): JsonResponse
    {
        /** @var class-string<JsonResource> $resourceClass */
        $data = $resourceClass::collection($paginator->items());

        return response()->json([
            'data' => $data,
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ], $extraMeta),
            'message' => $message,
        ]);
    }
}
