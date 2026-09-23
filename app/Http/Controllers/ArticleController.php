<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleStoreRequest;
use App\Http\Requests\ArticleUpdateRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ArticleController extends Controller
{

    public function index()
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $articles = Article::with('user')->latest()->paginate(10);

        return ArticleResource::collection($articles);
    }
    public function store(ArticleStoreRequest $request)
    {
        $article = Article::create($request->validated());
        $article->load('user');

        return response()->json(new ArticleResource($article), 201);
    }

    public function update(ArticleUpdateRequest $request, Article $article): JsonResponse {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $article->update($request->validated());
        $article->load('user');

        return response()->json(new ArticleResource($article));
    }

    public function destroy(Article $article): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $article->delete();

        return response()->json(null, 204);
    }


}