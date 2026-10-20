# Guia oficial de integração do front-end

Este guia descreve como consumir a API Laravel deste projeto. Os exemplos usam `http://localhost:8000`; em cada ambiente, substitua essa origem pelo valor configurado em `APP_URL`.

## 1. Visão geral e padrões da API

### URL base e prefixos

O servidor Laravel é a origem da API, sem o prefixo no valor de `APP_URL`. As rotas são agrupadas assim:

- `/api`: autenticação e consulta do usuário atual.
- `/api/v1`: recursos versionados de artigos e tags.

Exemplos:

```text
http://localhost:8000/api/login
http://localhost:8000/api/v1/articles
http://localhost:8000/api/v1/tags
```

### Cabeçalhos

Envie e aceite JSON em todas as chamadas:

```http
Accept: application/json
Content-Type: application/json
```

Nas rotas protegidas, inclua também o token recebido no login ou cadastro:

```http
Authorization: Bearer <token>
```

### Erros HTTP

Os erros da API são respostas JSON. O campo `message` contém a mensagem geral; erros de validação `422` também incluem `errors`, indexado pelo nome do campo. O texto pode variar conforme a rota e o idioma configurado no Laravel.

| Status | Significado | Payload ilustrativo |
| --- | --- | --- |
| `401 Unauthorized` | Token ausente/inválido em rota protegida ou credenciais inválidas no login. | `{"message":"Unauthenticated."}`. Login com credenciais inválidas retorna `{"message":"Invalid credentials"}`. |
| `403 Forbidden` | Usuário autenticado sem permissão para a operação. | `{"message":"This action is unauthorized."}` |
| `404 Not Found` | Rota ou artigo inexistente. | `{"message":"Not Found."}`. Em erros de model binding, a mensagem pode identificar o modelo consultado. |
| `422 Unprocessable Entity` | Payload inválido; `errors` informa os campos a corrigir. | `{"message":"The content field is required.","errors":{"content":["O campo content é obrigatório."]}}` |
| `5xx` | Falha inesperada no servidor. Não tente interpretar a resposta como sucesso; mostre uma mensagem genérica e registre o contexto no cliente. | `{"message":"Server Error"}`. Detalhes variam com a configuração de debug do servidor e não devem ser exibidos ao usuário em produção. |

## 2. Autenticação: Laravel Sanctum

### Modelo utilizado

O front-end deve usar **Bearer Token**, não autenticação por cookies de SPA stateful. `POST /api/login` e `POST /api/register` retornam um token pessoal do Sanctum no campo `token`. Envie esse valor no cabeçalho `Authorization` em cada chamada protegida. O backend revoga o token corrente no logout.

Use HTTPS fora do ambiente local e evite expor o token em logs, URLs ou armazenamento acessível a scripts não confiáveis. O backend não estabelece a sessão de autenticação do front-end por cookies.

### `POST /api/login`

Autentica um usuário existente. Ambos os campos são obrigatórios; `email` deve ser um endereço válido.

```json
{
  "email": "ana@example.com",
  "password": "senha-secreta"
}
```

Resposta `200 OK`:

```json
{
  "message": "Logged in successfully",
  "token": "1|<token-pessoal>",
  "user": {
    "id": 42,
    "name": "Ana Silva",
    "email": "ana@example.com",
    "role": "user"
  }
}
```

Credenciais incorretas resultam em `401`; falhas de validação do formato dos campos resultam em `422`.

### `POST /api/register`

Cria uma conta e já retorna um token. Campos:

- `name`: obrigatório, string, máximo de 255 caracteres.
- `email`: obrigatório, e-mail válido e único.
- `password`: obrigatório, string com no mínimo 8 caracteres.
- `password_confirmation`: obrigatório e igual a `password` (regra `confirmed` do Laravel).

```json
{
  "name": "Ana Silva",
  "email": "ana@example.com",
  "password": "senha-secreta",
  "password_confirmation": "senha-secreta"
}
```

Resposta `201 Created`: mesmo formato de `login`, com `message: "Registered successfully"`, `token` e `user`.

### `POST /api/logout`

Requer `Authorization: Bearer <token>`. Revoga o token usado nesta chamada. Resposta `200 OK`:

```json
{
  "message": "Logged out successfully"
}
```

Após o logout, descarte o token no cliente. Chamadas seguintes com ele devem ser tratadas como não autenticadas.

### `GET /api/user`

Requer `Authorization: Bearer <token>`. Retorna o usuário autenticado como JSON (`id`, `name`, `email`, `role` e timestamps disponíveis). A senha e o token não são incluídos. Use esta rota para carregar os dados da conta após recuperar o token.

## 3. Artigos: `/api/v1/articles`

Todas as rotas de artigos exigem autenticação Bearer. Usuários autenticados podem listar e consultar artigos; criação, edição e exclusão são restritas a usuários com papel `admin`.

| Método e rota | Descrição | Sucesso |
| --- | --- | --- |
| `GET /api/v1/articles` | Lista artigos paginados (10 por página). Aceita `?page=2`. | `200 OK`, envelope de paginação Laravel com `data`, `links` e `meta`. |
| `GET /api/v1/articles/{id}` | Consulta um artigo pelo ID. | `200 OK`, objeto do artigo. |
| `POST /api/v1/articles` | Cria um artigo. | `201 Created`, objeto criado. |
| `PUT /api/v1/articles/{id}` | Substitui o conteúdo do artigo. | `200 OK`, objeto atualizado. |
| `PATCH /api/v1/articles/{id}` | Atualiza o conteúdo do artigo. | `200 OK`, objeto atualizado. |
| `DELETE /api/v1/articles/{id}` | Exclui o artigo. | `204 No Content`, sem corpo. |

### Codificação Base64 de `content`

O campo `content` é Markdown e **circula codificado em Base64 na fronteira HTTP**: o front-end codifica o texto UTF-8 antes de enviar `POST`, `PUT` ou `PATCH`, e decodifica Base64 para UTF-8 ao receber `GET` (incluindo os artigos dentro de `data` na listagem). O armazenamento interno do backend continua sendo Markdown em texto UTF-8.

Exemplo de conteúdo Markdown:

```text
# Olá, mundo! 👋

Conteúdo com ação e coração 💙.
```

Payload de `POST /api/v1/articles` (envie `Authorization: Bearer <token>`):

```json
{
  "content": "IyBPbMOhLCBtdW5kbyEg8J+RiwoKQ29udGXDumRvIGNvbSBhw6fDo28gZSBjb3Jhw6fDo28g8J+SmS4="
}
```

Resposta de `POST` e formato de `GET /api/v1/articles/{id}`:

```json
{
  "id": 123,
  "user_id": 42,
  "content": "IyBPbMOhLCBtdW5kbyEg8J+RiwoKQ29udGXDumRvIGNvbSBhw6fDo28gZSBjb3Jhw6fDo28g8J+SmS4=",
  "created_at": "2026-10-12T18:29:15.000000Z",
  "updated_at": "2026-10-12T18:29:15.000000Z",
  "user": {
    "id": 42,
    "name": "Ana Silva",
    "email": "ana@example.com"
  }
}
```

Na listagem, esse objeto aparece dentro de `data` e a resposta inclui os metadados de paginação.

### Conversão UTF-8 segura no navegador

Não passe texto com acentos ou emojis diretamente para `btoa`: essa função trata strings como bytes Latin-1 e pode lançar exceção ou corromper o conteúdo. Converta primeiro para bytes UTF-8 com `TextEncoder` e, na leitura, reconstrua os bytes antes de usar `TextDecoder`.

```js
export function encodeMarkdown(markdown) {
  const bytes = new TextEncoder().encode(markdown);
  let binary = "";
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary);
}

export function decodeMarkdown(base64) {
  const binary = atob(base64);
  const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));
  return new TextDecoder("utf-8", { fatal: true }).decode(bytes);
}
```

`fatal: true` faz uma resposta com bytes que não sejam UTF-8 válido falhar explicitamente, em vez de substituir silenciosamente caracteres. Envie o Markdown já codificado no JSON e decodifique o valor de `content` após analisar a resposta.

## 4. Tags e leituras: `/api/v1/tags`

Tags são **estritamente Read-Only no front-end**. Use somente as rotas GET a seguir para popular seletores e etapas de formulário. Não crie, edite ou exclua tags a partir do cliente.

As leituras não exigem autenticação. Tags inativas são omitidas.

### `GET /api/v1/tags`

Retorna tags ativas ordenadas por `category` e `display_order`.

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Desenvolvimento Web",
      "slug": "desenvolvimento-web",
      "category": "area",
      "description": "Aplicações web",
      "display_order": 1,
      "is_active": true,
      "created_at": "2026-10-12T18:29:15.000000Z",
      "updated_at": "2026-10-12T18:29:15.000000Z"
    }
  ]
}
```

Cada item é o modelo de tag completo. Para um seletor simples, use `id` como valor e `name` como rótulo; use `category` para filtrar ou agrupar.

### `GET /api/v1/tags/steps`

Retorna as tags ativas agrupadas nas etapas do formulário. Cada grupo tem título, categoria e uma lista de tags no mesmo formato da resposta anterior:

```json
{
  "success": true,
  "data": {
    "step_1": {
      "title": "Área de Atuação",
      "category": "area",
      "tags": [
        {
          "id": 1,
          "name": "Desenvolvimento Web",
          "slug": "desenvolvimento-web",
          "category": "area",
          "description": "Aplicações web",
          "display_order": 1,
          "is_active": true,
          "created_at": "2026-10-12T18:29:15.000000Z",
          "updated_at": "2026-10-12T18:29:15.000000Z"
        }
      ]
    },
    "step_2": {
      "title": "Tecnologias e Stacks",
      "category": "technology",
      "tags": []
    },
    "step_3": {
      "title": "Sênioridade / Experiência",
      "category": "seniority",
      "tags": []
    }
  }
}
```

Os grupos são fixos e vêm sempre nas chaves `step_1`, `step_2` e `step_3`; cada `tags` pode estar vazio se não houver tags ativas naquela categoria.
