<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleStoreRequest;
use App\Http\Requests\ArticleUpdateRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate as FacadesGate;
use Laravel\Mcp\Request;

class ArticleController extends Controller
{

    public function index(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $search = $request->query('search');

        $articles = Article::query()
            ->when($search, function ($query, $search) {
                $query->where('content', 'like', "%{$search}%");
            })
        ->get();

        $articles = Article::with('user')->latest()->paginate(10);

        return ArticleResource::collection($articles);
    }
    public function store(ArticleStoreRequest $request)
    {
        FacadesGate::authorize('create', Article::class);
        $article = Article::create($request->validated());
        $article->load('user');

        return response()->json(new ArticleResource($article), 201);
    }

    public function show(Article $article)
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $article->load('user');

        return response()->json(new ArticleResource($article));
    }

    public function update(ArticleUpdateRequest $request, Article $article): JsonResponse {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        FacadesGate::authorize('update', Article::class);


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

        FacadesGate::authorize('destroy', Article::class);


        $article->delete();

        return response()->json(null, 204);
    }


}