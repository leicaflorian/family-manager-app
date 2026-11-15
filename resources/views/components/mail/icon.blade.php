@props(['name', 'shape' => 'round'])
{{-- The icon of a mail on its pale colour: round above the title, a rounded square in a row of notices. --}}
<table class="icon" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="icon-{{ $shape }}" bgcolor="{{ App\Support\MailIcons::tile($name) }}" width="{{ $shape === 'round' ? 48 : 40 }}" height="{{ $shape === 'round' ? 48 : 40 }}" align="center" valign="middle"><img src="{{ App\Support\MailIcons::src($name) }}" width="{{ $shape === 'round' ? 24 : 20 }}" height="{{ $shape === 'round' ? 24 : 20 }}" alt=""></td></tr></table>
