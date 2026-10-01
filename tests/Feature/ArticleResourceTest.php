<?php

namespace Tests\Feature;

use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\User;
use Tests\TestCase;

class ArticleResourceTest extends TestCase
{
    public function test_article_resource_serializes_expected_shape(): void
    {
        $user = new User();
        $user->id = 'user-123';
        $user->name = 'João da Silva';

        $article = new Article();
        $article->id_article = 'article-123';
        $article->id_user = 'user-123';
        $article->content = 'Conteúdo do artigo';
        $article->setRelation('user', $user);

        $payload = (new ArticleResource($article))->resolve();

        $this->assertSame('article-123', $payload['id']);
        $this->assertSame('user-123', $payload['user_id']);
        $this->assertSame('Conteúdo do artigo', $payload['content']);
        $this->assertSame('João da Silva', $payload['user']['name']);
    }
}
