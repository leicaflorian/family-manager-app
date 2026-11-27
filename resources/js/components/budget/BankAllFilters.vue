<script setup lang="ts">
/**
 * The filters above the list of the tab *All* of *From the bank*, as the boards *Dalla banca, tutti i movimenti* draw
 * them: a search in the text of the movement, the account, the status, the month and the direction (money out or in),
 * then the line with how many movements the filters find and their sum, and *Remove the filters* while one is on. A
 * change asks the server at once (the text after a short pause in typing) with a partial reload of the list that starts
 * over (`reset` of the rows) and writes the filters in the address, so the next blocks of the list and a refresh keep
 * them. Every filter is always sent, empty ones as an empty text, so none of the old address stays.
 *
 * @group forms
 */
import { router } from '@inertiajs/vue3';
import { transChoice, trans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import { useFormat } from '@/composables/useFormat';
import { monthName } from '@/lib/budget';
import { todayInRome } from '@/lib/format';
import { textOrNull } from '@/lib/forms';

/** How long after the last key the text is sent, in milliseconds. */
const TYPING_PAUSE = 350;

/** How many months back the menu of the months goes, this one included. */
const MONTHS_BACK = 13;

const { filters, accounts, total } = defineProps<{
    /** The filters in use, as the server read them. */
    filters: {
        q: string | null;
        account: number | null;
        status: string | null;
        month: string | null;
        direction: string | null;
    };
    /** The accounts that have transactions, to choose from. */
    accounts: { id: number; name: string }[];
    /** What the filters find: how many movements and their sum. */
    total: { count: number; sum: number };
}>();

const { money } = useFormat();

/** The text in the box, which can be ahead of the one the server has. */
const text = ref(filters.q ?? '');

/** The timer of the pause in typing. */
let pause: ReturnType<typeof setTimeout> | null = null;

/** Whether any filter is on. */
const anyOn = computed((): boolean =>
    Object.values(filters).some((value) => value !== null),
);

/** The statuses the list can be narrowed to, *All statuses* first. */
const statuses = computed(() => [
    { value: '', label: trans('All statuses') },
    { value: 'unsorted', label: trans('Left to sort') },
    { value: 'linked', label: trans('Linked') },
    { value: 'fee', label: trans('Commissions') },
    { value: 'transfer', label: trans('Transfers') },
    { value: 'ignored', label: trans('Ignored') },
    { value: 'pending', label: trans('Pending at the bank') },
]);

/** The directions, *Money out and in* first. */
const directions = computed(() => [
    { value: '', label: trans('Money out and in') },
    { value: 'expense', label: trans('Money out') },
    { value: 'income', label: trans('Money in') },
]);

/** The accounts of the menu, *All the accounts* first. */
const accountChoices = computed(() => [
    { value: '', label: trans('All the accounts') },
    ...accounts.map((account) => ({
        value: String(account.id),
        label: account.name,
    })),
]);

/** The months of the menu: the last ones, and the one chosen when it is older. */
const monthChoices = computed(() => {
    const [year, month] = todayInRome().split('-').map(Number) as [
        number,
        number,
    ];
    const months = Array.from({ length: MONTHS_BACK }, (_, back) => {
        const at = new Date(Date.UTC(year, month - 1 - back, 1));

        return `${at.getUTCFullYear()}-${String(at.getUTCMonth() + 1).padStart(2, '0')}`;
    });

    if (filters.month !== null && !months.includes(filters.month)) {
        months.push(filters.month);
    }

    return [
        { value: '', label: trans('All months') },
        ...months.map((value) => ({ value, label: monthName(value) })),
    ];
});

/** The line under the filters: «94 movements · -1.842,30 €». */
const found = computed((): string =>
    transChoice(
        '{0} No movements|{1} :count movement · :sum|[2,*] :count movements · :sum',
        total.count,
        { count: String(total.count), sum: money(total.sum, 'EUR') },
    ),
);

/**
 * Asks the server for the list with some filters changed.
 *
 * @param changed - the filters that changed, as the text the address carries (empty for none)
 */
function ask(changed: Partial<Record<string, string | number>>): void {
    if (pause !== null) {
        clearTimeout(pause);
        pause = null;
    }

    router.reload({
        data: {
            show: 'all',
            q: text.value.trim(),
            account: filters.account ?? '',
            status: filters.status ?? '',
            month: filters.month ?? '',
            direction: filters.direction ?? '',
            ...changed,
        },
        // Every prop the new filters change; the rows start over, the other pages are not asked for
        only: ['rows', 'next', 'total', 'filters'],
        reset: ['rows'],
        replace: true,
    });
}

/**
 * Asks with one filter taken from a menu.
 *
 * @param name - the filter
 * @param value - what the menu gave
 */
function choose(name: string, value: unknown): void {
    const chosen = textOrNull(value) ?? '';
    const current = String(filters[name as keyof typeof filters] ?? '');

    if (chosen !== current) {
        ask({
            [name]:
                name === 'account' && chosen !== '' ? Number(chosen) : chosen,
        });
    }
}

/** Takes every filter off, the text too. */
function removeAll(): void {
    text.value = '';
    ask({ q: '', account: '', status: '', month: '', direction: '' });
}

watch(text, (value) => {
    if (value.trim() === (filters.q ?? '')) {
        return;
    }

    if (pause !== null) {
        clearTimeout(pause);
    }

    pause = setTimeout(() => ask({ q: value.trim() }), TYPING_PAUSE);
});

// The server says what it took: «Remove the filters» (and a change from outside) empties the box
watch(
    () => filters.q,
    (value) => {
        if (value === null && pause === null) {
            text.value = '';
        }
    },
);

onBeforeUnmount(() => {
    if (pause !== null) {
        clearTimeout(pause);
    }
});
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <FmSearchField
            v-model="text"
            :label="$t('Search in the text of the movement')"
            @submit="ask({ q: text.trim() })"
        />
        <div class="grid grid-cols-2 gap-2 wide:grid-cols-4">
            <FormKit
                type="dropdown"
                name="account"
                :label="$t('Bank account')"
                :options="accountChoices"
                :model-value="
                    filters.account === null ? '' : String(filters.account)
                "
                :classes="{ label: 'sr-only', outer: 'mb-0!' }"
                @update:model-value="
                    (value: unknown) => choose('account', value)
                "
            />
            <FormKit
                type="dropdown"
                name="status"
                :label="$t('Status')"
                :options="statuses"
                :model-value="filters.status ?? ''"
                :classes="{ label: 'sr-only', outer: 'mb-0!' }"
                @update:model-value="
                    (value: unknown) => choose('status', value)
                "
            />
            <FormKit
                type="dropdown"
                name="month"
                :label="$t('Month')"
                :options="monthChoices"
                :model-value="filters.month ?? ''"
                :classes="{ label: 'sr-only', outer: 'mb-0!' }"
                @update:model-value="(value: unknown) => choose('month', value)"
            />
            <FormKit
                type="dropdown"
                name="direction"
                :label="$t('Direction')"
                :options="directions"
                :model-value="filters.direction ?? ''"
                :classes="{ label: 'sr-only', outer: 'mb-0!' }"
                @update:model-value="
                    (value: unknown) => choose('direction', value)
                "
            />
        </div>
        <div class="flex items-baseline justify-between gap-3 px-1">
            <span role="status" class="text-label text-ink">{{ found }}</span>
            <button
                v-if="anyOn"
                type="button"
                class="cursor-pointer border-0 bg-transparent p-0 text-label text-action hover:text-action-dark"
                @click="removeAll"
            >
                {{ $t('Remove the filters') }}
            </button>
        </div>
    </div>
</template>
