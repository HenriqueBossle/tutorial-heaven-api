<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_tags_are_saved_and_returned_by_the_user_controller(): void
    {
        $user = User::factory()->create();
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);
        $headers = [
            'Authorization' => 'Bearer '.$user->createToken('test-token')->plainTextToken,
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/onboarding/tags', [
                'tag_ids' => [$tag->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.tags.0.id', $tag->id);

        $this->assertDatabaseHas('tag_user', [
            'user_id' => $user->id,
            'tag_id' => $tag->id,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.tags.0.id', $tag->id)
            ->assertJsonPath('data.tags.0.name', 'Laravel');
    }

    public function test_onboarding_tag_sync_requires_authentication(): void
    {
        $this->postJson('/api/v1/onboarding/tags', [
            'tag_ids' => [1],
        ])->assertUnauthorized();
    }

    public function test_onboarding_tag_sync_rejects_unknown_tags_without_persisting_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/onboarding/tags', [
                'tag_ids' => [999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.0');

        $this->assertDatabaseCount('tag_user', 0);
    }
}
