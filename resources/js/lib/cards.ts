/**
 * What the pages of the cards know about each type: its icon. The colours of the areas are in `areas.ts`.
 */
import type { LucideIcon } from '@lucide/vue';
import { create } from '@/routes/cards';
import {
    Briefcase,
    Car,
    ClipboardList,
    CreditCard,
    FileText,
    GraduationCap,
    HeartPulse,
    House,
    Landmark,
    Package,
    Repeat,
    Shield,
    Wrench,
    Zap,
} from '@lucide/vue';

/** The icon of each card type, as on the boards. */
const icons: Record<string, LucideIcon> = {
    home: House,
    utility: Zap,
    belonging: Package,
    maintenance_record: Wrench,
    vehicle: Car,
    insurance_policy: Shield,
    financial_account: CreditCard,
    loan: Landmark,
    subscription: Repeat,
    filing: ClipboardList,
    employment: Briefcase,
    health_event: HeartPulse,
    enrollment: GraduationCap,
};

/**
 * The icon of a card type.
 *
 * @param type - the type of the card, for example `vehicle`
 * @returns the Lucide component, or the sheet for an unknown type
 */
export function cardIcon(type: string): LucideIcon {
    return icons[type] ?? FileText;
}

/**
 * Where *New card* goes for an area: straight to the form when the area has a
 * single card type, else to the choice of the type among those of the area.
 *
 * @param types - the card types the person can create
 * @param area - the area, for example `vehicles`
 * @returns the address of the page
 */
export function newCardUrl(
    types: App.Data.CardTypeData[],
    area: string,
): string {
    const ofArea = types.filter((type) => type.area === area);
    const [only] = ofArea;

    return only !== undefined && ofArea.length === 1
        ? create({ query: { type: only.type } }).url
        : create({ query: { area } }).url;
}
