<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    // Retorna as tags ordenadas por categoria e ordem de exibição
    public function index(): JsonResponse
    {
        $tags = Tag::where('is_active', true)
            ->orderBy('category')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tags
        ], 200);
    }

    // Salva a nova tag
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tag criada com sucesso!',
            'data' => $tag
        ], 201);
    }

    // Retorna as tags em ordem sequencial para montagem do formulário no Front
    public function getFormSteps(): JsonResponse
    {
        $steps = [
            'step_1' => [
                'title' => 'Área de Atuação',
                'category' => 'area',
                'tags' => Tag::where('category', 'area')->where('is_active', true)->get()
            ],
            'step_2' => [
                'title' => 'Tecnologias e Stacks',
                'category' => 'technology',
                'tags' => Tag::where('category', 'technology')->where('is_active', true)->get()
            ],
            'step_3' => [
                'title' => 'Sênioridade / Experiência',
                'category' => 'seniority',
                'tags' => Tag::where('category', 'seniority')->where('is_active', true)->get()
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $steps
        ], 200);
    }
}