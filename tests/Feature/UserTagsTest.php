<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTagsTest extends TestCase
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

    public function test_onboarding_sync_route_assigns_the_selected_tags_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $frontend = $this->createTag('Frontend');
        $laravel = $this->createTag('PHP / Laravel', 'technology');

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/onboarding/tags', [
                'tag_ids' => [$frontend->id, $laravel->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.tags.0.name', 'Frontend');

        $this->assertDatabaseHas('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $frontend->id,
        ]);

        $this->assertDatabaseHas('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $laravel->id,
        ]);
    }

    public function test_onboarding_sync_route_replaces_the_previously_selected_tags(): void
    {
        $user = User::factory()->create();
        $frontend = $this->createTag('Frontend');
        $backend = $this->createTag('Backend');

        $headers = $this->authHeaders($user);

        $this->withHeaders($headers)
            ->postJson('/onboarding/tags', ['tag_ids' => [$frontend->id]])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/onboarding/tags', ['tag_ids' => [$backend->id]])
            ->assertOk();

        $this->assertDatabaseMissing('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $frontend->id,
        ]);

        $this->assertDatabaseHas('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $backend->id,
        ]);
    }

    public function test_onboarding_sync_route_requires_authentication(): void
    {
        $this->postJson('/onboarding/tags', ['tag_ids' => [1]])
            ->assertUnauthorized();
    }

    public function test_onboarding_sync_route_rejects_unknown_tags(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/onboarding/tags', ['tag_ids' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.0');
    }

    public function test_user_route_returns_the_tags_assigned_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $frontend = $this->createTag('Frontend');
        $user->tags()->attach($frontend);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('tags.0.id', $frontend->id)
            ->assertJsonPath('tags.0.name', 'Frontend');
    }

    public function test_user_route_returns_an_empty_tag_list_when_the_user_has_no_tags(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/user')
            ->assertOk()
            ->assertJsonPath('tags', []);
    }

    public function test_users_show_route_returns_the_tags_of_the_user(): void
    {
        $user = User::factory()->create();
        $backend = $this->createTag('Backend');
        $user->tags()->attach($backend);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/users/'.$user->getKey())
            ->assertOk()
            ->assertJsonPath('data.tags.0.name', 'Backend');
    }

    public function test_login_route_returns_the_tags_assigned_to_the_user(): void
    {
        $user = User::factory()->create();
        $frontend = $this->createTag('Frontend');
        $user->tags()->attach($frontend);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.tags.0.name', 'Frontend');
    }
}
