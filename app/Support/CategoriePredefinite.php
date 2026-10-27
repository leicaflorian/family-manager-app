<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Le categorie con cui parte una famiglia nuova, dei documenti e delle spese.
 *
 * Ogni albero è `nome della principale => [nomi delle sottocategorie]`, nell'ordine
 * in cui si mostrano. La famiglia poi le cambia come vuole dalle impostazioni.
 */
final class CategoriePredefinite
{
    /**
     * Le categorie dei documenti, come nella nota *Categorie* del vault.
     *
     * @return array<string, list<string>>
     */
    public static function documenti(): array
    {
        return [
            'Anagrafica e pratiche' => [],
            'Casa e utenze' => ['Luce', 'Gas', 'Acqua', 'Internet', 'Telefonia', 'TARI', 'Canone RAI'],
            'Veicoli' => [],
            'Acquisti, garanzie e abbonamenti' => ['Arredamento', 'Elettronica ed elettrodomestici', 'Garanzie e scontrini', 'Abbonamenti'],
            'Assicurazioni e fondi' => [],
            'Banche e finanziamenti' => [],
            'Fisco e previdenza' => ['730', 'ISEE', 'Assegni e bonus', 'Maternità e congedi'],
            'Lavoro' => ['Buste paga', 'CU', 'Partita IVA'],
            'Salute' => ['Visite ed esami', 'Ricette', 'Vaccinazioni', 'Ottica', 'Fatture mediche'],
            'Scuola e attività' => [],
        ];
    }

    /**
     * L'icona (un nome di Lucide) e il colore (un'area) delle categorie principali
     * dei documenti, come nella tavola *Categorie dei documenti*. Le sottocategorie
     * prendono il colore della principale.
     *
     * @return array<string, array{icon: string, color: string}>
     */
    public static function aspettoDocumenti(): array
    {
        return [
            'Anagrafica e pratiche' => ['icon' => 'clipboard-list', 'color' => 'pratiche'],
            'Casa e utenze' => ['icon' => 'house', 'color' => 'casa'],
            'Veicoli' => ['icon' => 'car', 'color' => 'veicoli'],
            'Acquisti, garanzie e abbonamenti' => ['icon' => 'shopping-bag', 'color' => 'abbonamenti'],
            'Assicurazioni e fondi' => ['icon' => 'shield', 'color' => 'assicurazioni'],
            'Banche e finanziamenti' => ['icon' => 'landmark', 'color' => 'banche'],
            'Fisco e previdenza' => ['icon' => 'receipt', 'color' => 'trasversali'],
            'Lavoro' => ['icon' => 'briefcase', 'color' => 'lavoro'],
            'Salute' => ['icon' => 'heart-pulse', 'color' => 'salute'],
            'Scuola e attività' => ['icon' => 'graduation-cap', 'color' => 'scuola'],
        ];
    }

    /**
     * Le categorie delle spese, quelle di Family Manager su Directus.
     *
     * @return array<string, list<string>>
     */
    public static function spese(): array
    {
        return [
            'Scuola e asilo' => [],
            'Casa e affitto' => [],
            'Donazioni' => [],
            'Bollette e utenze' => ['Luce', 'Gas', 'Acqua'],
            'Svago' => [],
            'Lavoro' => [],
            'Auto' => [],
            'Risparmi' => [],
            'Polizze e assicurazioni' => [],
            'Salute' => [],
            'Spesa' => [],
            'Prestiti e finanziamenti' => [],
        ];
    }
}
