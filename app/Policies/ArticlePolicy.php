<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    /**
     * Pode visualizar a lista de artigos.
     */
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    /**
     * Pode visualizar um artigo específico.
     */
    public function view(User $user, Article $article): bool
    {
        return $user !== null;
    }

    /**
     * Pode criar artigos.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Pode editar artigos.
     */
    public function update(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }

    /**
     * Pode excluir artigos.
     */
    public function delete(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }

}