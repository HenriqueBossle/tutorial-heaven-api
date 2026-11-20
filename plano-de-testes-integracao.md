# Plano de Testes de Integração — Tutorial Heaven API

**Projeto:** `tutorial-heaven-api` (backend Laravel da aplicação Tutorial Heaven)
**Data:** 19/11/2026
**Versão:** 1.0
**Responsável:** Arthur (ULBRA)

---

## 1. Objetivo

Validar, por meio de testes de integração automatizados, que os fluxos entre autenticação, onboarding de tags, consulta de usuários e catálogo de tags funcionam de ponta a ponta: requisição HTTP → middleware → `FormRequest` → controller → Eloquent/banco de dados → API Resource → resposta JSON.

Os testes foram escritos **com base no teste já existente no projeto**, `tests/Feature/OnboardingControllerTest.php`, mantendo o mesmo padrão:

- `extends Tests\TestCase` com `Illuminate\Foundation\Testing\RefreshDatabase`;
- autenticação com token Sanctum (`$user->createToken('test-token')->plainTextToken`) enviado em `Authorization: Bearer`;
- requisições com `postJson()` / `getJson()` / `deleteJson()`;
- asserções de resposta com `assertOk()`, `assertCreated()`, `assertJsonPath()`, `assertJsonValidationErrors()`;
- asserções de persistência com `assertDatabaseHas()`, `assertDatabaseMissing()`, `assertDatabaseCount()` e `assertModelExists()`.

Nesta entrega, nenhum teste existente foi alterado e nenhum arquivo de código de produção foi modificado. As alterações já presentes no `working tree` em `UserController.php`, `UserResource.php` e `routes/api.php` são preexistentes (10/10/2026) e foram mantidas.

---

## 2. Escopo

### 2.1 Dentro do escopo

| Área | Endpoints / comportamentos |
| --- | --- |
| Autenticação | `POST /api/register`, `POST /api/logout`, token Bearer e revogação |
| Onboarding | `POST /api/v1/onboarding/tags` (sync, substituição, validação, autenticação) |
| Usuários | `GET /api/v1/users`, `GET /api/v1/users/{id}/tags`, `DELETE /api/v1/users/{id}` |
| Catálogo de tags | `GET /api/v1/tags`, `GET /api/v1/tags/steps`, `POST /api/v1/tags` |
| Persistência | Tabelas `users`, `tags`, `tag_user`, `personal_access_tokens` |

### 2.2 Fora do escopo

- Testes de browser real (Dusk não está instalado);
- Testes de carga, performance e penetração;
- Integração com banco NeonDB/PostgreSQL de produção — a suíte usa SQLite em memória;
- Correção dos defeitos listados na seção 7 (apenas diagnosticados).

---

## 3. Ambiente e pré-requisitos

| Item | Versão / valor |
| --- | --- |
| PHP | 8.3 |
| Laravel | 13.32.0 |
| PHPUnit | 12.5.35 |
| Sanctum | 4.3.3 |
| Banco de teste | SQLite `:memory:` (definido em `phpunit.xml`) |
| Cache / fila / mail / sessão | `array`, `sync`, `array`, `array` (definido em `phpunit.xml`) |

Configuração local necessária (o repositório não possui `.env`):

```bash
cp .env.example .env
php artisan key:generate
```

> Sem `APP_KEY`, o teste `tests/Feature/ExampleTest.php` falha com `No application encryption key has been specified.` Essa foi a única falha do baseline antes da configuração do ambiente.

Comandos de execução:

```bash
# Suíte completa
php artisan test --compact

# Apenas os testes de integração deste plano
php artisan test --compact tests/Feature/UserTagsIntegrationTest.php

# Um caso específico
php artisan test --compact --filter=test_onboarding_replaces_previous_tags_and_keeps_only_the_new_selection

# Verificar independência entre testes
php artisan test --compact --order-by=random

# Estilo de código
vendor/bin/pint --dirty --format agent
```

---

## 4. Estratégia

1. **Nível:** testes *feature* (integração), sem mocks de banco ou de HTTP — as requisições são simuladas internamente pelo Laravel e as consultas executadas no SQLite de teste.
2. **Isolamento:** `RefreshDatabase` recria o schema para cada teste; cada teste cria seus próprios registros via `User::factory()` e `Tag::create()`.
3. **Autenticação:** tokens reais do Sanctum. Nos testes de fluxo de logout, os guards são limpos com `$this->app['auth']->forgetGuards()` para evitar o cache de autenticação do próprio processo de teste.
4. **Asserções:** status HTTP nomeado, corpo JSON (caminhos e mensagens), e estado persistido. Em operações de escrita, resposta e banco são verificados juntos.
5. **Critérios de aceite:** todos os casos devem passar isoladamente, em ordem normal e em ordem aleatória.

---

## 5. Casos de teste

Arquivo novo: `tests/Feature/UserTagsIntegrationTest.php` (10 casos, 51 asserções).
Arquivos de referência já existentes: `OnboardingControllerTest.php`, `UserControllerTest.php`, `AuthTest.php`, `RouteCoverageTest.php`, `ArticleResourceTest.php`.

| ID | Caso / pré-condição | Ação | Resultado esperado | Método de teste |
| --- | --- | --- | --- | --- |
| IT-01 | Nenhum tag selecionada; tag `Laravel` cadastrada | Registrar usuário (`201`), usar o token retornado para sincronizar tags e consultar `GET /api/v1/users/{id}/tags` | Sync `200` com `data.user.tags.0.id`; leitura `200` com a tag; vínculo existe em `tag_user` | `test_registered_user_can_sync_tags_and_read_them_from_user_tags_route` |
| IT-02 | Usuário já possui a tag `React`; tag `Laravel` existe | `POST /api/v1/onboarding/tags` com `tag_ids: [Laravel]` | `200`; apenas 1 vínculo no banco; `React` removido e `Laravel` presente | `test_onboarding_replaces_previous_tags_and_keeps_only_the_new_selection` |
| IT-03 | Usuário possui 1 vínculo de tag | `POST /api/v1/onboarding/tags` com `tag_ids: []` | `422`, erro em `tag_ids` com mensagem `The tag ids field is required.`; nenhum vínculo alterado | `test_onboarding_rejects_empty_selection_without_changing_pivot_rows` |
| IT-04 | Usuário autenticado; ID inexistente | `GET /api/v1/users/99999/tags` | `404` com `message: Usuário não encontrado.` | `test_user_tags_route_returns_404_when_user_does_not_exist` |
| IT-05 | Tags ativas `Backend` (ordem 1), `Frontend` (ordem 2), `Laravel`; uma tag inativa `Inativa` | `GET /api/v1/tags` sem token | `200`, `success: true`, 3 itens na ordem Backend → Frontend → Laravel; inativa ausente | `test_tags_index_returns_only_active_tags_ordered_by_category_and_display_order` |
| IT-06 | Tags ativas nas categorias `area` e `technology`; tag inativa em `seniority` | `GET /api/v1/tags/steps` | `200`; chaves `step_1`, `step_2`, `step_3` com títulos/categorias fixos; tags ativas nos respectivos passos; `step_3.tags` vazio | `test_tags_steps_route_groups_only_active_tags_by_fixed_step_category` |
| IT-07 | Usuário autenticado com `role = user` | `GET /api/v1/users` | `403` com `message: Forbidden` | `test_non_admin_user_is_forbidden_from_listing_users` |
| IT-08 | Admin autenticado | `DELETE /api/v1/users/{id do próprio admin}` | `422`, erro em `user` com `You cannot delete your own account.`; registro mantido | `test_admin_cannot_delete_own_account_and_returns_422` |
| IT-09 | Usuário com token Sanctum ativo | `POST /api/logout` e, após limpar os guards, nova tentativa de onboarding com o mesmo token | Logout `200`; token removido de `personal_access_tokens`; requisição seguinte `401`; nenhum vínculo criado | `test_logout_revokes_the_token_for_subsequent_onboarding_requests` |
| IT-10 | Nenhuma tag cadastrada | `POST /api/v1/tags` com `name: PHP / Laravel`, `category: technology` | `201`; slug gerado `php-laravel`; registro persistido | `test_store_tag_generates_slug_and_returns_201` |

### Cobertura dos testes já existentes (baseline)

| ID | Arquivo | Comportamento |
| --- | --- | --- |
| EX-01 | `OnboardingControllerTest.php` | Tags selecionadas são salvas e retornadas por `/api/v1/users/{id}` |
| EX-02 | `OnboardingControllerTest.php` | Sync de onboarding exige autenticação (`401`) |
| EX-03 | `OnboardingControllerTest.php` | Tags inexistentes são rejeitadas (`422`) sem persistência |
| EX-04 | `UserControllerTest.php` | Listagem de admin inclui as tags de cada usuário |
| EX-05 | `UserControllerTest.php` | Resposta de atualização de usuário inclui as tags |
| EX-06 | `AuthTest.php` / `RouteCoverageTest.php` | Registro e login retornam token |
| EX-07 | `RouteCoverageTest.php` | CRUD de artigos, codificação Base64 e validação de conteúdo |

---

## 6. Resultados da execução

| Execução | Comando | Resultado |
| --- | --- | --- |
| Baseline (antes de criar `.env`) | `php artisan test --compact` | 21 testes, 20 passaram, 1 erro (`ExampleTest`: chave de criptografia ausente) |
| Novos testes de integração | `php artisan test --compact tests/Feature/UserTagsIntegrationTest.php` | **10/10 passaram**, 51 asserções |
| Suíte completa (após `.env` + novos testes) | `php artisan test --compact` | **31/31 passaram**, 137 asserções |
| Ordem aleatória | `php artisan test --compact --order-by=random` | **31/31 passaram** |
| Formatação | `vendor/bin/pint --dirty --format agent` | Sem alterações necessárias |

---

## 7. Achados e recomendações

Os itens abaixo foram identificados durante a análise e **não foram corrigidos** nesta entrega, pois o escopo solicitado foi de testes e plano de testes.

| # | Severidade | Achado | Recomendação |
| --- | --- | --- | --- |
| 1 | Média | `ArticleController::index()` constrói o filtro `?search=` e o descarta antes da paginação (`app/Http/Controllers/ArticleController.php`, linhas 26-34). | Aplicar o filtro na query paginada e adicionar teste de busca. |
| 2 | Média | `POST /api/v1/tags` é público: a rota não usa `auth:sanctum` e `StoreTagRequest::authorize()` retorna `true`, embora `backend-guide.md` defina tags como read-only no front. | Proteger a rota com autenticação e permissão de admin. |
| 3 | Média | `OnboardingController::syncTags()` aceita tags inativas, sem validar `is_active`. | Validar no `SyncUserTagsRequest` que as tags estejam ativas. |
| 4 | Baixa | `RouteCoverageTest` duplica login/registro de `AuthTest`; o teste de logout não verifica a revogação do token (o novo IT-09 cobre essa lacuna). | Consolidar os casos duplicados e manter a verificação de revogação. |
| 5 | Baixa | `StoreTagRequest::prepareForValidation()` sobrescreve sempre o `slug` enviado pelo cliente, apesar do comentário dizer "caso não venha na requisição". | Só gerar o slug quando o campo estiver ausente. |
| 6 | Baixa | `TagController::show()` não filtra tags inativas, ao contrário de `/api/v1/tags`. | Alinhar o comportamento ou documentar a diferença. |

---

## 8. Limitações e próximos passos

- A suíte usa SQLite em memória; diferenças de comportamento do PostgreSQL (ex.: enum, case-sensitivity de `LIKE`) não são detectadas aqui.
- Testes de browser/dusk e testes de carga não fazem parte desta entrega.
- Sugestão de evolução: incluir a execução de `php artisan test --compact` no pipeline de CI e adicionar testes para os defeitos 1 a 3 após a correção do código de produção.
