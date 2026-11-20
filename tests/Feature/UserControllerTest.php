<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_list_includes_each_users_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);
        $admin->tags()->attach($tag);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('data.0.tags.0.id', $tag->id)
            ->assertJsonPath('data.0.tags.0.name', 'Laravel');
    }

    public function test_user_update_response_includes_the_users_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $tag = Tag::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'technology',
        ]);
        $user->tags()->attach($tag);

        $this->withHeaders($this->authHeaders($admin))
            ->putJson('/api/v1/users/'.$user->id, [
                'name' => 'Updated User',
            ])
            ->assertOk()
            ->assertJsonPath('data.tags.0.id', $tag->id)
            ->assertJsonPath('data.tags.0.name', 'Laravel');
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
