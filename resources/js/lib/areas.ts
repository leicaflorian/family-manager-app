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
    home: 'bg-home',
    vehicles: 'bg-vehicles',
    insurance: 'bg-insurance',
    banks: 'bg-banks',
    health: 'bg-health',
    school: 'bg-school',
    work: 'bg-work',
    subscriptions: 'bg-subscriptions',
    paperwork: 'bg-paperwork',
    general: 'bg-general',
};

/** The soft background and the icon colour of each area, for the icon tiles. */
export const areaTile: Record<Area, string> = {
    home: 'bg-home/15 text-home',
    vehicles: 'bg-vehicles/15 text-vehicles',
    insurance: 'bg-insurance/15 text-insurance',
    banks: 'bg-banks/15 text-banks',
    health: 'bg-health/15 text-health',
    school: 'bg-school/15 text-school',
    work: 'bg-work/15 text-work',
    subscriptions: 'bg-subscriptions/15 text-subscriptions',
    paperwork: 'bg-paperwork/15 text-paperwork',
    general: 'bg-general/15 text-general',
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
