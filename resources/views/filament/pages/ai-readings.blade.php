<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="previousMonth" :label="__('Previous month')" />
            <span class="min-w-32 text-center font-medium">{{ $this->monthLabel() }}</span>
            <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="nextMonth" :label="__('Next month')" />
        </div>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="provider" :aria-label="__('Service')">
                <option value="">{{ __('Every service') }}</option>
                @foreach ($options['providers'] as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="model" :aria-label="__('Model')">
                <option value="">{{ __('Every model') }}</option>
                @foreach ($options['models'] as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    @if ($rows->isEmpty())
        <p>{{ __('No readings this month.') }}</p>
    @else
        <x-admin-table>
            <x-slot name="head">
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Family') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Readings') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Input tokens') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Output tokens') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Cost') }}</th>
            </x-slot>
            @foreach ($rows->push($total) as $row)
                <tr wire:key="reading-{{ $row->familyId ?? $row->label }}" style="border-bottom: 1px solid rgb(128 128 128 / 0.15)">
                    <td style="padding: 0.5rem 0.75rem; font-weight: 600">{{ $row->label }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $row->readings }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $row->inputTokens }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $row->outputTokens }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $row->costText() }}{{ $row->unpriced > 0 ? ' ('.__(':count without a price', ['count' => $row->unpriced]).')' : '' }}</td>
                </tr>
            @endforeach
        </x-admin-table>
    @endif
</x-filament-panels::page>
