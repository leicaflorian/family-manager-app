/**
 * The icons a document category can have: the database holds the Lucide name
 * (`house`), here is the component. They are the ones of the *Document
 * categories* board; a missing name gives the folder.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Briefcase,
    Car,
    CircleDollarSign,
    Clapperboard,
    ClipboardList,
    Droplet,
    Dumbbell,
    Flame,
    Folder,
    GraduationCap,
    HandHeart,
    HeartPulse,
    House,
    Landmark,
    PiggyBank,
    Receipt,
    Shield,
    Shirt,
    ShoppingBag,
    ShoppingCart,
    Zap,
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

/** The icons of the expense categories: the main ones and the subcategories the app gives a family. */
const expenseIcons: Record<string, LucideIcon> = {
    briefcase: Briefcase,
    car: Car,
    clapperboard: Clapperboard,
    droplet: Droplet,
    dumbbell: Dumbbell,
    flame: Flame,
    'graduation-cap': GraduationCap,
    'hand-heart': HandHeart,
    'heart-pulse': HeartPulse,
    house: House,
    landmark: Landmark,
    'piggy-bank': PiggyBank,
    shield: Shield,
    shirt: Shirt,
    'shopping-cart': ShoppingCart,
    zap: Zap,
};

/**
 * The icon of an expense category.
 *
 * @param name - the name saved in the category (`icon`)
 * @returns the Lucide component, or the coin
 */
export function expenseIcon(name: string | null): LucideIcon {
    return (name !== null && expenseIcons[name]) || CircleDollarSign;
}
