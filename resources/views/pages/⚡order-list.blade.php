<?php

use App\Models\Order;
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
     * Reset to page 1 whenever the search term changes, so results don't get stuck on an empty later page.
     */
    public function updatingSearch(): void
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
        $orders = Order::query()
            ->with('user')
            // Only orders belonging to users whose name matches the search term.
            // Because we're querying orders (not users) directly, users with zero orders
            // are automatically excluded — satisfies "only show users with orders".
            ->whereHas('user', fn($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->latest('ordered_at')
            ->paginate(10);

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

<div>
    {{-- wire:model.live re-runs the orders() computed property automatically on every change.
         debounce.100ms waits briefly after typing stops before sending the request. --}}
    <input type="text" wire:model.live.debounce.100ms="search" placeholder="Search by username...">

    <table>
        <thead>
            <tr>
                <th>Gebruikersnaam</th>
                <th>Orderbedrag (origineel)</th>
                <th>Orderbedrag (EUR)</th>
                <th>Orderdatum</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->orders as $order)
            <tr>
                <td>{{ $order->user->name }}</td>
                <td>{{ number_format((float) $order->amount, 2) }} {{ $order->user->country_code }}</td>
                <td>
                    @if ($order->amount_eur !== null)
                    {{ number_format($order->amount_eur, 2) }} EUR
                    @else
                    {{-- Conversion failed (API/key unavailable) — fail gracefully instead of crashing. --}}
                    unavailable
                    @endif
                </td>
                <td>{{ $order->ordered_at->format('d-m-Y H:i') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4">No orders found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div>
        {{ $this->orders->links() }}
    </div>
</div>