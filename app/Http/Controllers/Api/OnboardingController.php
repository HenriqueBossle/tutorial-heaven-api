<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncUserTagsRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function syncTags(SyncUserTagsRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        // Sincroniza as tags do onboarding com a tabela pivô tag_user
        $user->tags()->sync($request->validated('tag_ids'));

        return response()->json([
            'success' => true,
            'message' => 'Tags do onboarding registradas com sucesso!',
            'data' => [
                'user' => (new UserResource($user->load('tags')))->toArray($request),
            ],
        ], 200);
    }
}
