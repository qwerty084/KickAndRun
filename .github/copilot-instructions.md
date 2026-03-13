# Copilot Instructions for Board Games

This is a monorepo containing board game implementations. Each game lives in its own top-level directory (currently only `frontend/`).

## KickAndRun (frontend/)

Vue 3 + TypeScript board game UI built with Vite. Visualizes a 4-player "Kick and Run" style board game.

### Commands

All commands must be run from the `frontend/` directory:

```sh
npm run dev              # Start Vite dev server with HMR
npm run build            # Type-check + production build (parallel via npm-run-all2)
npm run build-only       # Build without type-checking
npm run type-check       # Run vue-tsc --build --force
npm run lint             # ESLint with auto-fix across all source files
npm run format           # Prettier on src/
npm run test:unit        # Run Vitest unit tests (single run)
npm run test:unit:watch  # Run Vitest in watch mode
npm run test:e2e         # Run Playwright e2e tests (requires built app)
```

**Before pushing changes:**
- Run `npm run test:unit` and `npm run test:e2e`.
- If any tests fail, investigate and fix the issue.
- Re-run the tests until all pass, then push your changes.

To run a single unit test file: `npx vitest run src/__tests__/PlayingField.spec.ts`

CI (`.github/workflows/KickAndRun.yml`) runs lint → type-check → unit tests → build → e2e tests on every push/PR using Node 22.

### Architecture

- **Component hierarchy**: `App.vue` → `TheBoard.vue` → `BaseElement.vue` / `PlayingField.vue`
- **TheBoard** renders a 3×3 CSS grid. Corners hold player bases (`BaseElement`), edges hold walking paths, and the center holds a cross-shaped layout — all built from `PlayingField` circles.
- **Four players** (green, yellow, red, black) each have a base and a color-coded path. Board symmetry is achieved by CSS rotation classes (`rotate-90`, `rotate-180`, `-rotate-90`) rather than duplicated layout logic.
- **State management**: Pinia is wired up (`main.ts`) with a template counter store, but no game state exists yet. Data flow is currently props-only, top-down.
- **Tests**: Unit tests live in `src/__tests__/` (Vitest + `@vue/test-utils`). E2e tests live in `e2e/` (Playwright, targeting `http://localhost:4173` via `npm run preview`).

### Key tooling notes

- **ESLint 9** uses flat config (`eslint.config.js`) — there is no `.eslintrc` file.
- **Tailwind CSS v4**: configured CSS-first. Use `@import "tailwindcss"` in `base.css`. In component `<style scoped>` blocks that use `@apply`, add `@reference "tailwindcss";` at the top. No `tailwind.config.js`.
- **Vitest** has its own `vitest.config.ts` (separate from `vite.config.ts`) due to a Vite 8 / Vitest 3 type boundary. Unit tests are scoped to `src/**/*.{spec,test}.ts` to avoid picking up Playwright files.
- **Playwright** e2e config is in `playwright.config.ts`. It auto-starts `npm run preview` as the web server.

### Conventions

- **Composition API only** — use `<script setup lang="ts">` in all Vue components.
- **Typed props** — define an `interface Props` and use `withDefaults(defineProps<Props>(), { ... })`.
- **Import alias** — `@/` maps to `src/` (configured in both Vite and tsconfig).
- **Custom types** live in `src/types/` (e.g., `Color` union type in `Colors.ts`).
- **Pinia stores** use the composition API style (`defineStore` with setup function returning refs/computed/functions).
- **Styling**: Tailwind CSS utility classes in templates; `@apply` in `<style scoped>` blocks (prefix with `@reference "tailwindcss";`). Responsive breakpoints use `sm:` and `md:` prefixes (mobile-first). Dark mode uses `media` strategy (Tailwind v4 default).
- **Formatting**: Prettier — double quotes, semicolons, 2-space indent, 120-char line width, ES5 trailing commas.
