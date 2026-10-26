<x-mail::message>
# Ciao!

{{ $chi }} ti ha invitato su Family Manager, dove la famiglia tiene le scadenze, le bollette e i documenti di tutti.

<x-mail::button :url="$link">
Entra nella famiglia
</x-mail::button>

Il link vale fino al {{ $scade }}. Se non aspettavi questo invito, puoi ignorare l'email.

Family Manager
</x-mail::message>
