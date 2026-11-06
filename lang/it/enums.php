<?php

declare(strict_types=1);

/*
 * The labels of the enums of fixed choices (Italian), read by
 * App\Enums\Concerns\HasLabel: the enum name in snake case, then the value.
 */
return [
    'agenda_reminder_state' => [
        'open' => 'Promemoria da fare',
        'late' => 'Promemoria in ritardo',
        'done' => 'Promemoria fatto',
    ],
    'area' => [
        'home' => 'Casa',
        'vehicles' => 'Veicoli',
        'insurance' => 'Assicurazioni',
        'banks' => 'Banche e finanziamenti',
        'health' => 'Salute',
        'school' => 'Scuola e attività',
        'work' => 'Lavoro',
        'subscriptions' => 'Abbonamenti',
        'paperwork' => 'Pratiche',
    ],
    'belonging_status' => [
        'in_use' => 'In uso',
        'disposed' => 'Dismesso',
    ],
    'channel' => [
        'app' => 'Solo app',
        'email' => 'Solo email',
        'both' => 'App ed email',
    ],
    'document_status' => [
        'active' => 'Attivo',
        'archived' => 'Archiviato',
        'expired' => 'Scaduto',
    ],
    'due_mode' => [
        'new' => 'Nuova spesa prevista',
        'existing' => 'Spesa prevista già presente',
        'exception' => 'Eccezione su una scadenza',
    ],
    'employment_status' => [
        'active' => 'Attivo',
        'ended' => 'Concluso',
    ],
    'enrollment_status' => [
        'active' => 'Attiva',
        'ended' => 'Conclusa',
    ],
    'enrollment_type' => [
        'school' => 'Scuola',
        'sport' => 'Sport',
        'course' => 'Corso',
        'other' => 'Altro',
    ],
    'exception_type' => [
        'skip' => 'Saltata',
        'amount_override' => 'Importo diverso',
        'date_override' => 'Data diversa',
    ],
    'file_source' => [
        'upload' => 'Caricati qui',
        'mail' => 'Per posta',
        'phone' => 'Dal telefono',
        'scanner' => 'Scansionati',
    ],
    'filing_status' => [
        'draft' => 'Bozza',
        'submitted' => 'Inviata',
        'approved' => 'Accolta',
        'rejected' => 'Respinta',
    ],
    'filing_type' => [
        'tax_return' => '730 / dichiarazione dei redditi',
        'isee' => 'ISEE',
        'benefit' => 'Assegni e bonus',
        'parental_leave' => 'Maternità e congedi',
        'municipal' => 'Pratica comunale',
        'other' => 'Altro',
    ],
    'financial_account_status' => [
        'active' => 'Attivo',
        'closed' => 'Chiuso',
    ],
    'financial_account_type' => [
        'checking_account' => 'Conto corrente',
        'card' => 'Carta',
        'deposit' => 'Deposito',
        'online_wallet' => 'Conto online',
        'other' => 'Altro',
    ],
    'health_event_type' => [
        'visit' => 'Visita',
        'exam' => 'Esame',
        'vaccine' => 'Vaccino',
        'prescription' => 'Ricetta',
        'optical' => 'Ottica',
    ],
    'home_status' => [
        'active' => 'Attiva',
        'archived' => 'Archiviata',
    ],
    'income_type' => [
        'salary' => 'Stipendio',
        'benefit' => 'INPS e bonus',
        'refund' => 'Rimborso',
        'other' => 'Altro',
    ],
    'insurance_policy_status' => [
        'active' => 'Attiva',
        'expired' => 'Scaduta',
        'cancelled' => 'Disdetta',
    ],
    'insurance_policy_type' => [
        'car' => 'Auto',
        'life' => 'Vita',
        'home' => 'Casa',
        'health' => 'Sanitaria',
        'accident' => 'Infortuni',
        'fund' => 'Fondo sanitario',
        'other' => 'Altro',
    ],
    'interval_unit' => [
        'week' => 'Settimana',
        'month' => 'Mese',
        'year' => 'Anno',
    ],
    'loan_status' => [
        'active' => 'Attivo',
        'paid_off' => 'Estinto',
    ],
    'maintenance_target' => [
        'home' => 'Abitazione',
        'belonging' => 'Bene',
        'vehicle' => 'Veicolo',
    ],
    'maintenance_type' => [
        'service' => 'Tagliando',
        'inspection' => 'Revisione',
        'repair' => 'Riparazione',
        'boiler_service' => 'Manutenzione caldaia',
        'other' => 'Altro',
    ],
    'notice_tone' => [
        'info' => 'Informazione',
        'warning' => 'Attenzione',
        'danger' => 'In ritardo',
    ],
    'organization_type' => [
        'bank' => 'Banca o finanziaria',
        'insurance' => 'Assicurazione',
        'utility_provider' => 'Fornitore utenze',
        'employer' => 'Datore di lavoro',
        'public_office' => 'Ente pubblico',
        'school' => 'Scuola o associazione',
        'healthcare' => 'Sanità',
        'shop' => 'Negozio o servizio',
        'other' => 'Altro',
    ],
    'person_role' => [
        'parent' => 'Genitore',
        'child' => 'Figlio/a',
        'other' => 'Altro',
    ],
    'reading_purpose' => [
        'document' => 'Documento',
        'mail_filter' => 'Filtro della posta',
        'mail_notice' => 'Avviso dalla posta',
        'mail_file' => 'Allegato della posta',
        'crop' => 'Ritaglio',
    ],
    'reading_status' => [
        'queued' => 'In coda',
        'reading' => 'Sto leggendo',
        'done' => 'Letto',
        'failed' => 'Non letto',
        'skipped' => 'Non si legge',
        'off' => 'Lettura spenta',
    ],
    'record_kind' => [
        'organization' => 'Ente',
        'vehicle' => 'Veicolo',
        'utility' => 'Utenza',
        'policy' => 'Polizza',
        'belonging' => 'Bene',
        'maintenance' => 'Intervento',
    ],
    'reminder_status' => [
        'open' => 'Aperto',
        'done' => 'Fatto',
    ],
    'role' => [
        'admin' => 'Tutto, come te',
        'member' => 'Vede tutto e carica i documenti',
    ],
    'role_description' => [
        'admin' => 'Anche le impostazioni, gli inviti e l\'abbonamento',
        'member' => 'Paga, registra e carica, ma non cambia le impostazioni',
    ],
    'role_name' => [
        'admin' => 'Amministratore',
        'member' => 'Familiare',
    ],
    'subscription_status' => [
        'active' => 'Attivo',
        'cancelled' => 'Disdetto',
    ],
    'subscription_type' => [
        'streaming' => 'Streaming e intrattenimento',
        'software' => 'Software e licenze',
        'cloud' => 'Cloud e archiviazione',
        'web' => 'Domini, hosting ed email',
        'other' => 'Altro',
    ],
    'utility_status' => [
        'active' => 'Attiva',
        'terminated' => 'Cessata',
    ],
    'utility_type' => [
        'electricity' => 'Luce',
        'gas' => 'Gas',
        'water' => 'Acqua',
        'internet' => 'Internet',
        'phone' => 'Telefonia',
        'waste_tax' => 'TARI',
        'tv_license' => 'Canone RAI',
    ],
    'vehicle_status' => [
        'active' => 'Attivo',
        'sold' => 'Venduto',
    ],
];
