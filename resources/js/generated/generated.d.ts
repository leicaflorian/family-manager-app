declare namespace App {
namespace Cards {
export type FieldKind = 'text' | 'textarea' | 'number' | 'currency' | 'date' | 'toggle' | 'choice' | 'dropdown' | 'person' | 'organization' | 'card';
}
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
export type AreaData = {
readonly area: string,
readonly label: string,
readonly count: number,
readonly overdue: number,
readonly cards: App.Data.CardData[],
};
export type CardData = {
readonly type: string,
readonly id: number,
readonly type_label: string,
readonly label: string,
readonly area: string,
readonly area_label: string,
readonly subtitle: string,
readonly next_label: string | null,
readonly next_date: string | null,
readonly overdue: boolean,
readonly closed: boolean,
};
export type CardDateData = {
readonly label: string,
readonly date: string,
readonly expires: boolean,
};
export type CardDetailData = {
readonly card: App.Data.CardData,
readonly heading: string,
readonly dates: App.Data.CardDateData[],
readonly notices: string[],
readonly values: App.Data.CardValueData[],
readonly related: App.Data.CardData[],
readonly documents: App.Data.DocumentData[],
readonly can_edit: boolean,
readonly can_delete: boolean,
};
export type CardFieldData = {
readonly column: string,
readonly label: string,
readonly kind: string,
readonly required: boolean,
readonly options: App.Data.OptionData[],
readonly show_when: Record<string, string[]>,
};
export type CardTypeData = {
readonly type: string,
readonly label: string,
readonly description: string,
readonly area: string,
readonly area_label: string,
};
export type CardValueData = {
readonly label: string,
readonly text: string,
readonly href: string | null,
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
export type DocumentData = {
readonly id: number,
readonly title: string,
readonly category: string | null,
readonly person: string | null,
readonly organization: string | null,
readonly date: string | null,
readonly expiry_date: string | null,
readonly status: string,
readonly mime_type: string,
};
export type DocumentDetailData = {
readonly id: number,
readonly title: string,
readonly category_id: number | null,
readonly category: string | null,
readonly person_id: number | null,
readonly person: string | null,
readonly person_initials: string | null,
readonly person_photo: string | null,
readonly organization_id: number | null,
readonly organization: string | null,
readonly date: string | null,
readonly expiry_date: string | null,
readonly status: string,
readonly status_label: string,
readonly tags: string[],
readonly notes: string | null,
readonly file: App.Data.FileData,
readonly links: App.Data.DocumentLinkData[],
readonly can_edit: boolean,
readonly can_delete: boolean,
};
export type DocumentLinkData = {
readonly type: string,
readonly id: number,
readonly type_label: string,
readonly label: string,
readonly href: string | null,
};
export type FamilyData = {
readonly name: string,
};
export type FamilySummaryData = {
readonly name: string,
readonly people: number,
readonly organizations: number,
};
export type FileData = {
readonly name: string,
readonly mime_type: string,
readonly size: number,
readonly url: string,
readonly download_url: string,
readonly preview_url: string | null,
};
export type InvitationData = {
readonly first_name: string,
readonly family: string,
readonly inviter: string,
readonly email: string,
readonly expires_on: string,
readonly people: App.Data.PersonSummaryData[],
};
export type LinkGroupData = {
readonly type: string,
readonly label: string,
readonly options: App.Data.OptionData[],
};
export type OptionData = {
readonly value: string,
readonly label: string,
readonly help: string | null,
};
export type OrganizationData = {
readonly id: number,
readonly name: string,
readonly types: string[],
readonly type_labels: string[],
readonly logo: string | null,
readonly records: string[],
};
export type OrganizationDetailData = {
readonly id: number,
readonly name: string,
readonly types: string[],
readonly type_labels: string[],
readonly logo: string | null,
readonly email: string | null,
readonly phone: string | null,
readonly website: string | null,
readonly notes: string | null,
readonly can_edit: boolean,
readonly can_delete: boolean,
};
export type PendingFileData = {
readonly id: number,
readonly name: string,
readonly mime_type: string,
readonly size: number,
readonly url: string,
readonly title: string,
readonly preview_url: string | null,
};
export type PersonData = {
readonly id: number,
readonly first_name: string,
readonly last_name: string,
readonly initials: string,
readonly photo: string | null,
readonly role_label: string,
readonly age: number | null,
readonly account_label: string | null,
readonly is_me: boolean,
readonly documents_count: number,
readonly records_count: number,
};
export type PersonDetailData = {
readonly id: number,
readonly first_name: string,
readonly last_name: string,
readonly initials: string,
readonly photo: string | null,
readonly role: string,
readonly role_label: string,
readonly birth_date: string | null,
readonly age: number | null,
readonly tax_code: string | null,
readonly notes: string | null,
readonly account_label: string | null,
readonly is_me: boolean,
readonly can_edit: boolean,
readonly can_delete: boolean,
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
export type Area = 'home' | 'vehicles' | 'insurance' | 'banks' | 'health' | 'school' | 'work' | 'subscriptions' | 'paperwork';
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
export type MaintenanceTarget = 'home' | 'belonging' | 'vehicle';
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
