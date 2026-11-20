<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTagsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_can_sync_tags_and_read_them_from_user_tags_route(): void
    {
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);

        $registration = $this->postJson('/api/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $headers = [
            'Authorization' => 'Bearer '.$registration->json('token'),
        ];
        $userId = $registration->json('user.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/onboarding/tags', [
                'tag_ids' => [$tag->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.tags.0.id', $tag->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/users/'.$userId.'/tags')
            ->assertOk()
            ->assertJsonPath('data.0.id', $tag->id)
            ->assertJsonPath('data.0.name', 'Laravel');

        $this->assertDatabaseHas('tag_user', [
            'user_id' => $userId,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_onboarding_replaces_previous_tags_and_keeps_only_the_new_selection(): void
    {
        $user = User::factory()->create();
        $previousTag = Tag::create([
            'name' => 'React',
            'slug' => 'react',
            'category' => 'technology',
        ]);
        $newTag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);
        $user->tags()->attach($previousTag);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/onboarding/tags', [
                'tag_ids' => [$newTag->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.tags.0.id', $newTag->id);

        $this->assertDatabaseCount('tag_user', 1);
        $this->assertDatabaseHas('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $newTag->id,
        ]);
        $this->assertDatabaseMissing('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $previousTag->id,
        ]);
    }

    public function test_onboarding_rejects_empty_selection_without_changing_pivot_rows(): void
    {
        $user = User::factory()->create();
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);
        $user->tags()->attach($tag);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/onboarding/tags', [
                'tag_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids')
            ->assertJsonPath('errors.tag_ids.0', 'The tag ids field is required.');

        $this->assertDatabaseCount('tag_user', 1);
        $this->assertDatabaseHas('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_user_tags_route_returns_404_when_user_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/users/99999/tags')
            ->assertNotFound()
            ->assertJsonPath('message', 'Usuário não encontrado.');
    }

    public function test_tags_index_returns_only_active_tags_ordered_by_category_and_display_order(): void
    {
        Tag::create([
            'name' => 'Frontend',
            'slug' => 'frontend',
            'category' => 'area',
            'display_order' => 2,
        ]);
        Tag::create([
            'name' => 'Backend',
            'slug' => 'backend',
            'category' => 'area',
            'display_order' => 1,
        ]);
        Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
            'display_order' => 1,
        ]);
        Tag::create([
            'name' => 'Inativa',
            'slug' => 'inativa',
            'category' => 'area',
            'display_order' => 0,
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Backend')
            ->assertJsonPath('data.1.name', 'Frontend')
            ->assertJsonPath('data.2.name', 'Laravel')
            ->assertJsonMissing(['name' => 'Inativa']);
    }

    public function test_tags_steps_route_groups_only_active_tags_by_fixed_step_category(): void
    {
        $areaTag = Tag::create([
            'name' => 'Backend',
            'slug' => 'backend',
            'category' => 'area',
        ]);
        $technologyTag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);
        Tag::create([
            'name' => 'Sênior',
            'slug' => 'senior',
            'category' => 'seniority',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/tags/steps')
            ->assertOk()
            ->assertJsonPath('data.step_1.title', 'Área de Atuação')
            ->assertJsonPath('data.step_1.category', 'area')
            ->assertJsonPath('data.step_1.tags.0.id', $areaTag->id)
            ->assertJsonPath('data.step_2.category', 'technology')
            ->assertJsonPath('data.step_2.tags.0.id', $technologyTag->id)
            ->assertJsonPath('data.step_3.category', 'seniority')
            ->assertJsonCount(0, 'data.step_3.tags');
    }

    public function test_non_admin_user_is_forbidden_from_listing_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_admin_cannot_delete_own_account_and_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withHeaders($this->authHeaders($admin))
            ->deleteJson('/api/v1/users/'.$admin->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user')
            ->assertJsonPath('errors.user.0', 'You cannot delete your own account.');

        $this->assertModelExists($admin);
    }

    public function test_logout_revokes_the_token_for_subsequent_onboarding_requests(): void
    {
        $user = User::factory()->create();
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);
        $token = $user->createToken('test-token');
        $headers = [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ];

        $this->withHeaders($headers)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);

        $this->app['auth']->forgetGuards();

        $this->withHeaders($headers)
            ->postJson('/api/v1/onboarding/tags', [
                'tag_ids' => [$tag->id],
            ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('tag_user', 0);
    }

    public function test_store_tag_generates_slug_and_returns_201(): void
    {
        $this->postJson('/api/v1/tags', [
            'name' => 'PHP / Laravel',
            'category' => 'technology',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'PHP / Laravel')
            ->assertJsonPath('data.slug', 'php-laravel');

        $this->assertDatabaseHas('tags', [
            'name' => 'PHP / Laravel',
            'slug' => 'php-laravel',
            'category' => 'technology',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test-token')->plainTextToken,
        ];
    }
}
