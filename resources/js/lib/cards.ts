/**
 * What the pages of the cards know about each type: its icon and the name of
 * its area. The colours of the areas are in `areas.ts`.
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
import { trans } from 'laravel-vue-i18n';

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
 * The name of an area, in the language of the page.
 *
 * @param area - the area of the card, for example `vehicles`
 * @returns the name, or the area itself if it is not one of the known
 */
export function areaLabel(area: string): string {
    const labels: Record<string, string> = {
        home: trans('Home'),
        vehicles: trans('Vehicles'),
        insurance: trans('Insurance'),
        banks: trans('Banks and loans'),
        health: trans('Health'),
        school: trans('School and activities'),
        work: trans('Work'),
        subscriptions: trans('Subscriptions'),
        paperwork: trans('Paperwork'),
        general: trans('General'),
    };

    return labels[area] ?? area;
}
