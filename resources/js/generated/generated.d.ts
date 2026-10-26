declare namespace App {
namespace Data {
export type FamigliaData = {
readonly name: string,
};
}
namespace Enums {
export type Ruolo = 'admin' | 'member';
export type RuoloPersona = 'parent' | 'child' | 'other';
export type StatoAbbonamento = 'active' | 'cancelled';
export type StatoBene = 'in_use' | 'disposed';
export type StatoCasa = 'active' | 'archived';
export type StatoConto = 'active' | 'closed';
export type StatoDocumento = 'active' | 'archived' | 'expired';
export type StatoFinanziamento = 'active' | 'paid_off';
export type StatoIscrizione = 'active' | 'ended';
export type StatoLavoro = 'active' | 'ended';
export type StatoPolizza = 'active' | 'expired' | 'cancelled';
export type StatoPratica = 'draft' | 'submitted' | 'approved' | 'rejected';
export type StatoPromemoria = 'open' | 'done';
export type StatoUtenza = 'active' | 'terminated';
export type StatoVeicolo = 'active' | 'sold';
export type TipoAbbonamento = 'streaming' | 'software' | 'cloud' | 'web' | 'other';
export type TipoConto = 'checking_account' | 'card' | 'deposit' | 'online_wallet' | 'other';
export type TipoEccezione = 'skip' | 'amount_override' | 'date_override';
export type TipoEnte = 'bank' | 'insurance' | 'utility_provider' | 'employer' | 'public_office' | 'school' | 'healthcare' | 'shop' | 'other';
export type TipoEntrata = 'salary' | 'benefit' | 'refund' | 'other';
export type TipoEventoSalute = 'visit' | 'exam' | 'vaccine' | 'prescription' | 'optical';
export type TipoIscrizione = 'school' | 'sport' | 'course' | 'other';
export type TipoManutenzione = 'service' | 'inspection' | 'repair' | 'boiler_service' | 'other';
export type TipoPolizza = 'car' | 'life' | 'home' | 'health' | 'accident' | 'fund' | 'other';
export type TipoPratica = 'tax_return' | 'isee' | 'benefit' | 'parental_leave' | 'municipal' | 'other';
export type TipoUtenza = 'electricity' | 'gas' | 'water' | 'internet' | 'phone' | 'waste_tax' | 'tv_license';
export type UnitaIntervallo = 'week' | 'month' | 'year';
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
