# Clan Hub

Sistema web para gerenciamento de clãs de Clash of Clans.

O projeto está em desenvolvimento. A autenticação não usa username ou senha:
cada pessoa autorizada entra usando somente seu código de acesso.

## Tecnologias

- PHP 8.4 e Laravel 13
- React 18
- Inertia.js
- Tailwind CSS
- SQLite
- Vite
- Docker Compose

## Funcionalidades atuais

- Login com um único campo: código de acesso.
- Código armazenado somente como hash no banco.
- Primeiro acesso administrativo criado pelo seeder.
- Papéis normalizados na tabela `roles`: `admin`, `leader`, `co_leader` e `member`.
- Área autenticada com menu lateral, top bar e edição do nome do perfil.
- Configuração da tag e identidade do clã no banco, restrita a administradores.
- Dashboard operacional com membros ativos, guerras do mês, taxa de vitória e
  confrontos recentes.
- Painel paginado de membros com busca, filtros por status, CV e cargo, além de
  ordenação pelas colunas.
- Histórico de membros preservado pelos status `in` e `out`.
- Histórico paginado de guerras com filtro por resultado e captura persistente
  de ataques e defesas.
- Alertas de guerra ativa, cronômetro em tempo real e atualização diretamente
  pela tela de detalhes.
- Administração paginada de usuários, busca por nome e códigos numéricos de
  seis dígitos.
- Alteração de papéis e exclusão protegida de contas.
- Vínculo de várias contas do jogo a um único usuário.
- Sincronização automática diária de membros e guerras pelo scheduler.
- Login e logout com sessão Laravel.

### Permissões

- `admin`: configura o clã, cria e exclui acessos, gera códigos, gerencia papéis,
  vincula contas e sincroniza dados.
- `leader`: visualiza usuários, vincula contas, alterna usuários entre
  `member`/`co_leader` e sincroniza membros e guerras.
- `co_leader` e `member`: possuem as mesmas permissões de visualização, sem
  acesso administrativo ou sincronização.

Administradores não podem alterar ou excluir outra conta administrativa, nem
alterar ou excluir a própria conta. A promoção de um usuário para `admin`
exige confirmação adicional na interface e no backend.

Os códigos gerados pela administração são exibidos apenas no momento da criação
ou regeneração. Como somente o hash é persistido, não existe uma opção para
consultar posteriormente um código já criado.

## Executando com Docker

### Requisitos

- Git
- Docker
- Docker Compose

### Instalação

Clone o repositório e entre no diretório:

```bash
git clone https://github.com/rThimoteo/coc-clan-management.git
cd coc-clan-management
```

Crie o arquivo de ambiente:

```bash
cp .env.example .env
```

O banco local fica em `database/database.sqlite`. Crie o arquivo caso ele ainda
não exista:

```bash
touch database/database.sqlite
```

Antes de iniciar, substitua o código administrativo de exemplo por um valor
longo e secreto:

```env
ADMIN_ACCESS_CODE=seu-codigo-inicial-seguro
```

As migrations executam os seeders necessários para criar os papéis, status e o
acesso administrativo inicial. O código administrativo recebe o papel `admin`
e não é salvo em texto puro.

Construa e inicie os containers:

```bash
docker compose build
docker compose up -d
```

Instale as dependências PHP, gere a chave da aplicação e prepare o banco:

```bash
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
docker compose exec php-fpm php artisan migrate
```

O container Node instala as dependências e inicia o Vite automaticamente.

Acesse:

```text
http://localhost:8081
```

## Modo de demonstração da API

O `.env.example` vem preparado para usar os serviços do Clash of Clans sem
criar uma chave na API:

```env
APP_ENV=local
CLASH_OF_CLANS_DEMO_MODE=true
```

## Sincronização automática

O serviço Docker `scheduler` executa as sincronizações todos os dias no fuso
`America/Sao_Paulo`:

- membros às 03:00;
- guerras às 03:15.

O timezone da aplicação permanece em UTC para preservar corretamente os
horários retornados pela API. O fuso do scheduler é configurado separadamente:

```env
APP_TIMEZONE=UTC
SCHEDULE_TIMEZONE=America/Sao_Paulo
```

O serviço inicia junto com `docker compose up -d` e usa
`restart: unless-stopped`.

## Usando a API oficial

Crie uma chave no
[portal de desenvolvedores do Clash of Clans](https://developer.clashofclans.com/)
e configure o `.env`:

```env
CLASH_OF_CLANS_API_URL=https://api.clashofclans.com/v1
CLASH_OF_CLANS_API_TOKEN=seu_token
CLASH_OF_CLANS_DEMO_MODE=false
```

Depois de alterar o ambiente:

```bash
docker compose exec php-fpm php artisan config:clear
```

A tag do clã não é configurada no `.env`. Depois de entrar com o acesso
administrativo, use **Administração → Configurar clã**. O sistema valida a tag
na API antes de salvá-la.

O histórico de guerras do clã precisa estar público no Clash of Clans. A
sincronização guarda os resumos do war log e captura os detalhes da guerra
atual ou recém-encerrada enquanto a API ainda os disponibiliza. Entradas
agregadas sem tag de oponente não são persistidas.

## Comandos úteis

Executar testes:

```bash
docker compose exec php-fpm php artisan test
```

Sincronizar manualmente pela linha de comando:

```bash
docker compose exec php-fpm php artisan members:sync
docker compose exec php-fpm php artisan wars:sync
```

Consultar os próximos horários agendados:

```bash
docker compose exec php-fpm php artisan schedule:list
```

Recriar o banco local:

```bash
docker compose exec php-fpm php artisan migrate:fresh
```

Compilar o frontend:

```bash
docker compose exec node npm run build
```

Parar os containers:

```bash
docker compose down
```

## Estrutura do banco

- `roles`: papéis de autorização definidos pelo enum `UserRole`.
- `users`: nome interno, hash do código de acesso e referência ao papel.
- `clans`: tag, nome e emblema do clã configurado.
- `member_statuses`: estados de vínculo definidos pelo enum `MemberStatus`.
- `members`: contas do jogo conhecidas, CV, cargo, status e vínculo opcional
  com um usuário.
- `wars`: placares, oponentes, resultado e disponibilidade de detalhes.
- `war_members`: participantes de cada lado da guerra.
- `war_attacks`: ataques e defesas disponíveis nos detalhes capturados.
- `sessions`: sessões autenticadas da aplicação.

Cache usa arquivos locais, filas são executadas de forma síncrona e o scheduler
roda em um container dedicado.

## Roadmap

### Base do projeto

- [x] Configurar Laravel, React e Inertia.js.
- [x] Configurar ambiente Docker com Nginx, PHP, SQLite e Node.
- [x] Criar migration inicial sem tabelas especulativas.
- [x] Adicionar testes automatizados para autenticação e regras de acesso.

### Autenticação

- [x] Bootstrap do primeiro administrador pelo seeder.
- [x] Login somente por código de acesso.
- [x] Definir papéis `admin`, `leader`, `co_leader` e `member`.
- [x] Logout e proteção do dashboard.
- [x] Criar gestão administrativa de acessos.
- [x] Gerar e regenerar códigos de seis dígitos.
- [x] Vincular usuários a múltiplas contas do jogo.
- [x] Aplicar autorização por papel nas funcionalidades.

### Gerenciamento do clã

- [x] Criar dashboard operacional.
- [x] Configurar e validar o clã pela API oficial.
- [x] Sincronizar e preservar o histórico de membros.
- [x] Registrar histórico de guerras.
- [x] Capturar ataques e defesas detalhados disponíveis.
- [x] Automatizar sincronizações diárias de membros e guerras.
- [ ] Criar inscrições para Liga de Guerra de Clãs.
- [ ] Registrar histórico de CWL.
- [ ] Criar métricas de desempenho por jogador.
