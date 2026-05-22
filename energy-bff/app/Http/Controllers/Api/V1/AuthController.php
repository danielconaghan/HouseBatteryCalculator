<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_CREDENTIALS',
                    'message' => 'The provided credentials are incorrect.',
                    'context' => [],
                ],
            ], 401);
        }

        $token = $user->createToken('ui')->plainTextToken;

        return response()->json([
            'data' => ['token' => $token],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'data' => ['message' => 'Logged out.'],
        ]);
    }
}
