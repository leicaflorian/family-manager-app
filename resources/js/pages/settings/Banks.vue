<script setup lang="ts">
/**
 * *Settings → Linked banks*: the banks the family linked, each with how it is doing (connected until a day, to
 * connect again, not working), the accounts read through it and when its transactions were read last. An
 * administrator opens *Connect a bank*, the panel with the search and the list of banks (asked to the server only
 * when the panel opens); a member sees the list without buttons. While a bank waits for its first read the page
 * asks for the state again by itself. Choosing a bank, *Reconnect*, *Disconnect* and *Update* come with their routes.
 */
import { Head, router } from '@inertiajs/vue3';
import { ChevronRight, Landmark, Plus, Wallet } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmSettingsPage from '@/components/fm/FmSettingsPage.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import BankMark from '@/components/banks/BankMark.vue';
import { useAppName } from '@/composables/useAppName';
import { usePageTrail } from '@/composables/usePageTrail';
import { useReadingPoll } from '@/composables/useReadingPoll';
import { lastReadText } from '@/lib/banks';
import { formatDate } from '@/lib/format';
import { plainText } from '@/lib/text';
import { settings as settingsIndex } from '@/routes';

const { connections, can_change, institutions } = defineProps<{
    /** The banks the family linked. */
    connections: App.Data.BankConnectionData[];
    /** Whether who looks can link and unlink banks (an administrator). */
    can_change: boolean;
    /**
     * The banks that can be linked: missing until the panel asks for them, `null` when the provider did not answer.
     */
    institutions?: App.Data.BankInstitutionData[] | null;
}>();

/** The longest the consent of a bank lasts, as the text under the list says it. */
const CONSENT_DAYS = 180;

const appName = useAppName();
const open = ref(false);
const query = ref('');

// A bank waiting for its first read: ask only for the connections, so the panel and what is written stay as they are
useReadingPoll(
    () => connections.some((connection) => connection.syncing),
    ['connections'],
);

/** The banks the search lets through, the ones that have the typed letters in their name. */
const found = computed((): App.Data.BankInstitutionData[] => {
    const wanted = plainText(query.value.trim());

    return (institutions ?? []).filter((bank) =>
        plainText(bank.name).includes(wanted),
    );
});

/** Asks the server for the list of banks, when the page does not have it. */
function loadInstitutions(): void {
    router.reload({ only: ['institutions'], preserveUrl: true });
}

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';

        if (!Array.isArray(institutions)) {
            loadInstitutions();
        }
    }
});

/**
 * The line that says where a connection is, with the colour of its state.
 *
 * @param connection - the connection
 */
function statusText(connection: App.Data.BankConnectionData): string {
    if (connection.status === 'active') {
        return connection.valid_until
            ? trans('Connected until :date', {
                  date: formatDate(connection.valid_until),
              })
            : trans('Connected');
    }

    if (connection.status === 'needs_reconnect') {
        return connection.valid_until
            ? trans('The connection ended on :date', {
                  date: formatDate(connection.valid_until),
              })
            : trans('The connection has ended');
    }

    return trans('We could not read this bank. We will try again.');
}

/**
 * The line under the state: the read that is running, or when the last one was.
 *
 * @param connection - the connection
 */
function readText(connection: App.Data.BankConnectionData): string | null {
    if (connection.syncing) {
        return trans('Reading the transactions…');
    }

    return connection.last_synced_at
        ? lastReadText(connection.last_synced_at)
        : null;
}

usePageTrail(() => [
    { label: trans('Settings'), href: settingsIndex().url },
    { label: trans('Linked banks') },
]);
</script>

<template>
    <Head :title="$t('Linked banks')" />

    <FmSettingsPage current="banks">
        <template #title>
            <FmTitle
                :title="$t('Linked banks')"
                :subtitle="
                    $t('The transactions arrive by themselves at 7, 13 and 19')
                "
                :back="settingsIndex().url"
                :back-label="$t('Settings')"
            >
                <template v-if="can_change && connections.length > 0" #actions>
                    <FmButton :icon="Plus" @click="open = true">{{
                        $t('Connect a bank')
                    }}</FmButton>
                </template>
            </FmTitle>
        </template>

        <FmEmpty
            v-if="connections.length === 0"
            :icon="Landmark"
            :title="$t('No bank connected')"
            :text="
                $t(
                    'Connect the account and the transactions arrive by themselves: the fixed expenses recognised are marked paid, the others are in Budget, From the bank.',
                )
            "
        >
            <FmButton v-if="can_change" :icon="Plus" @click="open = true">{{
                $t('Connect a bank')
            }}</FmButton>
        </FmEmpty>

        <template v-else>
            <FmCard v-for="connection in connections" :key="connection.id">
                <div class="flex items-start gap-3">
                    <BankMark :name="connection.institution" />
                    <span class="flex min-w-0 grow flex-col gap-1">
                        <span class="text-body-strong">{{
                            connection.institution
                        }}</span>
                        <span
                            v-if="connection.status === 'needs_reconnect'"
                            class="inline-flex self-start rounded-tag bg-warning-soft px-2 py-[3px] text-meta-strong whitespace-nowrap text-warning"
                            >{{ $t('To connect again') }}</span
                        >
                        <span
                            v-else-if="connection.status === 'error'"
                            class="inline-flex self-start rounded-tag bg-overdue/10 px-2 py-[3px] text-meta-strong whitespace-nowrap text-overdue"
                            >{{ $t('Not working') }}</span
                        >
                        <span
                            :class="
                                connection.status === 'active'
                                    ? 'text-meta-strong text-paid'
                                    : 'text-meta text-ink-soft'
                            "
                            >{{ statusText(connection) }}</span
                        >
                        <span
                            v-if="readText(connection)"
                            class="text-meta text-ink-soft"
                            >{{ readText(connection) }}</span
                        >
                    </span>
                </div>
                <ul
                    v-if="connection.accounts.length > 0"
                    class="m-0 flex list-none flex-col gap-1.5 p-0"
                >
                    <li
                        v-for="account in connection.accounts"
                        :key="account"
                        class="flex items-center gap-2 text-body-sm"
                    >
                        <FmIcon
                            :icon="Wallet"
                            :size="18"
                            class="shrink-0 text-ink-soft"
                        />{{ account }}
                    </li>
                </ul>
            </FmCard>

            <FmNotice v-if="!can_change">{{
                $t(
                    'Only who manages the family connects and disconnects the banks.',
                )
            }}</FmNotice>
        </template>

        <span class="text-meta text-ink-soft">{{
            $t(
                ':app reads only the transactions: it cannot make payments and does not see your bank password. The permission lasts up to :days days, then you renew it.',
                { app: appName, days: String(CONSENT_DAYS) },
            )
        }}</span>

        <FmBottomSheet v-model:open="open" :title="$t('Connect a bank')">
            <div class="flex flex-col gap-3.5">
                <p class="m-0 text-body-sm text-ink-soft">
                    {{
                        $t(
                            'Choose your bank: go to its website, sign in as usual and confirm. Then come back here.',
                        )
                    }}
                </p>
                <p
                    v-if="institutions === undefined"
                    role="status"
                    class="m-0 text-body-sm text-ink-soft"
                >
                    {{ $t('Loading the banks…') }}
                </p>
                <div
                    v-else-if="institutions === null"
                    role="status"
                    class="flex flex-col items-start gap-2.5"
                >
                    <p class="m-0 text-body-sm text-ink-soft">
                        {{
                            $t(
                                'We could not load the banks. Try again in a moment.',
                            )
                        }}
                    </p>
                    <FmButton variant="outline" @click="loadInstitutions">{{
                        $t('Try again')
                    }}</FmButton>
                </div>
                <template v-else>
                    <FmSearchField
                        v-model="query"
                        :label="$t('Search your bank')"
                    />
                    <ul
                        v-if="found.length > 0"
                        class="m-0 flex max-h-[40vh] list-none flex-col divide-y divide-divider overflow-y-auto rounded-card border border-edge-soft p-0"
                    >
                        <li v-for="bank in found" :key="bank.name">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 border-0 bg-transparent px-3.5 py-3 text-left text-inherit"
                            >
                                <BankMark
                                    :name="bank.name"
                                    :logo="bank.logo"
                                    small
                                />
                                <span class="grow text-body-strong">{{
                                    bank.name
                                }}</span>
                                <FmIcon
                                    :icon="ChevronRight"
                                    :size="18"
                                    class="shrink-0 text-ink-faint"
                                />
                            </button>
                        </li>
                    </ul>
                    <span class="text-meta text-ink-soft">{{
                        `${$tChoice(
                            '{0} No banks of :total|{1} 1 bank of :total|[2,*] :count banks of :total',
                            found.length,
                            { total: String(institutions.length) },
                        )}. ${$t('Not finding yours? It cannot be connected for now.')}`
                    }}</span>
                </template>
            </div>
        </FmBottomSheet>
    </FmSettingsPage>
</template>
