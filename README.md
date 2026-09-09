# PBS Test — Order Currency Converter

A Laravel + Livewire app that lists customer orders, showing each order's amount in the user's original currency (EUR/USD/GBP) and converted live to EUR via the [OpenExchangeRates](https://openexchangerates.org/) API. Search by username, paginated results, realtime search with no page reloads.

See [`PROCESS_LOG.md`](./PROCESS_LOG.md) for a full log of the development process, decisions made, and issues hit along the way.

## Stack

- Laravel 13 (PHP 8.5)
- Livewire 4 (TALL stack) for realtime search + pagination
- Tailwind CSS 4
- SQLite

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Get a free OpenExchangeRates API key at [openexchangerates.org/signup/free](https://openexchangerates.org/signup/free), then set it in `.env`:

```
OPENEXCHANGERATES_API_KEY=your_app_id_here
```

Set up the database (SQLite by default) and seed it with sample users/orders:

```bash
php artisan migrate:fresh --seed
```

## Running

```bash
composer run dev
```

Starts the Laravel dev server and Vite together. Visit `http://127.0.0.1:8000`.

## Testing

```bash
php artisan test --compact
```
