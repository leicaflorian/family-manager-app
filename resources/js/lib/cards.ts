/**
 * What the pages of the cards know about each type: its icon. The colours of the areas are in `areas.ts`.
 */
import type { LucideIcon } from '@lucide/vue';
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
