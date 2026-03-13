# Copilot Instructions for Board Games

This is a monorepo containing board game implementations. Each game lives in its own top-level directory (currently only `KickAndRun/`).

## KickAndRun

Vue 3 + TypeScript board game UI built with Vite. Visualizes a 4-player "Kick and Run" style board game.

### Commands

All commands must be run from the `KickAndRun/` directory:

```sh
npm run dev            # Start Vite dev server with HMR
npm run build          # Type-check + production build (parallel via npm-run-all2)
npm run build-only     # Build without type-checking
npm run type-check     # Run vue-tsc --build --force
npm run lint           # ESLint with auto-fix across all source files
npm run format         # Prettier on src/
```

There are no tests yet. CI (`.github/workflows/KickAndRun.yml`) runs lint → type-check → build on every push/PR using Node 18.

### Architecture

- **Component hierarchy**: `App.vue` → `TheBoard.vue` → `BaseElement.vue` / `PlayingField.vue`
- **TheBoard** renders a 3×3 CSS grid. Corners hold player bases (`BaseElement`), edges hold walking paths, and the center holds a cross-shaped layout — all built from `PlayingField` circles.
- **Four players** (green, yellow, red, black) each have a base and a color-coded path. Board symmetry is achieved by CSS rotation classes (`rotate-90`, `rotate-180`, `-rotate-90`) rather than duplicated layout logic.
- **State management**: Pinia is wired up (`main.ts`) with a template counter store, but no game state exists yet. Data flow is currently props-only, top-down.

### Conventions

- **Composition API only** — use `<script setup lang="ts">` in all Vue components.
- **Typed props** — define an `interface Props` and use `withDefaults(defineProps<Props>(), { ... })`.
- **Import alias** — `@/` maps to `src/` (configured in both Vite and tsconfig).
- **Custom types** live in `src/types/` (e.g., `Color` union type in `Colors.ts`).
- **Pinia stores** use the composition API style (`defineStore` with setup function returning refs/computed/functions).
- **Styling**: Tailwind CSS utility classes in templates; `@apply` in `<style scoped>` blocks. PostCSS nesting is enabled. Responsive breakpoints use `sm:` and `md:` prefixes (mobile-first). Dark mode uses `media` strategy.
- **Formatting**: Prettier — double quotes, semicolons, 2-space indent, 120-char line width, ES5 trailing commas.
