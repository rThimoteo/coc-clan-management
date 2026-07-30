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
- Administração de múltiplos clãs, com validação pela API e definição de um
  clã padrão.
- Seletor de clã na top bar, isolando dashboard, membros e guerras pelo
  contexto ativo.
- Dashboard operacional com membros ativos, guerras do mês, taxa de vitória e
  confrontos recentes.
- Painel paginado de membros com busca, filtros por status, CV e cargo, além de
  ordenação pelas colunas.
- Histórico de membros preservado pelos status `in` e `out`.
- Histórico paginado de guerras com filtro por resultado e captura persistente
  de ataques e defesas.
- Área separada para Liga de Guerra de Clãs, organizada por temporada, rodada
  e confrontos do clã ativo.
- Detalhes de desempenho por jogador, com métricas ofensivas e defensivas,
  gráfico temporal e históricos paginados.
- Alertas de guerra ativa, cronômetro em tempo real e atualização diretamente
  pela tela de detalhes.
- Administração paginada de usuários, busca por nome e códigos numéricos de
  seis dígitos.
- Alteração de papéis e exclusão protegida de contas.
- Vínculo de várias contas do jogo, inclusive de clãs diferentes, a um único
  usuário.
- Sincronização automática diária de membros e guerras de todos os clãs pelo
  scheduler.
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

### Preparação comum

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

Construa e inicie os containers:

```bash
docker compose build
docker compose up -d
```

Instale as dependências PHP e gere a chave da aplicação:

```bash
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate
```

O container Node instala as dependências e inicia o Vite automaticamente.

Depois, escolha um dos modos de execução abaixo. Em ambos os casos a aplicação
fica disponível em:

```text
http://localhost:8081
```

## Rodando no modo normal

Crie uma chave no
[portal de desenvolvedores do Clash of Clans](https://developer.clashofclans.com/)
e configure o `.env`:

```env
ADMIN_ACCESS_CODE=seu-codigo-inicial-seguro
CLASH_OF_CLANS_API_URL=https://api.clashofclans.com/v1
CLASH_OF_CLANS_API_TOKEN=seu_token
CLASH_OF_CLANS_DEMO_MODE=false
```

O token da API deve pertencer a uma chave que autorize o IP público de onde a
aplicação fará as requisições. Prepare o banco:

```bash
docker compose exec php-fpm php artisan config:clear
docker compose exec php-fpm php artisan migrate --seed
docker compose restart scheduler
```

Entre usando o valor de `ADMIN_ACCESS_CODE` e acesse **Administração → Clãs**.
Adicione cada tag encontrada no perfil do clã, com ou sem `#`; por exemplo,
`#2Q8L9Y0JP`. O sistema consulta a API e salva o nome e o emblema
correspondentes. Nessa tela também é possível definir qual clã será aberto por
padrão.

Use o seletor da top bar para trocar o clã ativo. Dashboard, membros, guerras e
detalhes são isolados por essa seleção; a administração de clãs e usuários é
compartilhada. Membros e guerras podem ser sincronizados pelos respectivos
painéis para o clã ativo. Os comandos e o scheduler percorrem todos os clãs.

O histórico de guerras precisa estar público no Clash of Clans. A sincronização
guarda os resumos do war log e captura os detalhes da guerra atual ou
recém-encerrada enquanto a API ainda os disponibiliza. Entradas agregadas sem
tag de oponente são resumos da temporada retornados pelo war log e não são
persistidas como guerras; elas alimentam a listagem histórica de temporadas
CWL.

A **Liga de Clãs** possui uma página e uma sincronização próprias. Os dados são
obtidos pelos resumos do war log, pelo grupo da CWL e pelas war tags de cada
rodada. A lista abre os detalhes da temporada e, dentro dela, cada confronto
detalhado. As guerras da Liga não aparecem misturadas ao histórico de guerras
regulares.

O contrato dos endpoints detalhados de CWL foi implementado e testado com as
estruturas publicadas na documentação oficial. Antes de considerar uma nova
temporada validada em produção, compare uma resposta real sanitizada com as
fixtures de teste, pois a API pode incluir variações não representadas na
documentação.

### Sincronização automática

O serviço Docker `scheduler` executa as sincronizações todos os dias no fuso
`America/Sao_Paulo`:

- membros às 03:00;
- guerras às 03:15.
- CWL às 03:30 fora da janela da Liga e a cada duas horas, entre 01:00 e 23:00,
  nos primeiros dez dias do mês.

O timezone da aplicação permanece em UTC para preservar corretamente os
horários retornados pela API. O fuso do scheduler é configurado separadamente:

```env
APP_TIMEZONE=UTC
SCHEDULE_TIMEZONE=America/Sao_Paulo
```

O serviço inicia junto com `docker compose up -d` e usa
`restart: unless-stopped`.

## Rodando no modo demo

O modo demo não precisa de chave da API. Configure o `.env`:

```env
ADMIN_ACCESS_CODE=demo-admin
CLASH_OF_CLANS_API_TOKEN=
CLASH_OF_CLANS_DEMO_MODE=true
```

Crie o banco e carregue os dados demonstrativos:

```bash
docker compose exec php-fpm php artisan config:clear
docker compose exec php-fpm php artisan migrate:fresh --seed
docker compose restart scheduler
```

O seeder cria dois clãs, define um deles como padrão e inclui players ativos e
antigos, participações compartilhadas, usuários vinculados e históricos de
guerras independentes com detalhes de ataques e defesas. O líder demo possui
uma conta em cada clã para demonstrar o vínculo multi-clã. O seeder pode ser
reaplicado sem duplicar esses dados:

```bash
docker compose exec php-fpm php artisan db:seed
```

Use `demo-admin` para entrar como administrador. Também existem acessos de
demonstração para validar as diferentes permissões:

| Papel | Código |
| --- | --- |
| Líder | `111111` |
| Colíder | `222222` |
| Membro | `333333` |

Todas as funcionalidades baseadas nos dados locais continuam disponíveis no
modo demo, incluindo filtros, detalhes de guerra e administração de acessos.
As rotas, botões, comandos e agendamentos de sincronização ficam desativados,
preservando os dados carregados pelos seeders.

> O modo demo contém códigos conhecidos e deve ser usado somente para
> demonstração ou desenvolvimento.

## Métricas dos jogadores

As métricas usam somente guerras concluídas, com detalhes capturados e
pertencentes ao clã ativo. O jogador também precisa aparecer entre os
participantes do lado administrado.

A amostra pode considerar as 5, 10 ou 20 guerras elegíveis mais recentes, ou
todo o histórico. Também pode ser filtrada entre guerras regulares e CWL.

As fórmulas são:

- **Ataques utilizados:** quantidade de ataques efetivamente registrados para
  o player.
- **Ataques disponíveis:** `2 × guerras regulares + 1 × guerras CWL`.
- **Média de estrelas ofensiva:** soma das estrelas dividida pelos ataques
  utilizados.
- **Destruição ofensiva média:** soma das destruições dividida pelos ataques
  utilizados.
- **Defesas sofridas:** quantidade de ataques registrados contra o player.
- **Média de estrelas cedidas:** soma das estrelas sofridas dividida pelas
  defesas registradas.
- **Destruição defensiva média:** soma das destruições sofridas dividida pelas
  defesas registradas.

Ataques disponíveis que não foram utilizados não são tratados como ataques de
zero estrela. Se a mesma base receber mais de uma defesa na guerra, cada ataque
é uma observação separada. Guerras em preparação ou andamento ficam fora das
métricas para que uma mesma janela produza resultados estáveis.

No gráfico, uma guerra sem ataque ou sem defesa permanece na amostra e no
denominador de disponibilidade, mas não gera um ponto artificial de `0%` para
a respectiva série.

## Comandos úteis

Executar testes:

```bash
docker compose exec php-fpm php artisan test
```

Sincronizar manualmente pela linha de comando:

```bash
docker compose exec php-fpm php artisan members:sync
docker compose exec php-fpm php artisan wars:sync
docker compose exec php-fpm php artisan cwl:sync
```

Consultar os próximos horários agendados:

```bash
docker compose exec php-fpm php artisan schedule:list
```

Recriar o banco local:

```bash
docker compose exec php-fpm php artisan migrate:fresh --seed
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
- `clans`: tag, nome, emblema e indicação do clã padrão.
- `players`: identidade global da conta do jogo e vínculo opcional com usuário.
- `clan_memberships`: situação, cargo e histórico do player em cada clã.
- `member_statuses`: estados de vínculo definidos pelo enum `MemberStatus`.
- `members`: estrutura legada mantida temporariamente durante a migração.
- `wars`: clã proprietário, placares, oponentes, resultado e disponibilidade
  de detalhes.
- `clan_war_leagues`: temporadas CWL pertencentes a cada clã.
- `clan_war_league_clans`: participantes registrados em cada temporada.
- `clan_war_league_rounds`: rodadas da temporada.
- `clan_war_league_round_wars`: war tags, pendências e vínculo com a guerra
  detalhada do clã ativo.
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
- [x] Registrar histórico de CWL em uma área separada.
- [ ] Criar métricas de desempenho por jogador.
