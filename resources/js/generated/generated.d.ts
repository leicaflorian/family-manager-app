declare namespace App {
namespace Data {
export type AccountData = {
readonly first_name: string,
readonly last_name: string,
readonly email: string,
readonly role: string,
readonly is_admin: boolean,
readonly initials: string,
readonly photo: string | null,
readonly is_last_admin: boolean,
readonly family: string,
};
export type CategoryData = {
readonly id: number,
readonly name: string,
readonly parent_id: number | null,
readonly icon: string | null,
readonly color: string | null,
readonly documents_count: number,
readonly children: App.Data.CategoryData[],
};
export type FamilyData = {
readonly name: string,
};
export type InvitationData = {
readonly first_name: string,
readonly family: string,
readonly inviter: string,
readonly email: string,
readonly expires_on: string,
readonly people: App.Data.PersonSummaryData[],
};
export type OptionData = {
readonly value: string,
readonly label: string,
readonly help: string | null,
};
export type PersonSummaryData = {
readonly id: number,
readonly first_name: string,
readonly last_name: string,
readonly initials: string,
};
export type SentInvitationData = {
readonly email: string,
readonly link: string,
readonly expires_on: string,
};
}
namespace Enums {
export type BelongingStatus = 'in_use' | 'disposed';
export type DocumentStatus = 'active' | 'archived' | 'expired';
export type EmploymentStatus = 'active' | 'ended';
export type EnrollmentStatus = 'active' | 'ended';
export type EnrollmentType = 'school' | 'sport' | 'course' | 'other';
export type ExceptionType = 'skip' | 'amount_override' | 'date_override';
export type FilingStatus = 'draft' | 'submitted' | 'approved' | 'rejected';
export type FilingType = 'tax_return' | 'isee' | 'benefit' | 'parental_leave' | 'municipal' | 'other';
export type FinancialAccountStatus = 'active' | 'closed';
export type FinancialAccountType = 'checking_account' | 'card' | 'deposit' | 'online_wallet' | 'other';
export type HealthEventType = 'visit' | 'exam' | 'vaccine' | 'prescription' | 'optical';
export type HomeStatus = 'active' | 'archived';
export type IncomeType = 'salary' | 'benefit' | 'refund' | 'other';
export type InsurancePolicyStatus = 'active' | 'expired' | 'cancelled';
export type InsurancePolicyType = 'car' | 'life' | 'home' | 'health' | 'accident' | 'fund' | 'other';
export type IntervalUnit = 'week' | 'month' | 'year';
export type LoanStatus = 'active' | 'paid_off';
export type MaintenanceType = 'service' | 'inspection' | 'repair' | 'boiler_service' | 'other';
export type OrganizationType = 'bank' | 'insurance' | 'utility_provider' | 'employer' | 'public_office' | 'school' | 'healthcare' | 'shop' | 'other';
export type PersonRole = 'parent' | 'child' | 'other';
export type ReminderStatus = 'open' | 'done';
export type Role = 'admin' | 'member';
export type SubscriptionStatus = 'active' | 'cancelled';
export type SubscriptionType = 'streaming' | 'software' | 'cloud' | 'web' | 'other';
export type UtilityStatus = 'active' | 'terminated';
export type UtilityType = 'electricity' | 'gas' | 'water' | 'internet' | 'phone' | 'waste_tax' | 'tv_license';
export type VehicleStatus = 'active' | 'sold';
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}
