{{-- The frame of every mail: the page background, the name of the app, the white card and the footer (board "Le email" of the canvas). --}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
{!! $head ?? '' !!}
</head>
<body>
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#f4f6fa">
<tr>
<td align="center">
<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; max-width: 600px;">
{!! $header ?? '' !!}
<tr>
<td style="padding: 0 16px;">
<table class="card" width="100%" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#ffffff">
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>
{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
