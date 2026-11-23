<script setup lang="ts">
/**
 * *Settings → Linked banks*: the banks the family linked, each with how it is doing (connected until a day, to
 * connect again, not working), the accounts read through it and when its transactions were read last. An
 * administrator opens *Connect a bank*, the panel with the search and the list of banks (asked to the server only
 * when the panel opens); a member sees the list without buttons. While a bank waits for its first read the page
 * asks for the state again by itself. Choosing a bank, and *Reconnect* on a bank that ended, send the person to the
 * bank's own website (the server answers with the address); *Disconnect* asks first. *Update* reads a bank that
 * works now and the server says how many movements are new. While a visit runs no button starts another.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronRight,
    Landmark,
    LinkIcon,
    Plus,
    RefreshCw,
    Unplug,
    Wallet,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmConfirmDelete from '@/components/fm/FmConfirmDelete.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmSettingsPage from '@/components/fm/FmSettingsPage.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import BankMark from '@/components/banks/BankMark.vue';
import { useWideLayout } from '@/composables/useWideLayout';
import { useAppName } from '@/composables/useAppName';
import { usePageMeta } from '@/composables/usePageMeta';
import { useReadingPoll } from '@/composables/useReadingPoll';
import { lastReadText, readTimes } from '@/lib/banks';
import { formatDate } from '@/lib/format';
import { plainText } from '@/lib/text';
import { destroy, store, sync } from '@/routes/banks';

const { connections, can_change, sync_hours, consent_days, institutions } =
    defineProps<{
        /** The banks the family linked. */
        connections: App.Data.BankConnectionData[];
        /** Whether who looks can link and unlink banks (an administrator). */
        can_change: boolean;
        /** The hours of the day the banks are read, as the server schedules them. */
        sync_hours: number[];
        /** The days the permission of a bank lasts at most. */
        consent_days: number;
        /**
         * The banks that can be linked: missing until the panel asks for them, `null` when the provider did not answer.
         */
        institutions?: App.Data.BankInstitutionData[] | null;
    }>();

const appName = useAppName();
const open = ref(false);
const query = ref('');
/** The bank the person is asked to disconnect, or null while no question is open. */
const disconnecting = ref<App.Data.BankConnectionData | null>(null);
/** A visit of a button is running: the buttons wait, so a double tap does not send it twice. */
const busy = ref(false);

/** The options of every visit of the page: they keep the scroll and tell when one is running. */
const visit = {
    preserveScroll: true,
    onStart: (): void => {
        busy.value = true;
    },
    onFinish: (): void => {
        busy.value = false;
    },
};

/** The hours of the reads written as a sentence: «at 7, at 13 and at 19». */
const times = computed((): string => readTimes(sync_hours));

/** Whether the page has the wide layout: the button to connect sits beside the title, else under it. */
const wide = useWideLayout();

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
 * Sends the person to the website of a bank to give the permission: the server answers with its address and the
 * browser goes there, to come back to this page.
 *
 * @param institution - the name of the bank
 */
function connect(institution: string): void {
    if (busy.value) {
        return;
    }

    router.post(store().url, { institution }, visit);
}

/**
 * Reads a bank now: the server says how many movements are new.
 *
 * @param connection - the bank to read
 */
function refresh(connection: App.Data.BankConnectionData): void {
    if (busy.value) {
        return;
    }

    router.post(sync(connection.id).url, {}, visit);
}

/** Closes the question about disconnecting, with or without disconnecting. */
const askOpen = computed({
    get: (): boolean => disconnecting.value !== null,
    set: (value: boolean): void => {
        if (!value) {
            disconnecting.value = null;
        }
    },
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

const { title, head } = usePageMeta({
    title: () => trans('Linked banks'),
    parent: 'settings',
});
</script>

<template>
    <Head :title="head" />

    <FmSettingsPage current="banks">
        <template #title>
            <div class="flex flex-col gap-4">
                <FmTitle
                    :title="title"
                    :subtitle="
                        $t('The transactions arrive by themselves :times', {
                            times,
                        })
                    "
                >
                    <template
                        v-if="wide && can_change && connections.length > 0"
                        #actions
                    >
                        <FmButton :icon="Plus" @click="open = true">{{
                            $t('Connect a bank')
                        }}</FmButton>
                    </template>
                </FmTitle>
                <FmButton
                    v-if="!wide && can_change && connections.length > 0"
                    :icon="Plus"
                    class="self-start"
                    @click="open = true"
                    >{{ $t('Connect a bank') }}</FmButton
                >
            </div>
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
        <FmNotice v-if="connections.length === 0 && !can_change">{{
            $t(
                'Only who manages the family connects and disconnects the banks.',
            )
        }}</FmNotice>

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
                            class="inline-flex self-start rounded-tag bg-overdue-soft px-2 py-[3px] text-meta-strong whitespace-nowrap text-overdue"
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
                <div
                    v-if="can_change"
                    class="flex flex-wrap gap-2.5 border-t border-divider pt-3.5"
                >
                    <FmButton
                        v-if="connection.status !== 'active'"
                        :icon="LinkIcon"
                        :disabled="busy"
                        @click="connect(connection.institution)"
                        >{{ $t('Reconnect') }}</FmButton
                    >
                    <FmButton
                        v-if="connection.status !== 'needs_reconnect'"
                        variant="outline"
                        :icon="RefreshCw"
                        :disabled="busy"
                        @click="refresh(connection)"
                        >{{ $t('Update') }}</FmButton
                    >
                    <FmButton
                        variant="text"
                        :icon="Unplug"
                        class="text-overdue"
                        :disabled="busy"
                        @click="disconnecting = connection"
                        >{{ $t('Disconnect') }}</FmButton
                    >
                </div>
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
                { app: appName, days: String(consent_days) },
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
                                :disabled="busy"
                                class="flex w-full items-center gap-3 border-0 bg-transparent px-3.5 py-3 text-left text-inherit"
                                @click="connect(bank.name)"
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
                        $tChoice(
                            '{0} No banks of :total. Not finding yours? It cannot be connected for now.|{1} 1 bank of :total. Not finding yours? It cannot be connected for now.|[2,*] :count banks of :total. Not finding yours? It cannot be connected for now.',
                            found.length,
                            { total: String(institutions.length) },
                        )
                    }}</span>
                </template>
            </div>
        </FmBottomSheet>

        <FmConfirmDelete
            v-if="disconnecting !== null"
            v-model:open="askOpen"
            :url="destroy(disconnecting.id).url"
            :question="
                $t('Disconnect :bank?', { bank: disconnecting.institution })
            "
            :text="
                $t(
                    ':app stops reading its transactions. The ones that pay an expense or an income stay, the others are deleted.',
                    { app: appName },
                )
            "
            :confirm-label="$t('Disconnect')"
        />
    </FmSettingsPage>
</template>
