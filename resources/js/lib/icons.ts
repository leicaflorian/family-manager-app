/**
 * The icons a document category can have: the database holds the Lucide name
 * (`house`), here is the component. They are the ones of the *Document
 * categories* board; a missing name gives the folder.
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

const icons: Record<string, LucideIcon> = {
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
 * The icon of a category.
 *
 * @param name - the name saved in the category (`icon`)
 * @returns the Lucide component, or the folder
 */
export function categoryIcon(name: string | null): LucideIcon {
    return (name !== null && icons[name]) || Folder;
}
