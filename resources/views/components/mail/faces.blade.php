@props(['initials'])
{{-- The circles of the people of the family with their initials, in the colours of FmFaces, each one slightly over the one before with a white ring (mail programs that ignore negative margins set them side by side). --}}
<div class="faces">@foreach ($initials as $i => $letters)<span class="face" style="background-color: {{ App\Support\MailIcons::color(App\Support\MailIcons::FACE_COLORS[$i % count(App\Support\MailIcons::FACE_COLORS)]) }};{{ $i > 0 ? ' margin-left: -10px;' : '' }}">{{ $letters }}</span>@endforeach</div>
