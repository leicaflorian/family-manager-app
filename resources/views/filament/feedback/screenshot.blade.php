{{-- The screenshot the app took of the page when the person reported the problem --}}
<a href="{{ route('filament.admin.feedback-file', [$getRecord(), 'screenshot']) }}" target="_blank" rel="noopener">
    <img src="{{ route('filament.admin.feedback-file', [$getRecord(), 'screenshot']) }}" alt="{{ __('Screenshot') }}" class="max-h-[32rem] rounded-lg border border-gray-200">
</a>
