# Clash of Clans Clan Management

Sistema web para gerenciamento de clãs de Clash of Clans.

O projeto está em desenvolvimento. A primeira etapa entrega autenticação por
usuário e senha, vinculando cada conta a uma player tag de jogador. No cadastro, o backend consulta a API oficial e permite somente jogadores pertencentes aos clãs
configurados.

## Tecnologias

- PHP 8.4 e Laravel 13
- React 18
- Inertia.js
- Tailwind CSS
- MySQL 8.4
- Vite
- Docker Compose

## Funcionalidades atuais

- Cadastro com username, senha e player tag.
- Login e logout com sessão Laravel.
- Validação do jogador pela API oficial do Clash of Clans.
- Player tag única por usuário.
- Modo de demonstração sem dependência da API externa.
- Dashboard inicial protegido por autenticação.

> [!IMPORTANT]
> A validação atual confirma que a player tag existe e pertence a um clã
> autorizado, mas ainda não comprova que o visitante controla a conta do jogo.

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

## Modo de demonstração

O `.env.example` vem preparado para testar o cadastro sem criar uma chave na
API do Clash of Clans:

```env
APP_ENV=local
CLASH_OF_CLANS_CLAN_TAG="#QGRJ2"
CLASH_OF_CLANS_DEMO_MODE=true
```

Na tela de cadastro, use qualquer player tag com formato válido, por exemplo:

```text
#PQLG2
```

## Usando a API oficial

Crie uma chave no
[portal de desenvolvedores do Clash of Clans](https://developer.clashofclans.com/)
e configure o `.env`:

```env
CLASH_OF_CLANS_API_URL=https://api.clashofclans.com/v1
CLASH_OF_CLANS_API_TOKEN=seu_token
CLASH_OF_CLANS_CLAN_TAG="#TAG_DO_CLA"
CLASH_OF_CLANS_DEMO_MODE=false
```

Para autorizar múltiplos clãs, separe as tags com `|`:

```env
CLASH_OF_CLANS_CLAN_TAG="#TAG_CLA_1|#TAG_CLA_2|#TAG_CLA_3"
```

Depois de alterar o ambiente:

```bash
docker compose exec php-fpm php artisan config:clear
```

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

## Estrutura inicial do banco

A migration inicial cria apenas:

- `users`: credenciais e identidade do jogador no Clash of Clans.
- `sessions`: sessões autenticadas da aplicação.

Cache usa arquivos locais e filas são executadas de forma síncrona nesta fase.

## Roadmap

### Base do projeto

- [x] Configurar Laravel, React e Inertia.js.
- [x] Configurar ambiente Docker com Nginx, PHP, MySQL e Node.
- [x] Criar migration inicial sem tabelas especulativas.
- [x] Adicionar testes automatizados de autenticação e cadastro.
- [x] Documentar decisões técnicas em `architecture.md`.

### Autenticação

- [x] Cadastro com username, senha e player tag.
- [x] Login por username e senha.
- [x] Logout e proteção do dashboard.
- [x] Validar jogador pela API oficial.
- [x] Restringir cadastro aos clãs configurados.
- [x] Permitir múltiplos clãs.
- [x] Impedir player tags duplicadas.
- [x] Criar modo de demonstração.
- [ ] Comprovar posse da conta usando o token pessoal do jogador.
- [ ] Definir fluxo seguro de recuperação de acesso.
- [ ] Revalidar periodicamente clã e cargo do jogador.

### Gerenciamento do clã

- [ ] Criar dashboard operacional.
- [ ] Sincronizar dados dos clãs e jogadores.
- [ ] Criar inscrições para Liga de Guerra de Clãs.
- [ ] Registrar histórico de guerras.
- [ ] Registrar histórico de CWL.
- [ ] Criar métricas de desempenho por jogador.
- [ ] Criar permissões administrativas para líderes e colíderes.
