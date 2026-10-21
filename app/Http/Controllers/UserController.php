<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return UserResource::collection(User::query()->with('tags')->latest()->paginate(10));
    }

    public function show(Request $request, User $user): UserResource|JsonResponse
    {
        if (! $request->user()->isAdmin() && $request->user()->isNot($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return new UserResource($user->load('tags'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user->refresh()->load('tags'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
