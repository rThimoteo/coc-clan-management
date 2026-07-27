# Clash of Clans Clan Management

Sistema web para gerenciamento de clãs de Clash of Clans.

O projeto está em desenvolvimento. A autenticação não usa username ou senha:
cada pessoa autorizada entra usando somente seu código de acesso.

## Tecnologias

- PHP 8.4 e Laravel 13
- React 18
- Inertia.js
- Tailwind CSS
- MySQL 8.4
- Vite
- Docker Compose

## Funcionalidades atuais

- Login com um único campo: código de acesso.
- Código armazenado somente como hash no banco.
- Primeiro acesso administrativo criado pelo seeder.
- Papéis normalizados na tabela `roles`: `admin`, `leader`, `co_leader` e `member`.
- Área autenticada com menu lateral, top bar e edição do nome do perfil.
- Configuração da tag e identidade do clã no banco, restrita a administradores.
- Painel local de membros com sincronização manual pela API do jogo.
- Histórico de membros preservado pelos status `in` e `out`.
- Histórico de guerras sincronizado com captura persistente de ataques e defesas.
- Detalhes de guerra com ataques dos membros e modal de defesas.
- Administração de usuários e códigos numéricos de seis dígitos.
- Vínculo de várias contas do jogo a um único usuário.
- Login e logout com sessão Laravel.
- Dashboard inicial protegido por autenticação.

### Permissões

- `admin`: configura o clã, administra usuários e sincroniza dados.
- `leader` e `co_leader`: visualizam e sincronizam membros e guerras.
- `member`: possui acesso somente para visualização e não pode sincronizar.

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
- `members`: contas do jogo conhecidas, status e vínculo opcional com um usuário.
- `wars`: placares, oponentes, resultado e disponibilidade de detalhes.
- `war_members`: participantes de cada lado da guerra.
- `war_attacks`: ataques e defesas disponíveis nos detalhes capturados.
- `sessions`: sessões autenticadas da aplicação.

Cache usa arquivos locais e filas são executadas de forma síncrona nesta fase.

## Roadmap

### Base do projeto

- [x] Configurar Laravel, React e Inertia.js.
- [x] Configurar ambiente Docker com Nginx, PHP, MySQL e Node.
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

- [ ] Criar dashboard operacional.
- [x] Configurar e validar o clã pela API oficial.
- [x] Sincronizar e preservar o histórico de membros.
- [x] Registrar histórico de guerras.
- [x] Capturar ataques e defesas detalhados disponíveis.
- [ ] Criar inscrições para Liga de Guerra de Clãs.
- [ ] Registrar histórico de CWL.
- [ ] Criar métricas de desempenho por jogador.
