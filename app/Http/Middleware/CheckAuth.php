<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

    public function handle(Request $request, Closure $next)
    {
        // Ambil token dari header Authorization
        $token = $request->bearerToken();

        if (!$token) {
            $response = [
                'status' => 'error',
                'status_code'   => 401,
                'message' => 'Unauthorized',
                'response_time' => (float)microtime() - (float)LARAVEL_START
            ];
            return response()->json($response, 401);
        }

        try {
            // Ambil payload token tanpa autentikasi ke DB
            $payload = JWTAuth::setToken($token)->getPayload()->toArray();
            $userData = $payload['users'];

            // lanjutkan ke controller atau response
            $request->merge(['user' => $userData]);
            
            return $next($request);
            
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            $response = [
                'status' => 'error',
                'status_code'   => 401,
                'message' => 'Token tidak valid',
                'response_time' => (float)microtime() - (float)LARAVEL_START
            ];
            return response()->json($response, 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            $response = [
                'status' => 'error',
                'status_code'   => 401,
                'message' => 'Token expired',
                'response_time' => (float)microtime() - (float)LARAVEL_START
            ];
            return response()->json($response, 401);
        } catch (\Exception $e) {
            $response = [
                'status' => 'error',
                'status_code'   => 500,
                'message' => 'Gagal memproses token',
                'response_time' => (float)microtime() - (float)LARAVEL_START
            ];
            return response()->json($response, 500);
        }
    }
}