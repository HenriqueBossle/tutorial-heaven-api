<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        if (Article::query()->exists()) {
            return;
        }

        $author = User::query()->where('role', 'admin')->first() ?? User::query()->first();

        if (! $author) {
            return;
        }

        $tags = Tag::query()->pluck('id', 'name');

        $articles = [
            [
                'tags' => ['React / Next.js', 'Frontend'],
                'content' => "# Server Components no React\n\nOs Server Components executam no servidor e reduzem o JavaScript enviado ao navegador.\n\n## Quando usar\n\nUse quando o componente precisa de dados privados ou de renderização no servidor.\n\n```tsx\nexport default async function Page() {\n  return <main>Carregado no servidor</main>;\n}\n```",
            ],
            [
                'tags' => ['React / Next.js', 'TypeScript', 'Frontend'],
                'content' => "# Tipos fortes no front-end\n\nTypeScript transforma erros de tipagem em erros de compilação.\n\n## generics\n\n```ts\nfunction primeiro<T>(itens: T[]): T | undefined {\n  return itens[0];\n}\n```",
            ],
            [
                'tags' => ['PHP / Laravel', 'Backend'],
                'content' => "# Form Requests no Laravel\n\nForm Requests centralizam a validação e a autorização de uma requisição.\n\n## Exemplo\n\n```php\npublic function rules(): array\n{\n    return ['title' => ['required', 'string', 'max:255']];\n}\n```",
            ],
            [
                'tags' => ['Node.js / Express', 'Backend'],
                'content' => "# Middlewares no Express\n\nMiddlewares interceptam a requisição antes do handler final.\n\n## Ordem importa\n\nA ordem de registro define a ordem de execução dos middlewares.",
            ],
            [
                'tags' => ['Python', 'Backend'],
                'content' => "# Automatizar tarefas com Python\n\nPython resolve tarefas repetitivas com poucas linhas de código.\n\n## Um exemplo rápido\n\n```python\nprint(\"olá, mundo\")\n```",
            ],
            [
                'tags' => ['UI/UX Design', 'Frontend'],
                'content' => "# Contraste em interfaces escuras\n\nContraste alto mantém o texto legível sobre fundos escuros.\n\n## Boas práticas\n\n- Prefira textos claros sobre fundos escuros.\n- Reserve cores fortes para ações principais.",
            ],
            [
                'tags' => ['Sénior', 'PHP / Laravel', 'Backend'],
                'content' => "# Rotinas de、物流as\n\nUm checklist para revisões de código em projects mais maduros.",
            ],
            [
                'tags' => ['Pleno', 'React / Next.js'],
                'content' => "# Do protótipo à produção\n\nComo levar uma interface do Figma até a produção com qualidade.",
            ],
        ];

        foreach ($articles as $article) {
            $created = Article::create([
                'id_user' => $author->id,
                'content' => $article['content'],
            ]);

            $created->tags()->sync($tags->only($article['tags'])->values());
        }
    }
}
