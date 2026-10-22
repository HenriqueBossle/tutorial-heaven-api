<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticleTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test-token')->plainTextToken,
        ];
    }

    protected function createTag(string $name, string $category = 'area'): Tag
    {
        return Tag::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $category,
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_articles_index_route_can_filter_articles_by_the_tags_of_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $frontend = $this->createTag('Frontend');
        $backend = $this->createTag('Backend', 'technology');

        $frontendArticle = Article::create(['id_user' => $user->id, 'content' => 'Artigo de frontend']);
        $frontendArticle->tags()->attach($frontend);

        $backendArticle = Article::create(['id_user' => $user->id, 'content' => 'Artigo de backend']);
        $backendArticle->tags()->attach($backend);

        $untaggedArticle = Article::create(['id_user' => $user->id, 'content' => 'Artigo sem tag']);

        $user->tags()->attach($frontend);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/articles?tags=mine')
            ->assertOk()
            ->assertJsonPath('data.0.id', $frontendArticle->getKey())
            ->assertJsonPath('data.0.tags.0.name', 'Frontend')
            ->assertJsonMissing(['id' => $backendArticle->getKey()])
            ->assertJsonMissing(['id' => $untaggedArticle->getKey()]);
    }

    public function test_articles_index_route_returns_every_article_without_the_tag_filter(): void
    {
        $user = User::factory()->create();
        $frontend = $this->createTag('Frontend');

        $tagged = Article::create(['id_user' => $user->id, 'content' => 'Artigo com tag']);
        $tagged->tags()->attach($frontend);

        Article::create(['id_user' => $user->id, 'content' => 'Artigo sem tag']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/articles')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_articles_index_route_returns_no_articles_when_the_user_has_no_tags(): void
    {
        $user = User::factory()->create();
        $frontend = $this->createTag('Frontend');

        $article = Article::create(['id_user' => $user->id, 'content' => 'Artigo com tag']);
        $article->tags()->attach($frontend);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/articles?tags=mine')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_articles_store_route_persists_the_selected_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $frontend = $this->createTag('Frontend');
        $laravel = $this->createTag('PHP / Laravel', 'technology');

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/articles', [
                'content' => base64_encode('Artigo com tags'),
                'tag_ids' => [$frontend->id, $laravel->id],
            ])
            ->assertCreated()
            ->assertJsonPath('tags.0.name', 'Frontend')
            ->assertJsonCount(2, 'tags');

        $this->assertDatabaseHas('article_tag', [
            'article_id' => Article::query()->firstOrFail()->getKey(),
            'tag_id' => $frontend->id,
        ]);
    }

    public function test_articles_store_route_rejects_unknown_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/articles', [
                'content' => base64_encode('Artigo com tag inválida'),
                'tag_ids' => [999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.0');
    }

    public function test_articles_update_route_replaces_the_selected_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $frontend = $this->createTag('Frontend');
        $backend = $this->createTag('Backend', 'technology');

        $article = Article::create(['id_user' => $admin->id, 'content' => 'Artigo editável']);
        $article->tags()->attach($frontend);

        $this->withHeaders($this->authHeaders($admin))
            ->putJson('/articles/'.$article->getKey(), [
                'content' => base64_encode('Artigo editável'),
                'tag_ids' => [$backend->id],
            ])
            ->assertOk()
            ->assertJsonPath('tags.0.name', 'Backend')
            ->assertJsonCount(1, 'tags');

        $this->assertDatabaseMissing('article_tag', [
            'article_id' => $article->getKey(),
            'tag_id' => $frontend->id,
        ]);
    }
}
