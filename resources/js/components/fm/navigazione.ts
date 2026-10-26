/**
 * Le voci di navigazione dell'app, le stesse per la barra in basso del telefono
 * e per la barra laterale del computer.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    FileText,
    LayoutDashboard,
    Settings,
    Shapes,
    Users,
} from '@lucide/vue';
import {
    aree,
    carica,
    dashboard,
    famiglia,
    impostazioni,
    logout,
} from '@/routes';
import { index as documenti } from '@/routes/documenti';

/** Le sezioni dell'app, per sapere quale segnare. */
export type Sezione =
    | 'home'
    | 'aree'
    | 'documenti'
    | 'famiglia'
    | 'impostazioni';

/** Una voce della navigazione. */
export type VoceNavigazione = {
    /** La sezione a cui porta. */
    sezione: Sezione;
    /** Il testo della voce. */
    etichetta: string;
    /** L'indirizzo. */
    href: string;
    /** L'icona di Lucide. */
    icona: LucideIcon;
};

/** Tutte le voci, con gli indirizzi di Carica e di Esci. */
export type VociDellaBarra = {
    /** Home, Aree, Documenti e Famiglia, in quest'ordine. */
    sezioni: VoceNavigazione[];
    /** Le impostazioni, nel menu dell'account o in Altro. */
    impostazioni: VoceNavigazione;
    /** L'indirizzo di Carica. */
    carica: string;
    /** L'indirizzo per uscire (si chiama con POST). */
    esci: string;
};

/**
 * Le voci di navigazione con i loro indirizzi.
 *
 * @returns le voci della barra in basso e della barra laterale
 */
export function vociDellaBarra(): VociDellaBarra {
    return {
        sezioni: [
            {
                sezione: 'home',
                etichetta: 'Home',
                href: dashboard().url,
                icona: LayoutDashboard,
            },
            {
                sezione: 'aree',
                etichetta: 'Aree',
                href: aree().url,
                icona: Shapes,
            },
            {
                sezione: 'documenti',
                etichetta: 'Documenti',
                href: documenti().url,
                icona: FileText,
            },
            {
                sezione: 'famiglia',
                etichetta: 'Famiglia',
                href: famiglia().url,
                icona: Users,
            },
        ],
        impostazioni: {
            sezione: 'impostazioni',
            etichetta: 'Impostazioni',
            href: impostazioni().url,
            icona: Settings,
        },
        carica: carica().url,
        esci: logout().url,
    };
}

/**
 * La sezione di una pagina, dal suo indirizzo.
 *
 * @param percorso - il percorso della pagina, per esempio `/documenti/12`
 * @param voci - le voci della navigazione
 * @returns la sezione della voce che apre quel percorso, o `home`
 */
export function sezioneDi(percorso: string, voci: VociDellaBarra): Sezione {
    const tutte = [...voci.sezioni, voci.impostazioni];
    const trovata = tutte.find(
        (v) => percorso === v.href || percorso.startsWith(`${v.href}/`),
    );

    return trovata?.sezione ?? 'home';
}
