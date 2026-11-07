import type { LucideIcon } from '@lucide/vue';
import {
    Briefcase,
    Car,
    ClipboardList,
    FileText,
    GraduationCap,
    HeartPulse,
    House,
    Landmark,
    Repeat,
    Shield,
} from '@lucide/vue';
import type { CSSProperties } from 'vue';
import { cn } from './utils';

/**
 * The areas of family life and their colours, as on the canvas.
 *
 * The classes are written in full so that Tailwind finds them in the code: a
 * class built on the fly (`bg-${area}`) would not end up in the CSS.
 */
export type Area =
    | 'home'
    | 'vehicles'
    | 'insurance'
    | 'banks'
    | 'health'
    | 'school'
    | 'work'
    | 'subscriptions'
    | 'paperwork'
    | 'general';

/** The full colour of each area, for dots and icons. */
export const areaFill: Record<Area, string> = {
    home: cn('bg-home'),
    vehicles: cn('bg-vehicles'),
    insurance: cn('bg-insurance'),
    banks: cn('bg-banks'),
    health: cn('bg-health'),
    school: cn('bg-school'),
    work: cn('bg-work'),
    subscriptions: cn('bg-subscriptions'),
    paperwork: cn('bg-paperwork'),
    general: cn('bg-general'),
};

/** The soft background and the icon colour of each area, for the icon tiles. */
export const areaTile: Record<Area, string> = {
    home: cn('bg-home-soft text-home-text'),
    vehicles: cn('bg-vehicles-soft text-vehicles-text'),
    insurance: cn('bg-insurance-soft text-insurance-text'),
    banks: cn('bg-banks-soft text-banks-text'),
    health: cn('bg-health-soft text-health-text'),
    school: cn('bg-school-soft text-school-text'),
    work: cn('bg-work-soft text-work-text'),
    subscriptions: cn('bg-subscriptions-soft text-subscriptions-text'),
    paperwork: cn('bg-paperwork-soft text-paperwork-text'),
    general: cn('bg-general-soft text-general-text'),
};

/** The dark background of the top of a card page. */
export const areaHeader: Record<Area, string> = {
    home: cn('bg-home-dark'),
    vehicles: cn('bg-vehicles-dark'),
    insurance: cn('bg-insurance-dark'),
    banks: cn('bg-banks-dark'),
    health: cn('bg-health-dark'),
    school: cn('bg-school-dark'),
    work: cn('bg-work-dark'),
    subscriptions: cn('bg-subscriptions-dark'),
    paperwork: cn('bg-paperwork-dark'),
    general: cn('bg-general-dark'),
};

/**
 * The area of a colour saved in the database, if it is one of the areas.
 *
 * @param color - the value of `color`, for example `home`
 * @returns the area, or undefined
 */
export function areaOf(color: string | null): Area | undefined {
    return color !== null && color in areaFill ? (color as Area) : undefined;
}

/**
 * The colour of its own of a category of expenses (`#0797B9`), or null for an
 * area or no colour, which the classes of the areas colour.
 *
 * @param color - the value of `color`
 * @returns the hex colour, or null
 */
function ownColor(color: string | null): string | null {
    return color?.startsWith('#') ? color : null;
}

/**
 * The style of an icon tile in a colour of its own: the icon in the colour,
 * on a soft background of the same colour.
 *
 * @param color - the value of `color`, for example `#0797B9`
 * @returns the inline style, empty when the colour is an area or missing
 */
export function colorTile(color: string | null): CSSProperties {
    const own = ownColor(color);

    return own === null ? {} : { color: own, backgroundColor: `${own}1f` };
}

/**
 * The style of a dot in a colour of its own.
 *
 * @param color - the value of `color`, for example `#0797B9`
 * @returns the inline style, empty when the colour is an area or missing
 */
export function colorFill(color: string | null): CSSProperties {
    const own = ownColor(color);

    return own === null ? {} : { backgroundColor: own };
}

/** The icon of each area, as on the board *Schede*. */
const areaIcons: Record<Area, LucideIcon> = {
    home: House,
    vehicles: Car,
    insurance: Shield,
    banks: Landmark,
    health: HeartPulse,
    school: GraduationCap,
    work: Briefcase,
    subscriptions: Repeat,
    paperwork: ClipboardList,
    general: FileText,
};

/**
 * The icon of an area.
 *
 * @param area - the area, for example `vehicles`
 * @returns the Lucide component, the one of `general` for an unknown area
 */
export function areaIcon(area: string): LucideIcon {
    return areaIcons[areaOf(area) ?? 'general'];
}
