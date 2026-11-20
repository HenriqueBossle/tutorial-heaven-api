<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RouteCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test-token')->plainTextToken,
        ];
    }

    public function test_user_route_requires_authentication(): void
    {
        $this->getJson('/user')
            ->assertUnauthorized();
    }

    public function test_login_route_returns_a_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_register_route_creates_a_user_and_returns_a_token(): void
    {
        $this->postJson('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJsonPath('user.email', 'jane@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
        ]);
    }

    public function test_logout_route_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');
    }

    public function test_articles_index_route_returns_paginated_articles_for_an_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Article::create([
            'id_user' => $user->id,
            'content' => 'Article one',
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/articles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'user_id', 'content', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonPath('data.0.content', base64_encode('Article one'));
    }

    public function test_articles_show_route_returns_a_single_article_for_an_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $article = Article::create([
            'id_user' => $user->id,
            'content' => 'Single article',
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/articles/'.$article->getKey())
            ->assertOk()
            ->assertJsonPath('content', base64_encode('Single article'))
            ->assertJsonPath('user_id', $user->id);
    }

    public function test_articles_store_route_creates_an_article_for_admin_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
            'is_active' => true,
        ]);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/articles', [
                'content' => base64_encode('Article created through route'),
                'tag_ids' => [$tag->id],
            ])
            ->assertCreated()
            ->assertJsonPath('content', base64_encode('Article created through route'))
            ->assertJsonPath('user_id', $admin->id)
            ->assertJsonPath('tags.0.id', $tag->id)
            ->assertJsonPath('tags.0.name', 'Laravel');

        $this->assertDatabaseHas('articles', [
            'content' => 'Article created through route',
            'id_user' => $admin->id,
        ]);
        $this->assertDatabaseHas('article_tag', [
            'article_id' => Article::where('content', 'Article created through route')->firstOrFail()->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_articles_update_route_updates_an_article_for_admin_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = Article::create([
            'id_user' => $admin->id,
            'content' => 'Original content',
        ]);
        $oldTag = Tag::create([
            'name' => 'React',
            'slug' => 'react',
            'category' => 'technology',
            'is_active' => true,
        ]);
        $newTag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'category' => 'technology',
            'is_active' => true,
        ]);
        $article->tags()->attach($oldTag);

        $this->withHeaders($this->authHeaders($admin))
            ->putJson('/articles/'.$article->getKey(), [
                'content' => base64_encode('Updated content'),
                'tag_ids' => [$newTag->id],
            ])
            ->assertOk()
            ->assertJsonPath('content', base64_encode('Updated content'))
            ->assertJsonPath('tags.0.id', $newTag->id)
            ->assertJsonPath('tags.0.name', 'PHP');

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'content' => 'Updated content',
        ]);
        $this->assertDatabaseHas('article_tag', [
            'article_id' => $article->id,
            'tag_id' => $newTag->id,
        ]);
        $this->assertDatabaseMissing('article_tag', [
            'article_id' => $article->id,
            'tag_id' => $oldTag->id,
        ]);
    }

    public function test_articles_update_route_can_remove_all_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tag = Tag::create([
            'name' => 'Backend',
            'slug' => 'backend',
            'category' => 'area',
            'is_active' => true,
        ]);
        $article = Article::create([
            'id_user' => $admin->id,
            'content' => 'Original content',
        ]);
        $article->tags()->attach($tag);

        $this->withHeaders($this->authHeaders($admin))
            ->patchJson('/articles/'.$article->getKey(), [
                'content' => base64_encode('Updated content'),
                'tag_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('tags', []);

        $this->assertDatabaseCount('article_tag', 0);
    }

    public function test_articles_store_route_rejects_unknown_tag_ids(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/articles', [
                'content' => base64_encode('Article with unknown tag'),
                'tag_ids' => [999999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.0');

        $this->assertDatabaseMissing('articles', [
            'content' => 'Article with unknown tag',
        ]);
    }

    public function test_articles_destroy_route_deletes_an_article_for_admin_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = Article::create([
            'id_user' => $admin->id,
            'content' => 'Article to delete',
        ]);

        $this->withHeaders($this->authHeaders($admin))
            ->deleteJson('/articles/'.$article->getKey())
            ->assertNoContent();

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);
    }

    public function test_article_content_round_trips_as_base64_encoded_utf8(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $markdown = "# Olá, mundo! 👋\n\nConteúdo com ação e coração 💙.";

        $response = $this->withHeaders($this->authHeaders($admin))
            ->postJson('/articles', [
                'content' => base64_encode($markdown),
            ])
            ->assertCreated()
            ->assertJsonPath('content', base64_encode($markdown));

        $this->assertDatabaseHas('articles', [
            'id' => $response->json('id'),
            'content' => $markdown,
        ]);
    }

    public function test_article_content_rejects_invalid_base64_or_utf8(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/articles', [
                'content' => '%%%not-base64%%%',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }
}
