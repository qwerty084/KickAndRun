# 🎲 Kick and Run

A multiplayer online board game inspired by **Mensch ärgere dich nicht** — the beloved German classic. Create a lobby, invite friends (or add bots), and race your pieces home!

## Features

- **Multiplayer lobbies** — create/join games with a share code, 2–4 players
- **Bot players** — lobby hosts can add AI opponents that auto-play
- **Real-time updates** — Mercure SSE for instant game state sync
- **Full game rules** — 40-space circular path, must-exit-base on 6, kicking, goal lanes
- **Rematch** — play again with the same lobby after a game ends
- **Game activity log** — see every roll, move, and kick as it happens
- **Real-time chat** — message other players in the lobby and during gameplay
- **Piece animations** — smooth pop-in/pop-out transitions, kicked piece shake, dice roll animation
- **Sound effects** — audio feedback for dice rolls, moves, kicks, and wins (with mute toggle)
- **Authentication** — optional JWT login/register; guest play still works without an account
- **Connection status** — visual indicator when SSE drops or reconnects
- **Session persistence** — refresh the page without losing your seat
- **Responsive UI** — mobile-first with dark mode support

## Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | Vue 3, TypeScript, Pinia, Tailwind CSS v4, Vite 8 |
| Backend | Symfony 8.0, PHP 8.5, Doctrine ORM |
| Database | PostgreSQL 16 |
| Real-time | Mercure (built into FrankenPHP/Caddy) |
| Auth | JWT via lexik/jwt-authentication-bundle |
| Testing | Vitest, Playwright, PHPUnit, PHPStan |
| Infrastructure | Docker Compose, FrankenPHP |

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Node.js 22+

### Backend

```bash
cd backend
docker compose up -d
```

This starts:
- **PHP/Caddy** on `https://localhost` (self-signed cert)
- **PostgreSQL 16** on port 5432
- Migrations run automatically on startup

### Frontend

```bash
cd frontend
npm install
npm run dev
```

The Vite dev server proxies `/api` and `/.well-known/mercure` to `https://localhost` (the backend).

Open `http://localhost:5173` to play.

## Project Structure

```
board-games/
├── backend/                  # Symfony 8 API
│   ├── src/
│   │   ├── Controller/       # AuthController, ChatController, GameController,
│   │   │                     # HealthController, LobbyController
│   │   ├── Entity/           # ChatMessage, GameSession, Lobby, Player, User
│   │   └── Game/             # GameEngine, BotService
│   ├── tests/
│   ├── migrations/
│   └── compose.yaml
├── frontend/                 # Vue 3 SPA
│   ├── src/
│   │   ├── components/       # TheBoard, ChatPanel, AuthDialog, GameLog,
│   │   │                     # ConnectionStatus, LobbyCard, ...
│   │   ├── composables/      # useGame, useLobby, useChat, useMercure,
│   │   │                     # useSoundEffects, usePlayerSession, apiFetch
│   │   ├── stores/           # Pinia game store, auth store
│   │   ├── types/            # TypeScript interfaces
│   │   └── views/            # HomePage, LobbyRoom, GamePage
│   ├── e2e/                  # Playwright tests
│   └── src/__tests__/        # Vitest unit tests
└── .github/workflows/        # CI pipeline (frontend + backend)
```

## API Endpoints

### Auth (`/api/auth`)

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/auth/register` | Register a new user (returns JWT) |
| `POST` | `/auth/login` | Log in (returns JWT) |
| `GET` | `/auth/me` | Get current user (requires JWT) |

### Lobbies (`/api/lobbies`)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/lobbies` | List open lobbies |
| `POST` | `/lobbies` | Create lobby |
| `GET` | `/lobbies/{id}` | Get lobby details |
| `POST` | `/lobbies/{id}/join` | Join lobby |
| `POST` | `/lobbies/{id}/leave` | Leave lobby |
| `POST` | `/lobbies/{id}/add-bot` | Add bot (host only) |
| `POST` | `/lobbies/{id}/remove-bot` | Remove bot (host only) |
| `DELETE` | `/lobbies/{id}` | Delete lobby |
| `POST` | `/lobbies/{id}/start` | Start game |
| `GET` | `/lobbies/{id}/game` | Get game session ID |
| `POST` | `/lobbies/{id}/rematch` | Reset lobby for a new game |

### Games (`/api/games`)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/games/{id}` | Get game state |
| `GET` | `/games/{id}/player/{playerId}` | Validate player (rejoin) |
| `POST` | `/games/{id}/roll` | Roll dice |
| `POST` | `/games/{id}/move` | Move a piece |

### Chat (`/api/chat`)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/chat/lobby/{id}/messages` | Get lobby chat history |
| `POST` | `/chat/lobby/{id}/messages` | Send lobby message |
| `GET` | `/chat/game/{id}/messages` | Get game chat history |
| `POST` | `/chat/game/{id}/messages` | Send game message |

### Health

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/health` | Health check |

## Development

### Run Tests

```bash
# Backend (inside Docker)
cd backend
docker compose exec php bin/phpunit

# Frontend unit tests
cd frontend
npm run test:unit

# Frontend e2e tests
npm run test:e2e
```

Current test counts: **92 backend** + **154 frontend** = **246 tests**.

### Lint & Type Check

```bash
cd frontend
npm run lint          # ESLint with auto-fix
npm run type-check    # vue-tsc
npm run format        # Prettier
```

### Backend Static Analysis

```bash
cd backend
docker compose exec php vendor/bin/phpstan analyse --memory-limit=256M
```

## Game Rules

Based on the classic **Mensch ärgere dich nicht**:

- 4 players (green, yellow, red, black), each with 4 pieces
- Roll a 6 to move a piece out of base
- 3 roll attempts when all pieces are in base
- Land on an opponent's piece to kick it back to their base
- First player to get all 4 pieces into their goal lane wins
- Roll a 6 for an extra turn (max 3 consecutive)

## License

[MIT](LICENSE) — Luca Hendrik Helms
