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
export type AgendaData = {
readonly entries: App.Data.AgendaEntryData[],
readonly months: number,
readonly from: string,
readonly to: string,
readonly people: App.Data.PersonData[],
readonly filters: App.Data.AgendaFiltersData,
readonly areas: App.Data.OptionData[],
};
export type AgendaEntryData = {
readonly key: string,
readonly kind: string,
readonly date: string,
readonly title: string,
readonly subtitle: string,
readonly area: string,
readonly person_id: number | null,
readonly person: string | null,
readonly amount: number | null,
readonly estimated: boolean,
readonly reminder_done: boolean,
readonly href: string,
};
export type AgendaFiltersData = {
readonly months: number,
readonly person_id: number | null,
readonly hidden_areas: string[],
};
export type AreaCountData = {
readonly area: string,
readonly label: string,
readonly count: number,
};
export type AreaData = {
readonly area: string,
readonly label: string,
readonly count: number,
readonly overdue: number,
readonly cards: App.Data.CardData[],
};
export type CardAddData = {
readonly type: string,
readonly label: string,
readonly href: string,
};
export type CardBudgetData = {
readonly kind: string,
readonly next: App.Data.DeadlineData | null,
readonly next_label: string,
readonly installment_number: number | null,
readonly installments_count: number | null,
readonly installments_paid: number | null,
readonly paid_amount: number | null,
readonly left_amount: number | null,
readonly numbers: App.Data.CardNumberData[],
readonly due_label: string | null,
readonly due: App.Data.DeadlineData[],
readonly planned_label: string | null,
readonly planned: App.Data.PlannedRowData[],
readonly can_add_planned: boolean,
readonly payments_label: string | null,
readonly payments_more: string,
readonly payments: App.Data.PaymentYearData[],
readonly pay: App.Data.DeadlineData[],
readonly can_pay: boolean,
readonly can_add_expense: boolean,
readonly payments_previous: string | null,
readonly payments_next: string | null,
};
export type CardChoiceData = {
readonly value: string,
readonly label: string,
readonly person_id: string | null,
readonly organization_id: string | null,
readonly category_id: string | null,
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
readonly due_amount: number | null,
readonly due_late: boolean,
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
readonly addable: App.Data.CardAddData[],
readonly documents: App.Data.DocumentData[],
readonly documents_previous: string | null,
readonly documents_next: string | null,
readonly can_edit: boolean,
readonly can_delete: boolean,
readonly can_delete_documents: boolean,
readonly budget: App.Data.CardBudgetData | null,
};
export type CardFieldData = {
readonly column: string,
readonly label: string,
readonly kind: string,
readonly required: boolean,
readonly options: App.Data.OptionData[],
readonly show_when: Record<string, string[]>,
readonly label_when: Record<string, Record<string, string>>,
};
export type CardNumberData = {
readonly label: string,
readonly amount: number | null,
readonly count: number | null,
readonly hint: string | null,
readonly hint_amount: number | null,
};
export type CardTypeData = {
readonly type: string,
readonly label: string,
readonly plural_label: string,
readonly description: string,
readonly area: string,
readonly area_label: string,
};
export type CardValueData = {
readonly label: string,
readonly text: string,
readonly href: string | null,
};
export type CategoryComparisonData = {
readonly name: string,
readonly icon: string | null,
readonly color: string | null,
readonly spent: number,
readonly planned: number,
readonly estimated: boolean,
readonly late_title: string | null,
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
export type ChecklistStepData = {
readonly key: string,
readonly done: boolean,
readonly name: string | null,
};
export type ComparisonData = {
readonly month: string,
readonly planned: number,
readonly spent: number,
readonly incomes: number,
readonly incomes_planned: number,
readonly days_left: number | null,
readonly categories: App.Data.CategoryComparisonData[],
readonly months: App.Data.MonthBarData[],
readonly unpaid: App.Data.DeadlineData[],
};
export type DeadlineData = {
readonly kind: string,
readonly planned_id: number,
readonly title: string,
readonly planned_title: string,
readonly date: string,
readonly original_date: string,
readonly amount: number,
readonly base_amount: number | null,
readonly estimated: boolean,
readonly notes: string | null,
readonly late: boolean,
readonly skipped: boolean,
readonly payment: App.Data.PaymentData | null,
readonly account_id: number | null,
readonly account: string | null,
readonly card: App.Data.CardData | null,
readonly income_type: string | null,
readonly person: string | null,
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
readonly file_url: string,
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
export type ExceptionData = {
readonly id: number,
readonly occurrence_date: string,
readonly type: string,
readonly amount_override: number | null,
readonly date_override: string | null,
readonly notes: string | null,
readonly deadline: App.Data.DeadlineData | null,
};
export type ExpenseCategoryData = {
readonly id: number,
readonly name: string,
readonly parent_id: number | null,
readonly icon: string | null,
readonly color: string | null,
readonly card_type: string | null,
readonly card_label: string | null,
readonly children: App.Data.ExpenseCategoryData[],
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
export type HomeData = {
readonly first_name: string,
readonly today: string,
readonly total: number,
readonly payments: number,
readonly late: number,
readonly entries: App.Data.AgendaEntryData[],
readonly more: boolean,
readonly incoming: App.Data.AgendaEntryData[],
readonly pending: App.Data.PendingFileData[],
readonly pending_count: number,
readonly checklist: App.Data.ChecklistStepData[],
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
export type LoanFiguresData = {
readonly total_to_pay: number | null,
readonly cost: number | null,
};
export type MonthBarData = {
readonly month: string,
readonly spent: number,
readonly planned: number,
readonly current: boolean,
};
export type MonthSummaryData = {
readonly month: string,
readonly incomes: number,
readonly salaries_in: number,
readonly salaries_expected: number,
readonly expenses: number,
readonly payments: number,
readonly balance: number,
};
export type MovementData = {
readonly kind: string,
readonly id: number,
readonly title: string,
readonly date: string,
readonly amount: number,
readonly fee_amount: number | null,
readonly account_id: number | null,
readonly account: string | null,
readonly category_id: number | null,
readonly category: string | null,
readonly icon: string | null,
readonly color: string | null,
readonly organization_id: number | null,
readonly organization: string | null,
readonly person_id: number | null,
readonly card: string | null,
readonly planned_id: number | null,
readonly occurrence_date: string | null,
readonly type: string | null,
readonly employment_id: number | null,
readonly documents: App.Data.DocumentData[],
readonly can_edit: boolean,
readonly can_delete: boolean,
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
export type PaymentData = {
readonly kind: string,
readonly id: number,
readonly date: string,
readonly amount: number,
readonly account: string | null,
};
export type PaymentYearData = {
readonly year: number,
readonly total: number,
readonly count: number,
readonly payments: App.Data.MovementData[],
};
export type PendingFileData = {
readonly id: number,
readonly name: string,
readonly mime_type: string,
readonly size: number,
readonly url: string,
readonly title: string,
readonly preview_url: string | null,
readonly links: {
type: string,
id: number,
}[],
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
export type PlannedChoiceData = {
readonly kind: string,
readonly value: string,
readonly label: string,
readonly category_id: string | null,
readonly card: string | null,
readonly organization_id: string | null,
readonly financial_account_id: string | null,
readonly person_id: string | null,
readonly employment_id: string | null,
readonly type: string | null,
};
export type PlannedDetailData = {
readonly row: App.Data.PlannedRowData,
readonly year_total: number,
readonly skipped_months: number[],
readonly next: App.Data.DeadlineData[],
readonly exceptions: App.Data.ExceptionData[],
readonly values: App.Data.CardValueData[],
readonly payments: App.Data.PaymentYearData[],
readonly can_edit: boolean,
readonly can_pay: boolean,
};
export type PlannedRowData = {
readonly kind: string,
readonly id: number,
readonly title: string,
readonly card: App.Data.CardData | null,
readonly unit: string,
readonly interval: number,
readonly start_date: string,
readonly end_date: string | null,
readonly recurring: boolean,
readonly person: string | null,
readonly next_date: string | null,
readonly account: string | null,
readonly amount: number,
readonly estimated: boolean,
readonly icon: string | null,
readonly color: string | null,
readonly ended: boolean,
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
