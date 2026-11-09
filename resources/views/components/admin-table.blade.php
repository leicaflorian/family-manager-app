{{-- A plain table for the owner panel pages: Filament 5 has no table component outside its own table builder. --}}
<x-filament::section>
    <div style="overflow-x: auto">
        <table style="width: 100%; border-collapse: collapse; text-align: start; font-size: 0.875rem">
            <thead>
                <tr style="border-bottom: 1px solid rgb(128 128 128 / 0.3)">
                    {{ $head }}
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
</x-filament::section>
