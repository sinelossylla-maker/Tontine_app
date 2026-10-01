<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ValidateDjangoJwt
 *
 * Validates incoming HTTP requests containing a shared JWT Bearer token issued by
 * the Django REST Framework authentication microservice (SimpleJWT).
 *
 * Key Responsibilities:
 * 1. Extract Bearer token from the `Authorization` request header.
 * 2. Decode token using the shared secret key (`config('jwt.secret')`) and algorithm (`HS256`).
 * 3. Handle token expiration (`ExpiredException`) and signature tampering (`SignatureInvalidException`).
 * 4. Verify KYC compliance (`is_kyc_verified == true`), rejecting unverified accounts with 403 Forbidden.
 * 5. Inject decoded user claims and user phone identifier into request attributes.
 *
 * @package App\Http\Middleware
 */
class ValidateDjangoJwt
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming HTTP request object.
     * @param  Closure(Request): (Response)  $next  The next middleware callback in the HTTP pipeline.
     * @return Response Returns 401 Unauthorized, 403 Forbidden, or forwards to next request handler.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorizationHeader = $request->header('Authorization');

        // Step 1: Check Authorization header presence and Bearer scheme
        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization header missing or invalid format. Bearer token required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($authorizationHeader, 7);

        // Step 2: Decode and verify JWT signature using shared secret
        try {
            $secret = config('jwt.secret');
            $algo = config('jwt.algo', 'HS256');

            $decoded = JWT::decode($token, new Key($secret, $algo));
        } catch (ExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired.',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (SignatureInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token signature.',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication token: ' . $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Step 3: Enforce KYC verification check (is_kyc_verified claim)
        $isKycVerified = isset($decoded->is_kyc_verified) ? (bool) $decoded->is_kyc_verified : false;

        if (!$isKycVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Your KYC must be verified to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Step 4: Extract user phone number or fallback identifier
        $userPhone = $decoded->phone ?? $decoded->phone_number ?? $decoded->user_id ?? $decoded->sub ?? null;

        if (!$userPhone) {
            return response()->json([
                'success' => false,
                'message' => 'User identifier missing from JWT payload.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Step 5: Bind JWT claims to request attributes for downstream controllers
        $request->attributes->set('jwt_payload', $decoded);
        $request->attributes->set('auth_user_phone', (string) $userPhone);

        return $next($request);
    }
}
