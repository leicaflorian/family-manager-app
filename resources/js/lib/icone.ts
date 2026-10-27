/**
 * Le icone che una categoria dei documenti può avere: nel database c'è il nome
 * di Lucide (`house`), qui il componente. Sono quelle della tavola *Categorie
 * dei documenti*; un nome che manca dà la cartella.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Briefcase,
    Car,
    ClipboardList,
    Folder,
    GraduationCap,
    HeartPulse,
    House,
    Landmark,
    Receipt,
    Shield,
    ShoppingBag,
} from '@lucide/vue';

const icone: Record<string, LucideIcon> = {
    briefcase: Briefcase,
    car: Car,
    'clipboard-list': ClipboardList,
    'graduation-cap': GraduationCap,
    'heart-pulse': HeartPulse,
    house: House,
    landmark: Landmark,
    receipt: Receipt,
    shield: Shield,
    'shopping-bag': ShoppingBag,
};

/**
 * L'icona di una categoria.
 *
 * @param nome - il nome salvato nella categoria (`icon`)
 * @returns il componente di Lucide, o la cartella
 */
export function iconaCategoria(nome: string | null): LucideIcon {
    return (nome !== null && icone[nome]) || Folder;
}
