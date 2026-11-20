# Relatório de Testes de Integração — Tutorial Heaven API

**Projeto:** `tutorial-heaven-api` (Laravel 13, backend da aplicação Tutorial Heaven — ULBRA)
**Data da execução:** 19/11/2026
**Objetivo:** validar os fluxos HTTP → middleware → FormRequest → controller → Eloquent → API Resource → JSON e consolidar, em um único arquivo, tudo o que foi obtido dos testes.

---

## 1. Arquivos envolvidos

| Tipo | Arquivo | Papel |
| --- | --- | --- |
| Novo | `tests/Feature/UserTagsIntegrationTest.php` | 10 casos de integração (51 asserções) |
| Reutilizado | `tests/Feature/OnboardingControllerTest.php` | Base de padrão: token Sanctum, `postJson`, `assertDatabaseHas` |
| Reutilizado | `tests/Feature/UserControllerTest.php` | Tags em listagem e atualização de usuário |
| Reutilizado | `tests/Feature/AuthTest.php` | Registro/login e emissão de token |
| Reutilizado | `tests/Feature/RouteCoverageTest.php` | CRUD de artigos, Base64, logout, `/api/user` |
| Reutilizado | `tests/Feature/ArticleResourceTest.php`, `ExampleTest.php` | Serialização e smoke test |
| Referência | `plano-de-testes-integracao.md` | Plano detalhado dos casos e estratégia |

Nenhum teste existente foi alterado. As modificações já presentes em `UserController.php`, `UserResource.php` e `routes/api.php` são preexistentes a esta análise (10/10/2026) e foram mantidas.

---

## 2. Ambiente e execução

| Item | Valor |
| --- | --- |
| PHP / Laravel / PHPUnit / Sanctum | 8.3 / 13.32.0 / 12.5.35 / 4.3.3 |
| Banco de teste | SQLite `:memory:` (`phpunit.xml`) |
| Isolamento | `RefreshDatabase`, cache/fila/sessão/mail em array |
| Autenticação nos testes | `createToken('test-token')->plainTextToken` enviado em `Authorization: Bearer` |

```bash
php artisan test --compact                                          # suíte completa
php artisan test --compact tests/Feature/UserTagsIntegrationTest.php # só integração
php artisan test --compact --order-by=random                        # independência entre testes
vendor/bin/pint --dirty --format agent                              # estilo (PHP)
```

---

## 3. Resultados obtidos

| Execução | Comando | Resultado |
| --- | --- | --- |
| Teste de integração isolado | `php artisan test --compact tests/Feature/UserTagsIntegrationTest.php` | **10/10 passaram**, 51 asserções, 216 ms |
| Suíte completa | `php artisan test --compact` | **31/31 passaram**, 137 asserções, 415 ms |
| Ordem aleatória | `php artisan test --compact --order-by=random` | **31/31 passaram**, 375 ms |

Saída consolidada do runner: `{"tool":"phpunit","result":"passed","tests":31,"passed":31,"assertions":137}`.

---

## 4. Casos de integração executados

| ID | Fluxo validado | Resultado esperado | Método |
| --- | --- | --- | --- |
| IT-01 | `POST /api/register` → token → `POST /api/v1/onboarding/tags` → `GET /api/v1/users/{id}/tags` | 201 → 200 → 200; tag na resposta e em `tag_user` | `test_registered_user_can_sync_tags_and_read_them_from_user_tags_route` |
| IT-02 | Sync com tag diferente da já vinculada | 200; só 1 vínculo, antigo removido | `test_onboarding_replaces_previous_tags_and_keeps_only_the_new_selection` |
| IT-03 | `tag_ids: []` | 422 em `tag_ids`; vínculos inalterados | `test_onboarding_rejects_empty_selection_without_changing_pivot_rows` |
| IT-04 | `GET /api/v1/users/99999/tags` | 404, `Usuário não encontrado.` | `test_user_tags_route_returns_404_when_user_does_not_exist` |
| IT-05 | `GET /api/v1/tags` com tags ativas/inativa | 200; 3 ativas ordenadas por categoria/ordem | `test_tags_index_returns_only_active_tags_ordered_by_category_and_display_order` |
| IT-06 | `GET /api/v1/tags/steps` | 200; `step_1`/`step_2`/`step_3` com tags ativas | `test_tags_steps_route_groups_only_active_tags_by_fixed_step_category` |
| IT-07 | `GET /api/v1/users` como usuário comum | 403 `Forbidden` | `test_non_admin_user_is_forbidden_from_listing_users` |
| IT-08 | `DELETE /api/v1/users/{próprio id}` como admin | 422 em `user`; registro preservado | `test_admin_cannot_delete_own_account_and_returns_422` |
| IT-09 | `POST /api/logout` → nova requisição com o mesmo token | 200; token removido; onboarding seguinte 401; sem vínculo | `test_logout_revokes_the_token_for_subsequent_onboarding_requests` |
| IT-10 | `POST /api/v1/tags` com `name: PHP / Laravel` | 201; slug `php-laravel`; persistido | `test_store_tag_generates_slug_and_returns_201` |

**Cobertura por tabela:** `users`, `tags`, `tag_user`, `personal_access_tokens` — verificada com `assertDatabaseHas`, `assertDatabaseMissing`, `assertDatabaseCount` e `assertModelExists`.

**Cobertura dos testes já existentes (baseline):** onboarding com tags retornadas via `/api/v1/users/{id}`, autenticação obrigatória, tags inexistentes rejeitadas, tags na listagem/atualização de usuários, registro/login com token, CRUD de artigos com Base64 e validação de conteúdo.

---

## 5. Achados do código (diagnosticados, não corrigidos)

| # | Severidade | Achado | Local |
| --- | --- | --- | --- |
| 1 | Média | Filtro `?search=` é montado e descartado antes da paginação | `ArticleController::index()` (linhas 26-34) |
| 2 | Média | `POST /api/v1/tags` é público e `StoreTagRequest::authorize()` retorna `true`, contrariando `backend-guide.md` (tags read-only no front) | `routes/api.php`, `StoreTagRequest` |
| 3 | Média | Onboarding aceita tags inativas | `SyncUserTagsRequest::rules()` |
| 4 | Baixa | `RouteCoverageTest` duplica login/registro de `AuthTest`; o teste de logout não conferia revogação (IT-09 cobre) | `tests/Feature/RouteCoverageTest.php` |
| 5 | Baixa | `prepareForValidation()` sempre sobrescreve o `slug` enviado, apesar do comentário | `StoreTagRequest` (linhas 15-23) |
| 6 | Baixa | `TagController::show()` não filtra tags inativas, ao contrário de `/api/v1/tags` | `TagController::show()` |

**Recomendações:** aplicar o filtro na query paginada com teste de busca; proteger a criação de tags com `auth:sanctum` + admin; validar `is_active` no sync; consolidar testes duplicados; gerar slug apenas quando ausente; alinhar `show()` com `index()`.

---

## 6. Limitações

- SQLite em memória não reproduz diferenças do PostgreSQL de produção (enum, case-sensitivity de `LIKE`).
- Não há testes de browser (Dusk não instalado), carga ou penetração.
- Antes da configuração do `.env`/`APP_KEY`, `ExampleTest` falhava; com o `.env` atual a suíte passa integralmente.
