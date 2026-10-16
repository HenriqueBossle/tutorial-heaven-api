<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tag;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            // Step 1: Área de Atuação
            ['name' => 'Frontend', 'category' => 'area', 'display_order' => 1],
            ['name' => 'Backend', 'category' => 'area', 'display_order' => 2],
            ['name' => 'Fullstack', 'category' => 'area', 'display_order' => 3],
            ['name' => 'UI/UX Design', 'category' => 'area', 'display_order' => 4],

            // Step 2: Tecnologias e Stacks
            ['name' => 'React / Next.js', 'category' => 'technology', 'display_order' => 1],
            ['name' => 'PHP / Laravel', 'category' => 'technology', 'display_order' => 2],
            ['name' => 'Node.js / Express', 'category' => 'technology', 'display_order' => 3],
            ['name' => 'Java / Spring', 'category' => 'technology', 'display_order' => 4],
            ['name' => 'Python', 'category' => 'technology', 'display_order' => 5],
            ['name' => 'TypeScript', 'category' => 'technology', 'display_order' => 6],

            // Step 3: Senioridade / Nível
            ['name' => 'Estagiário / Trainee', 'category' => 'seniority', 'display_order' => 1],
            ['name' => 'Júnior', 'category' => 'seniority', 'display_order' => 2],
            ['name' => 'Pleno', 'category' => 'seniority', 'display_order' => 3],
            ['name' => 'Sênior', 'category' => 'seniority', 'display_order' => 4],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['name' => $tag['name']],
                [
                    'slug' => Str::slug($tag['name']),
                    'category' => $tag['category'],
                    'display_order' => $tag['display_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}