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
    home: cn('bg-home/15 text-home'),
    vehicles: cn('bg-vehicles/15 text-vehicles'),
    insurance: cn('bg-insurance/15 text-insurance'),
    banks: cn('bg-banks/15 text-banks'),
    health: cn('bg-health/15 text-health'),
    school: cn('bg-school/15 text-school'),
    work: cn('bg-work/15 text-work'),
    subscriptions: cn('bg-subscriptions/15 text-subscriptions'),
    paperwork: cn('bg-paperwork/15 text-paperwork'),
    general: cn('bg-general/15 text-general'),
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
