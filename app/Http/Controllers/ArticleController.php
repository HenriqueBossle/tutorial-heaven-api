<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleStoreRequest;
use App\Http\Requests\ArticleUpdateRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate as FacadesGate;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $search = $request->query('search');

        $articles = Article::query()
            ->when($search, function ($query, $search) {
                $query->where('content', 'like', "%{$search}%");
            })
            ->with(['user', 'tags'])
            ->latest()
            ->paginate(10);

        return ArticleResource::collection($articles);
    }

    public function store(ArticleStoreRequest $request)
    {
        FacadesGate::authorize('create', Article::class);

        $data = $request->validated();
        $data['id_user'] = $request->user()->id;
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $article = Article::create($data);
        $article->tags()->sync($tagIds);
        $article->load(['user', 'tags']);

        return response()->json(new ArticleResource($article), 201);
    }

    public function show(Article $article)
    {
        if (! Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        FacadesGate::authorize('view', $article);

        $article->load(['user', 'tags']);

        return response()->json(new ArticleResource($article));
    }

    public function update(ArticleUpdateRequest $request, Article $article): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        FacadesGate::authorize('update', $article);

        $data = $request->validated();
        $article->update(['content' => $data['content']]);

        if (array_key_exists('tag_ids', $data)) {
            $article->tags()->sync($data['tag_ids']);
        }

        $article->load(['user', 'tags']);

        return response()->json(new ArticleResource($article));
    }

    public function destroy(Article $article): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        FacadesGate::authorize('delete', $article);

        $article->delete();

        return response()->json(null, 204);
    }
}
