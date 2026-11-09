<x-filament-panels::page>
    <x-filament::section :heading="__('Mailgun')">
        <ul class="space-y-1">
            <li>{{ __('Domain') }}: {{ $domain ?? __('Not set') }}</li>
            <li>{{ $hasSigningKey ? __('Signing key: set') : __('Signing key: not set') }}</li>
            <li>{{ $hasApiKey ? __('API key: set') : __('API key: not set') }}</li>
        </ul>
    </x-filament::section>

    <div class="flex flex-wrap items-center gap-4">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="family" :aria-label="__('Family')">
                <option value="">{{ __('Every family') }}</option>
                @foreach ($families as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="outcome" :aria-label="__('Outcome')">
                <option value="">{{ __('Every outcome') }}</option>
                @foreach ($outcomes as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <x-filament::input type="date" wire:model.live="since" :aria-label="__('From day')" />
        </x-filament::input.wrapper>
    </div>

    @if ($calls->isEmpty())
        <p>{{ __('No calls.') }}</p>
    @else
        <x-admin-table>
            <x-slot name="head">
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Date') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Outcome') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Status') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Family') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Files') }}</th>
                <th style="padding: 0.5rem 0.75rem; font-weight: 600">{{ __('Saved') }}</th>
            </x-slot>
            @foreach ($calls as $call)
                <tr wire:key="call-{{ $call->id }}" style="border-bottom: 1px solid rgb(128 128 128 / 0.15)">
                    <td style="padding: 0.5rem 0.75rem">{{ $call->created_at?->setTimezone('Europe/Rome')->format('d/m/Y H:i') }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $call->outcome->label() }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $call->http_status }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $call->family_id === null ? '-' : ($families[$call->family_id] ?? '#'.$call->family_id) }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $call->attachments }}</td>
                    <td style="padding: 0.5rem 0.75rem">{{ $call->saved }}</td>
                </tr>
            @endforeach
        </x-admin-table>
    @endif
</x-filament-panels::page>
