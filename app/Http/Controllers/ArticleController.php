<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleStoreRequest;
use App\Http\Requests\ArticleUpdateRequest;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ArticleController extends Controller
{

    public function index(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $articles = Article::latest()->paginate(10);

        return response()->json([
            'data' => $articles,
        ]);
    }
    public function store(ArticleStoreRequest $request)
    {
        $article = Article::create($request->validated());

        return response()->json($article, 201);
    }

    public function update(ArticleUpdateRequest $request, Article $article): JsonResponse {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $article->update($request->validated());

        return response()->json($article);
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