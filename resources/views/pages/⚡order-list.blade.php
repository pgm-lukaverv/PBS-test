<?php

use App\Models\Order;
use App\Models\User;
use App\Services\CurrencyConversionService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /**
     * The current search term, bound to the search input. Filters orders by the owning user's name.
     */
    public string $search = '';

    /**
     * How to sort the order list: 'recent' (default, most recent order first) or 'name'
     * (by the owning user's name, A-Z).
     */
    public string $sortBy = 'recent';

    /**
     * Reset to page 1 whenever the search term changes, so results don't get stuck on an empty later page.
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset to page 1 whenever the sort order changes too, for the same reason as the search reset.
     */
    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    /**
     * Orders matching the current search term, each with its live EUR-converted amount attached.
     *
     * This is a #[Computed] property rather than a render() method: Livewire v4 single-file
     * components auto-render their inline Blade template below, so derived data for that
     * template is exposed this way instead of via a manual view() call.
     */
    #[Computed]
    public function orders()
    {
        $query = Order::query()
            ->with('user')
            // Only orders belonging to users whose name matches the search term.
            // Because we're querying orders (not users) directly, users with zero orders
            // are automatically excluded — satisfies "only show users with orders".
            ->whereHas('user', fn($query) => $query->where('name', 'like', '%' . $this->search . '%'));

        // 'name' sorts by the related user's name. Order has no name column itself, so we order by
        // a correlated subquery selecting it from users — Eloquent's orderBy() accepts a subquery
        // directly for exactly this case. 'recent' (default) just sorts by order date.
        $query = $this->sortBy === 'name'
            ? $query->orderBy(User::select('name')->whereColumn('id', 'orders.user_id'))
            : $query->latest('ordered_at');

        $orders = $query->paginate(10);

        // Attach the live EUR conversion to each order. This is a derived value, not stored data,
        // so it's computed here at render time rather than persisted on the model.
        $orders->getCollection()->transform(function (Order $order) {
            $order->amount_eur = app(CurrencyConversionService::class)->convertToEur(
                (float) $order->amount,
                $order->user->country_code
            );

            return $order;
        });

        return $orders;
    }
};
?>

<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold text-gray-900">Orders</h1>
    <p class="mt-1 text-sm text-gray-500">Search by username to filter orders. Amounts are shown in the user's original currency and converted to EUR using live exchange rates.</p>

    <div class="mt-6 flex flex-wrap items-end gap-4">
        {{-- wire:model.live re-runs the orders() computed property automatically on every change.
             debounce.100ms waits briefly after typing stops before sending the request. --}}
        <div class="flex-1 min-w-[16rem] max-w-sm">
            <label for="search" class="mb-1 block text-sm font-medium text-gray-700">Search</label>
            <input
                id="search"
                type="text"
                wire:model.live.debounce.100ms="search"
                placeholder="Search by username..."
                class="block w-full rounded-md border-0 px-3 py-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm">
        </div>

        {{-- Sort toggle: 'recent' (default) or 'name'. wire:model.live re-runs orders()
             immediately on change, same mechanism as the search input. --}}
        <div>
            <label for="sortBy" class="mb-1 block text-sm font-medium text-gray-700">Sort by</label>
            <select
                id="sortBy"
                wire:model.live="sortBy"
                class="block rounded-md border-0 py-2 pl-3 pr-8 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm">
                <option value="recent">Most recent</option>
                <option value="name">Username (A-Z)</option>
            </select>
        </div>
    </div>

    {{-- min-h keeps the table area a constant height (header + 10 rows), so a last page with
         fewer results doesn't shrink the table and make the pagination bar jump up. --}}
    <div class="mt-6 min-h-123 overflow-hidden rounded-lg shadow ring-1 ring-black/5">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Gebruikersnaam</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Orderbedrag (origineel)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Orderbedrag (EUR)</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Orderdatum</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($this->orders as $order)
                <tr wire:key="{{ $order->id }}" class="hover:bg-gray-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $order->user->name }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ number_format((float) $order->amount, 2) }} {{ $order->user->country_code }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                        @if ($order->amount_eur !== null)
                        &euro;&nbsp;{{ number_format($order->amount_eur, 2) }}
                        @else
                        {{-- Conversion failed (API/key unavailable) — fail gracefully instead of crashing. --}}
                        <span class="italic text-red-500">unavailable</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $order->ordered_at->format('d-m-Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No orders found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->orders->links() }}
    </div>
</div>