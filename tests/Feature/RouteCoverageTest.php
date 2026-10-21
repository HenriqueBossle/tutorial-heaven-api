<?php

namespace Tests\Feature;

use App\Models\Article;
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

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/articles', [
                'content' => base64_encode('Article created through route'),
            ])
            ->assertCreated()
            ->assertJsonPath('content', base64_encode('Article created through route'))
            ->assertJsonPath('user_id', $admin->id);

        $this->assertDatabaseHas('articles', [
            'content' => 'Article created through route',
            'id_user' => $admin->id,
        ]);
    }

    public function test_articles_update_route_updates_an_article_for_admin_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $article = Article::create([
            'id_user' => $admin->id,
            'content' => 'Original content',
        ]);

        $this->withHeaders($this->authHeaders($admin))
            ->putJson('/articles/'.$article->getKey(), [
                'content' => base64_encode('Updated content'),
            ])
            ->assertOk()
            ->assertJsonPath('content', base64_encode('Updated content'));

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'content' => 'Updated content',
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
