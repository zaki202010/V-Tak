<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

abstract class ApiController extends Controller
{
    protected function ok($data = null, string $message = 'OK', int $code = 200)
    {
        return response()->json(['status' => 'success', 'message' => $message, 'data' => $data], $code);
    }

    protected function fail(string $message, int $code = 400, $errors = null)
    {
        $body = ['status' => 'error', 'message' => $message, 'data' => null];
        if ($errors) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $code);
    }

    protected function paginated($paginator, ?callable $map = null)
    {
        $items = collect($paginator->items());
        if ($map) {
            $items = $items->map($map);
        }

        return $this->ok([
            'items' => $items->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
