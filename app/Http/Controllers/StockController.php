<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['permission:stock.view'];
    }

    public function index(Request $request): Response
    {
        $items = StockItem::ordered()->get(['id', 'name', 'unit', 'reorder_level', 'item_order', 'is_active'])
            ->map(fn (StockItem $i) => [
                ...$i->only('id', 'name', 'unit', 'reorder_level', 'item_order', 'is_active'),
                'on_hand' => $i->on_hand,
            ]);

        $movements = StockMovement::query()
            ->with(['item:id,name,unit', 'user:id,full_name,name'])
            ->between($request->query('from'), $request->query('to'))
            ->when($request->query('item'), fn ($q, $v) => $q->where('stock_item_id', $v))
            ->orderByDesc('moved_on')->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (StockMovement $m) => [
                'id' => $m->id,
                'moved_on' => $m->moved_on?->toDateString(),
                'item' => $m->item?->name,
                'unit' => $m->item?->unit,
                'direction' => $m->direction,
                'quantity' => $m->quantity,
                'user' => $m->user?->display_name,
                'description' => $m->description,
            ]);

        return Inertia::render('Stock/Index', [
            'items' => $items,
            'movements' => $movements,
            'filters' => $request->only('from', 'to', 'item'),
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        StockItem::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:30'],
            'reorder_level' => ['integer', 'min:0'],
            'item_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'نام کالا']));

        return back()->with('success', 'کالا ثبت شد.');
    }

    public function updateItem(Request $request, StockItem $item): RedirectResponse
    {
        $this->authorizeManage();

        $item->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:30'],
            'reorder_level' => ['integer', 'min:0'],
            'item_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]));

        return back()->with('success', 'کالا به‌روزرسانی شد.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'stock_item_id' => ['required', 'exists:stock_items,id'],
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'integer', 'min:1'],
            'moved_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'stock_item_id' => 'کالا',
            'quantity' => 'تعداد',
            'moved_on' => 'تاریخ',
        ]);

        $item = StockItem::findOrFail($data['stock_item_id']);

        // Refuse to take out more than the ledger says is there; a negative
        // stock level is always a data-entry mistake, not a real state.
        if ($data['direction'] === 'out' && $data['quantity'] > $item->on_hand) {
            return back()->withErrors([
                'quantity' => sprintf('موجودی کافی نیست. موجودی فعلی: %d', $item->on_hand),
            ]);
        }

        $movement = StockMovement::create([...$data, 'user_id' => $request->user()->id]);
        ActivityLogger::record('created', $movement, 'ثبت گردش انبار');

        return back()->with('success', 'گردش انبار ثبت شد.');
    }

    private function authorizeManage(): void
    {
        abort_unless(request()->user()->can('stock.manage'), 403);
    }
}
