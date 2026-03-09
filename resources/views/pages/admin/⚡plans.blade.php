<?php

use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new class extends Component
{
    use WithPagination, WireUiActions;

    // ──────────────────────────────────────────
    // Modal visibility flags
    // ──────────────────────────────────────────
    public bool $createModal = false;
    public bool $editModal   = false;

    // ──────────────────────────────────────────
    // Form fields
    // ──────────────────────────────────────────
    public ?int    $planId       = null;
    public string  $name         = '';
    public string  $slug         = '';
    public string  $price_zmw    = '';
    public string  $billing_cycle = 'monthly';
    public bool    $is_active    = true;
    public int     $sort_order   = 0;

    /**
     * Features stored as a flat key=>value array for the form.
     * Each row: ['key' => '', 'enabled' => false]
     */
    public array $featureRows = [];

    // ──────────────────────────────────────────
    // Filters / search
    // ──────────────────────────────────────────
    public string $search        = '';
    public string $filterStatus  = '';

    // ──────────────────────────────────────────
    // Validation rules
    // ──────────────────────────────────────────
    protected function rules(): array
    {
        return [
            'name'                    => 'required|string|max:255',
            'slug'                    => 'required|string|max:255|unique:subscription_plans,slug,' . ($this->planId ?? 'NULL'),
            'price_zmw'               => 'required|numeric|min:0',
            'billing_cycle'           => 'required|in:monthly,quarterly,yearly',
            'is_active'               => 'boolean',
            'sort_order'              => 'integer|min:0',
            'featureRows.*.key'       => 'required|string|max:100',
            'featureRows.*.enabled'   => 'boolean',
        ];
    }

    protected $messages = [
        'featureRows.*.key.required' => 'Each feature must have a key name.',
    ];

    // ──────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────

    public function updatedName(string $value): void
    {
        if (! $this->planId) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ──────────────────────────────────────────
    // Create
    // ──────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->createModal = true;
    }

    public function store(): void
    {
        $this->validate();

        SubscriptionPlan::create([
            'name'          => $this->name,
            'slug'          => $this->slug,
            'price_zmw'     => $this->price_zmw,
            'billing_cycle' => $this->billing_cycle,
            'features'      => $this->buildFeaturesArray(),
            'is_active'     => $this->is_active,
            'sort_order'    => $this->sort_order,
        ]);

        $this->createModal = false;
        $this->resetForm();

        $this->notification()->success(
            title: 'Plan Created',
            description: 'The subscription plan has been created successfully.'
        );
    }

    // ──────────────────────────────────────────
    // Edit / Update
    // ──────────────────────────────────────────

    public function openEditModal(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $this->planId        = $plan->id;
        $this->name          = $plan->name;
        $this->slug          = $plan->slug;
        $this->price_zmw     = $plan->price_zmw;
        $this->billing_cycle = $plan->billing_cycle;
        $this->is_active     = $plan->is_active;
        $this->sort_order    = $plan->sort_order;
        $this->featureRows   = collect($plan->features ?? [])
            ->map(fn ($enabled, $key) => ['key' => $key, 'enabled' => (bool) $enabled])
            ->values()
            ->toArray();

        $this->editModal = true;
    }

    public function update(): void
    {
        $this->validate();

        SubscriptionPlan::findOrFail($this->planId)->update([
            'name'          => $this->name,
            'slug'          => $this->slug,
            'price_zmw'     => $this->price_zmw,
            'billing_cycle' => $this->billing_cycle,
            'features'      => $this->buildFeaturesArray(),
            'is_active'     => $this->is_active,
            'sort_order'    => $this->sort_order,
        ]);

        $this->editModal = false;
        $this->resetForm();

        $this->notification()->success(
            title: 'Plan Updated',
            description: 'The subscription plan has been updated successfully.'
        );
    }

    // ──────────────────────────────────────────
    // Delete
    // ──────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->dialog()->confirm([
            'title'       => 'Delete Plan?',
            'description' => 'This action cannot be undone. All related subscriptions and payments will lose their plan reference.',
            'icon'        => 'trash',
            'iconColor'   => 'text-red-500',
            'accept'      => [
                'label'  => 'Yes, Delete',
                'color'  => 'negative',
                'method' => 'delete',
                'params' => $id,
            ],
            'reject' => [
                'label' => 'Cancel',
                'color' => 'secondary',
            ],
        ]);
    }

    public function delete(int $id): void
    {
        SubscriptionPlan::findOrFail($id)->delete();

        $this->notification()->error(
            title: 'Plan Deleted',
            description: 'The subscription plan has been removed.'
        );
    }

    // ──────────────────────────────────────────
    // Toggle active status inline
    // ──────────────────────────────────────────

    public function toggleActive(int $id): void
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        $this->notification()->info(
            title: 'Status Updated',
            description: 'Plan status has been toggled.'
        );
    }

    // ──────────────────────────────────────────
    // Feature row helpers
    // ──────────────────────────────────────────

    public function addFeatureRow(): void
    {
        $this->featureRows[] = ['key' => '', 'enabled' => false];
    }

    public function removeFeatureRow(int $index): void
    {
        array_splice($this->featureRows, $index, 1);
    }

    // ──────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────

    private function buildFeaturesArray(): array
    {
        $features = [];
        foreach ($this->featureRows as $row) {
            if (filled($row['key'])) {
                $features[$row['key']] = (bool) ($row['enabled'] ?? false);
            }
        }
        return $features;
    }

    private function resetForm(): void
    {
        $this->planId        = null;
        $this->name          = '';
        $this->slug          = '';
        $this->price_zmw     = '';
        $this->billing_cycle = 'monthly';
        $this->is_active     = true;
        $this->sort_order    = 0;
        $this->featureRows   = [];
        $this->resetValidation();
    }

    // ──────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────

    public function getPlansProperty()
    {
        $plans = SubscriptionPlan::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%"))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('is_active', (bool) $this->filterStatus))
            ->orderBy('sort_order')
            ->paginate(10);
        return $plans;
    }
};
?>

<div class="min-h-screen bg-gray-50 p-6">

    {{-- ─────────────────────────────────────────── --}}
    {{-- Notifications (WireUI) --}}
    {{-- ─────────────────────────────────────────── --}}
    <x-notifications />
    <x-dialog />

    {{-- ─────────────────────────────────────────── --}}
    {{-- Page Header --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Subscription Plans</h1>
            <p class="mt-1 text-sm text-gray-500">Manage pricing tiers and feature sets for your customers.</p>
        </div>

        <x-button
            icon="plus"
            label="New Plan"
            wire:click="openCreateModal"
            class="w-full sm:w-auto"
        />
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Filters --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <x-input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name or slug…"
                icon="magnifying-glass"
                class="w-full"
            />
        </div>

        <x-select
            wire:model.live="filterStatus"
            placeholder="All Statuses"
            class="sm:w-44"
            :options="[
                ['label' => 'Active',   'value' => '1'],
                ['label' => 'Inactive', 'value' => '0'],
            ]"
            option-label="label"
            option-value="value"
        />
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- Table --}}
    {{-- ─────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">#</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Slug</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Price</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Cycle</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Features</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Order</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->plans as $plan)
                    <tr class="transition hover:bg-gray-50" wire:key="plan-{{ $plan->id }}">
                        <td class="px-4 py-3 text-gray-400">{{ $plan->id }}</td>

                        <td class="px-4 py-3 font-medium text-gray-900">{{ $plan->name }}</td>

                        <td class="px-4 py-3">
                            <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600">
                                {{ $plan->slug }}
                            </span>
                        </td>

                        <td class="px-4 py-3 font-semibold text-emerald-700">
                            {{ $plan->formatted_price }}
                        </td>

                        <td class="px-4 py-3 capitalize text-gray-600">{{ $plan->billing_cycle }}</td>

                        <td class="px-4 py-3">
                            @if ($plan->features)
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($plan->features as $key => $enabled)
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' => $enabled,
                                            'bg-gray-100 text-gray-400 line-through'               => ! $enabled,
                                        ])>
                                            {{ $key }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <button
                                wire:click="toggleActive({{ $plan->id }})"
                                title="Toggle status"
                                @class([
                                    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold transition',
                                    'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' =>  $plan->is_active,
                                    'bg-red-100 text-red-600 hover:bg-red-200'             => ! $plan->is_active,
                                ])
                            >
                                @if ($plan->is_active)
                                    <x-icon name="check-circle" class="h-3.5 w-3.5" /> Active
                                @else
                                    <x-icon name="x-circle" class="h-3.5 w-3.5" /> Inactive
                                @endif
                            </button>
                        </td>

                        <td class="px-4 py-3 text-gray-500">{{ $plan->sort_order }}</td>

                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <x-mini-button
                                    icon="pencil"
                                    color="secondary"
                                    wire:click="openEditModal({{ $plan->id }})"
                                    title="Edit"
                                />
                                <x-mini-button
                                    icon="trash"
                                    color="negative"
                                    wire:click="confirmDelete({{ $plan->id }})"
                                    title="Delete"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                            <x-icon name="inbox" class="mx-auto mb-2 h-10 w-10 text-gray-300" />
                            No subscription plans found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        @if ($this->plans->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $this->plans->links() }}
            </div>
        @endif
    </div>

    {{-- ─────────────────────────────────────────── --}}
    {{-- CREATE MODAL --}}
    {{-- ─────────────────────────────────────────── --}}
    <x-modal wire:model="createModal" max-width="2xl" persistent>
        <x-card title="Create Subscription Plan" class="relative">

            <div class="space-y-4 p-2">
                @include('livewire.partials.subscription-plan-form')
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('createModal', false)" />
                    <x-button label="Create Plan" icon="check" wire:click="store" spinner="store" />
                </div>
            </x-slot>

        </x-card>
    </x-modal>

    {{-- ─────────────────────────────────────────── --}}
    {{-- EDIT MODAL --}}
    {{-- ─────────────────────────────────────────── --}}
    <x-modal wire:model="editModal" max-width="2xl" persistent>
        <x-card title="Edit Subscription Plan" class="relative">

            <div class="space-y-4 p-2">
                @include('livewire.partials.subscription-plan-form')
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-3">
                    <x-button flat label="Cancel" wire:click="$set('editModal', false)" />
                    <x-button label="Save Changes" icon="check" wire:click="update" spinner="update" />
                </div>
            </x-slot>

        </x-card>
    </x-modal>

</div>
