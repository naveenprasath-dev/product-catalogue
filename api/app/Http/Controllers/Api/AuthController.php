<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auth\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** Shop customer auth (Sanctum SPA cookie auth on the shared "web" session). */
final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );
        $request->session()->regenerate();

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        $user = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        ) ?? throw ValidationException::withMessages(['email' => __('auth.failed')]);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user auth:sanctum guarantees a user */
        $user = $request->user();

        return new UserResource($user);
    }
}
