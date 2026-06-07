# Frontend Working Guide

## Purpose

This frontend is the customer-facing Bookify React SPA. Keep changes small, behavior-preserving, and consistent with the current e-commerce UI. The Laravel backend and Filament admin live outside this frontend scope.

## Tech Stack

- React 18 with Vite.
- React Router for client-side routes.
- Tailwind CSS v4 through `@tailwindcss/vite`.
- Axios services for REST API calls.
- Context API for shared state: auth, cart, wishlist.
- npm with `package-lock.json`; do not add pnpm/yarn lockfiles.

## Directory Structure

- `src/App.jsx`: app shell only, such as `BrowserRouter` and global providers mounted above it.
- `src/routes/AppRouter.jsx`: all route definitions, redirects, and route guards.
- `src/pages/`: route-level screens grouped by domain.
- `src/components/`: reusable UI, layout, and domain components.
- `src/context/`: React Context providers and hooks for cross-page state.
- `src/services/`: API modules. UI code should call services, not raw axios.
- `src/hooks/`: reusable React hooks.
- `src/utils/`: pure helpers such as formatting, validation, and media URL resolution.
- `src/styles/`: global CSS and design tokens.
- `src/assets/`: static images/icons imported by the app.

## Styling And Design Tokens

- Manage frontend color and font tokens in `src/styles/theme.css`.
- Manage reusable component styling in `src/styles/components.css` with semantic class names such as `app-card`, `app-section-card`, `app-primary-button`, `site-header`, `bookify-footer`, `product-card`, and `chatbot-fab`.
- Manage third-party library overrides in `src/styles/vendor.css`. Do not put library override CSS next to feature components.
- Do not create `.css` files inside feature/component folders such as `src/components/Home`; import centralized style files from `src/index.css`.
- Do not hard-code brand hex colors in JSX. Use semantic component classes first, then Tailwind token classes such as `text-primary`, `bg-primary`, `border-primary`, `bg-danger`, `bg-auth-surface`, and `bg-chat-surface` when a one-off utility is genuinely clearer.
- Avoid raw Tailwind palette utilities for reusable visual decisions in shared UI (`bg-green-*`, `text-gray-*`, `border-gray-*`, `shadow-[...]`, `bg-[...]`, `text-[#...]`). Add or reuse a semantic token/class instead.
- Add a new token to `theme.css` before reusing a custom color in more than one place.
- Use `container mx-auto px-8 lg:px-10` as the standard responsive page wrapper.
- Do not add a custom fixed-width container unless a feature has a clear layout requirement that Tailwind's responsive container cannot satisfy.
- Main content uses `bg-page-surface`; route pages should not set their own full-screen background unless they use a special layout such as auth.
- Main content sections should generally be white cards with a light border/shadow.
- Use Tailwind utilities for layout and spacing unless a shared global rule is genuinely needed.
- Keep `index.css` minimal: Tailwind import plus project style imports.

## State And Data

- Keep Context API as the default state management approach.
- Do not add Redux for the current project size.
- Keep server communication in `src/services/*Api.js`; use `axiosClient` for credentials, CSRF, and global error handling.
- Categories, books, cart, wishlist, orders, and profile data should come from backend APIs, not hard-coded frontend fixtures.

## Routing

- Add or change routes in `src/routes/AppRouter.jsx`.
- Keep legacy redirects when removing or replacing public URLs.
- Protected customer pages should use the existing auth guard pattern.
- Use case-correct import paths because production builds may run on case-sensitive file systems.

## Refactor Policy

- Do not split large files only because they are long.
- Split components when editing that area and one of these is true: repeated UI, isolated form/modal logic, hard-to-test branching, or a clear reusable domain component.
- Avoid broad visual rewrites unless the task explicitly asks for redesign.
- Preserve existing Vietnamese UI copy unless the task is about copy or encoding cleanup.

## Verification Checklist

- Run `npm run lint` after frontend code changes.
- Run `npm run build` before finishing structural, routing, dependency, or styling changes.
- Search for hard-coded project colors before finishing theme work.
- Confirm no `pnpm-lock.yaml` or unused state-management dependency is introduced.
