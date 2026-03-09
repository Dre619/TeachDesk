<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-input
        label="Plan Name"
        placeholder="e.g. Basic"
        wire:model.live.debounce.300ms="name"
        hint="Auto-generates the slug"
    />

    <x-input
        label="Slug"
        placeholder="e.g. basic"
        wire:model="slug"
        hint="URL-friendly identifier — must be unique"
    />
</div>

{{-- Row 2: Price + Billing Cycle --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-input
        label="Price (ZMW)"
        type="number"
        placeholder="0.00"
        wire:model="price_zmw"
        prefix="K"
    />

    <x-select
        label="Billing Cycle"
        wire:model="billing_cycle"
        :options="[
            ['label' => 'Monthly',   'value' => 'monthly'],
            ['label' => 'Quarterly', 'value' => 'quarterly'],
            ['label' => 'Yearly',    'value' => 'yearly'],
        ]"
        option-label="label"
        option-value="value"
    />
</div>

{{-- Row 3: Sort Order + Active toggle --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-input
        label="Sort Order"
        type="number"
        min="0"
        wire:model="sort_order"
        hint="Lower numbers appear first"
    />

    <div class="flex flex-col justify-end pb-1">
        <x-toggle
            label="Active (visible to customers)"
            wire:model="is_active"
        />
    </div>
</div>

{{-- Features builder --}}
<div>
    <div class="mb-2 flex items-center justify-between">
        <label class="block text-sm font-medium text-gray-700">Features</label>
        <x-mini-button
            icon="plus"
            color="secondary"
            wire:click="addFeatureRow"
            title="Add feature"
        />
    </div>

    @if (count($featureRows) === 0)
        <p class="rounded-lg border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-gray-400">
            No features added yet. Click <strong>+</strong> to add one.
        </p>
    @endif

    <div class="space-y-2">
        @foreach ($featureRows as $i => $row)
            <div
                class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
                wire:key="feature-row-{{ $i }}"
            >
                <x-input
                    placeholder="Feature key, e.g. report_cards"
                    wire:model="featureRows.{{ $i }}.key"
                    class="flex-1"
                    without-padding
                />

                <x-toggle
                    wire:model="featureRows.{{ $i }}.enabled"
                    title="Enabled?"
                />

                <x-mini-button
                    icon="trash"
                    color="negative"
                    flat
                    wire:click="removeFeatureRow({{ $i }})"
                    title="Remove"
                />
            </div>
        @endforeach
    </div>

    @error('featureRows.*.key')
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>
