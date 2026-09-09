# Process Log

A chronological log of the steps followed while building this test, the reasoning behind key decisions, and the issues that came up along the way.

## 1. Read the assignment

Requirements: fetch orders from storage, display each order's amount in the user's original currency (EUR/USD/GBP) and converted to EUR via the OpenExchangeRates API, a searchable/paginated list styled with CSS, and a process log with code comments. Technical constraints: Laravel, OpenExchangeRates, Tailwind. The free OpenExchangeRates plan only returns USD-based rates, so GBP → EUR needed to go via a USD cross-rate.

## 2. Data layer

- Extended the default `users` migration with a `country_code` column (`EUR`/`USD`/`GBP`), instead of creating a separate table — kept the schema minimal since this app has no auth requirements beyond the data itself.
- Created an `orders` migration: `user_id` (FK, cascade delete), `amount` as `decimal(10,2)` (never `float`, to avoid money rounding errors), and a dedicated `ordered_at` timestamp separate from Laravel's own `created_at`/`updated_at`.
- Added the `User::orders()` / `Order::user()` Eloquent relationship.
- Discovered this Laravel version (13.17) uses the newer `#[Fillable([...])]` PHP attribute for mass assignment instead of the classic `protected $fillable` property — updated both models to follow that convention consistently.

## 3. Seeding test data

Wrote factories (`UserFactory` picks a random currency per user, `OrderFactory` generates realistic amounts/dates) and a `DatabaseSeeder` that creates 20 users and attaches a random 0-5 orders to each. Users with 0 orders are intentional — the spec requires that only users *with* orders appear in search results, so the seeder needed to guarantee some users have none in order to actually exercise that rule.

## 4. Currency conversion service

Built `app/Services/CurrencyConversionService.php`:
- Fetches live rates from OpenExchangeRates (`/latest.json`), which on the free tier are always USD-based.
- EUR amounts pass through unchanged.
- USD → EUR is a direct multiplication by the EUR rate.
- GBP (and any other non-USD currency) is converted via a USD cross-rate: `amount ÷ rate(currency) = amount in USD`, then `× rate(EUR) = amount in EUR`.
- Rates are cached for 1 hour (`Cache::remember`) to respect the free plan's request quota and avoid re-fetching on every page load — satisfies the caching bonus.
- Returns `null` (with a logged warning/error) instead of throwing when the API is unreachable or a currency is missing, so the UI can degrade gracefully instead of crashing.
- The API key lives in `config/services.php` → `env('OPENEXCHANGERATES_API_KEY')`, so it can be rotated via `.env` alone.

Verified manually via `php artisan tinker` before wiring it into the UI.

## 5. Livewire (TALL stack) for the UI

Installed `livewire/livewire` (v4, confirmed via `composer show`), which turned out to have a notably different component model than expected:
- v4 defaults to **single-file components** (`resources/views/.../⚡name.blade.php`) with a PHP class inline above the Blade template — no separate class/view files.
- Full-page components (routed directly, not embedded in another view) belong in a `pages::` namespace by convention (`resources/views/pages/`), not the general-purpose `components/` folder — moved the component there once this was clarified.
- Derived/computed data for the inline template is exposed via a `#[Computed]` method (e.g. `orders()`, accessed as `$this->orders` in the view) rather than a `render()` method returning `view(...)` — single-file components auto-render their inline template, so there's no separate view path to return.

The `OrderList` page component (`resources/views/pages/⚡order-list.blade.php`):
- `public string $search` bound via `wire:model.live.debounce.100ms`, giving realtime search with no custom JavaScript.
- The `#[Computed] orders()` method queries `Order::query()->whereHas('user', ...)->paginate(10)`, filtering by the related user's name. Because the query starts from `Order`, not `User`, users with zero orders are structurally excluded from results — no extra filtering logic needed.
- Each order gets its `amount_eur` attached at render time via `CurrencyConversionService`.
- `WithPagination` trait handles the pagination bonus.

## 6. Routing and layout

Wired the root route with `Route::livewire('/', 'pages::order-list')` (the v4-recommended routing method) and deleted the now-unused default `welcome.blade.php`. Hit an initial `View [app] not found` error — the `php artisan livewire:layout` command reported success but hadn't actually written the layout file to disk; re-running it produced the real `resources/views/layouts/app.blade.php` with `{{ $slot }}`, `@livewireStyles`/`@livewireScripts`, and Vite asset tags.

## 7. Styling

Deliberately built and verified all functionality (search, conversion, pagination) *before* styling, to keep functional correctness and visual polish as separate, independently-checkable concerns. Applied Tailwind utility classes for a clean card-style table, styled search input, and page heading — matching the spec's "basic CSS, neat table view" requirement rather than over-designing it.

**Bug found during a live browser check:** Livewire's built-in pagination view ships its own `dark:` Tailwind classes, which activated because the testing browser was in OS dark mode — while the rest of the custom markup (with no dark-mode classes) stayed light, causing a visibly inconsistent, "broken-looking" pagination bar. Since this app doesn't support a dark theme at all, fixed it by overriding Tailwind's `dark:` variant to be class-based instead of `prefers-color-scheme`-based (`@custom-variant dark (&:where(.dark, .dark *));` in `app.css`), so it simply never activates unless a `.dark` class is deliberately added — which it isn't.

## 8. Code comments

Went back through every file touched (migrations, models, factories, seeder, service, config, routes, Blade template) adding explanatory comments — focused on *why* a choice was made (e.g. why `decimal` not `float`, why the seeder guarantees some 0-order users, why the GBP cross-rate math works the way it does) rather than restating what the code obviously does.

## 9. Architecture note: no traditional controllers

This app has no `Http/Controllers` — the Livewire component fills that role directly (route → component → model/service → inline view), which is the standard pattern for full-page Livewire/TALL-stack components. The spec's bonus item mentions "unit tests for controller logic"; since there's no controller, the equivalent coverage is: unit tests on `CurrencyConversionService` (the actual business logic, framework-agnostic) plus a Livewire component test (`Livewire::test('pages::order-list')`) for the search/filtering/pagination behavior.

## 10. Tests (bonus)

Added Pest feature tests covering both pieces of business logic identified in the architecture note above:

- `tests/Feature/Services/CurrencyConversionServiceTest.php` — using `Http::fake()` to intercept the OpenExchangeRates call (no real API/network dependency, no quota usage, deterministic rates): EUR passes through unchanged with zero HTTP calls made, USD → EUR and GBP → EUR cross-rate math are both verified against hand-calculated expected values, caching is verified via `Http::assertSentCount(1)` after two conversions, and both API failure and a missing-currency response correctly return `null`.
- `tests/Feature/Livewire/OrderListTest.php` — using `Livewire::test('pages::order-list')`: the page renders successfully, a user with zero orders never appears in the list, searching by username filters correctly, and pagination correctly limits to 10 orders per page then shows the remainder on the next page.

Along the way, enabled `RefreshDatabase` in `tests/Pest.php` (it ships commented out by default) — without it, the Livewire component tests failed with "no such table: users", since the in-memory SQLite test database was never actually migrated between tests.

All 12 tests pass (`php artisan test --compact`).

## 11. README

Replaced Laravel's default boilerplate `README.md` with a project-specific one: what the app does, the stack, and step-by-step setup (install, `.env` + API key, migrate/seed, run). Kept it separate from this process log rather than merging the two — the README is what someone opens first to get the app running; this log is the narrative of how/why it was built. Linked from the README so nothing is buried.

## 12. Final review against the spec

Did a last line-by-line pass of the original assignment PDF against the running app before calling this done:

- **Gap found:** the seeder generated only random Faker data — the spec's own example dataset (Alice Johnson/EUR, Bob Smith/USD, Carol Davis/GBP, with their example order amounts/dates) was never actually seeded. The spec's note *"je mag altijd extra records toevoegen, eventueel geautomatiseerd"* (you may always add extra records, optionally automated) implies those 3 example users are the baseline you're given, with extra records layered on top — not a random dataset replacing them. Fixed by seeding those exact 3 users/orders explicitly, then generating 20 additional random users/orders on top, as before. This also guarantees all 3 currencies are always represented, rather than merely near-certain with pure randomness.
- Verified column labels, per-order (not aggregated) amount display, GBP-via-USD conversion, error handling, and the "only users with orders" rule all match the "Functionele Vereisten" section exactly.
- Re-ran the full test suite (12/12 passing) and a manual git history scan confirming the real OpenExchangeRates API key was never committed anywhere — only the `your_app_id_here` placeholder lives in `.env.example`.

## 13. Sort toggle (added after review feedback)

Feedback: the list should support both "most recent first" and alphabetical ordering, with a sensible default rather than forcing one. Added a `$sortBy` property (`'recent'` default, or `'name'`) bound to a `<select>` next to the search input, with an `updatingSortBy()` hook resetting pagination the same way search does. Sorting by username required a correlated subquery (`User::select('name')->whereColumn('id', 'orders.user_id')`) since `orders` has no `name` column of its own — Eloquent's `orderBy()` accepts a subquery builder directly for this. Verified in the browser: alphabetical mode correctly surfaces Alice/Bob/Carol first, then continues A-Z; full test suite still green (12/12).
