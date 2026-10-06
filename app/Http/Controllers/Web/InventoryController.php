<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StaffMember;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Export\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Asset register: furniture, devices and equipment, where they are, their state and who holds them. */
class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', Rule::in(InventoryItem::CONDITIONS)],
        ]);

        return Inertia::render('Inventory/Index', [
            'items' => $this->query($filters)->with('custodian')->orderBy('category')->orderBy('name')->paginate(50)->withQueryString()
                ->through(fn (InventoryItem $i) => [
                    ...$i->only(['id', 'code', 'name', 'category', 'location', 'quantity', 'condition', 'custodian_id', 'notes']),
                    'unit_value' => $i->unit_value, 'purchased_on' => $i->purchased_on?->toDateString(), 'custodian' => $i->custodian?->name,
                ]),
            'categories' => InventoryItem::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'totals' => [
                'items' => InventoryItem::query()->where('condition', '!=', 'disposed')->sum('quantity'),
                'value' => round((float) InventoryItem::query()->where('condition', '!=', 'disposed')->selectRaw('sum(quantity * coalesce(unit_value, 0)) as v')->value('v'), 2),
                'needs_repair' => InventoryItem::query()->whereIn('condition', ['needs_repair', 'damaged'])->count(),
            ],
            'staff' => StaffMember::query()->where('status', 'active')->orderBy('name_ar')->get()->map(fn (StaffMember $s) => ['id' => $s->id, 'name' => $s->name]),
            'conditions' => InventoryItem::CONDITIONS,
            'filters' => $filters,
        ]);
    }

    public function save(Request $request, ?InventoryItem $item = null): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'condition' => ['required', Rule::in(InventoryItem::CONDITIONS)],
            'purchased_on' => ['nullable', 'date', 'before_or_equal:today'],
            'unit_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'custodian_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('staff_members')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $item ? $item->update($data) : InventoryItem::query()->create($data);

        return back()->with('success', __('Changes saved.'));
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $rows = $this->query($request->only(['search', 'category', 'condition']))->with('custodian')->orderBy('category')->orderBy('name')->get()
            ->map(fn (InventoryItem $i) => [$i->code, $i->name, $i->category, $i->location, $i->quantity, __('condition.'.$i->condition),
                $i->purchased_on?->toDateString(), $i->unit_value, $i->custodian?->name, $i->notes]);

        return Spreadsheet::download('inventory.xlsx', [__('Inventory') => [[
            __('Code'), __('Name'), __('Category'), __('Location'), __('Quantity'), __('Condition'), __('Purchased on'), __('Unit value'), __('Custodian'), __('Notes'),
        ], $rows]]);
    }

    /** @param  array<string, mixed>  $filters */
    private function query(array $filters)
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        return InventoryItem::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', $search)->orWhere('location', 'like', $like)))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($filters['condition'] ?? null, fn ($q, $c) => $q->where('condition', $c));
    }
}
