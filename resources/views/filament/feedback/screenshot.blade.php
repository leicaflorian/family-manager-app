{{-- The screenshot the app took of the page when the person reported the problem --}}
<a href="{{ route('owner.feedback.file', [$getRecord(), 'screenshot']) }}" target="_blank" rel="noopener">
    <img src="{{ route('owner.feedback.file', [$getRecord(), 'screenshot']) }}" alt="{{ __('Screenshot') }}" class="max-h-[32rem] rounded-lg border border-gray-200">
</a>
