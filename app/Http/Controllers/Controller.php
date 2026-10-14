<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * @OA\Info(
     *   title="API FPI ",
     *   version="1.0",
     *   @OA\Contact(
     *     email="viva@gmail.com",
     *     name="Support Team"
     *   )
     * )
     * @OA\SecurityScheme(
     *     securityScheme="bearerAuth",
     *     type="http",
     *     scheme="bearer",
     *     bearerFormat="JWT"
     * )
     */

    public function sendResponse($result, $message, $code = 200)
    {
        if ($code == 200) {
            $response = [
                'status'        => 'success',
                'data'          => $result,
                'status_code'   => $code,
                'message'       => $message,
                'response_time' => (float)microtime() - (float)LARAVEL_START
            ];
        } else {
            $response = [
                'status'        => 'error',
                'data'          => $result,
                'status_code'   => $code,
                'message'       => $message,
                'response_time' => (float)microtime() - (float)LARAVEL_START
            ];
        }

        return response()->json($response, $code);
    }


    /**
     * method untuk error response.
     *
     */
    public function sendError($error, $errorMessages = [], $code = 500)
    {
        $response = [
            'status' => 'error',
            'status_code'   => $code,
            'message' => $error,
            'response_time' => (float)microtime() - (float)LARAVEL_START
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    public function collectionPaginate($items, $perPage = 15, $page = null, $options = [])
    {
        $page = $page ?: (\Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof \Illuminate\Support\Collection ? $items : \Illuminate\Support\Collection::make($items);
        return new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}