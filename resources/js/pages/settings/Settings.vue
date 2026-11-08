<script setup lang="ts">
/**
 * The index of the *Settings*: my account, mine only, and the settings that apply
 * to the whole family. The other items of the board (bills, subscription) come with their stages.
 */
import { Head } from '@inertiajs/vue3';
import { Bell, CalendarDays, FolderTree, Inbox, LayoutGrid, Tags } from '@lucide/vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { edit } from '@/routes/account';
import { edit as calendar } from '@/routes/calendar';
import { index } from '@/routes/categories';
import { index as expenseCategories } from '@/routes/expense-categories';
import { edit as mailSettings } from '@/routes/mail-settings';
import { edit as areasSettings } from '@/routes/settings/areas';
import { edit as notices } from '@/routes/settings/notices';

const {
    account,
    mainCategories,
    mainExpenseCategories,
    areaCount,
    hiddenAreas,
} = defineProps<{
    /** My account. */
    account: App.Data.AccountData;
    /** How many main document categories there are. */
    mainCategories: number;
    /** How many main expense categories there are. */
    mainExpenseCategories: number;
    /** How many areas there are. */
    areaCount: number;
    /** How many areas the family hid. */
    hiddenAreas: number;
}>();
</script>

<template>
    <Head :title="$t('Settings')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Settings')"
            :subtitle="
                $t(
                    'They apply to the whole :family family; my account is yours only',
                    { family: account.family },
                )
            "
        />
        <FmCard list>
            <FmRow
                :title="$t('My account')"
                :subtitle="`${account.first_name} ${account.last_name} · ${account.role}`"
                :href="edit().url"
            >
                <template #before>
                    <FmAvatar
                        :initials="account.initials"
                        :photo="account.photo"
                        :name="`${account.first_name} ${account.last_name}`"
                    />
                </template>
            </FmRow>
            <FmRow
                :title="$t('Calendar')"
                :subtitle="$t('Reminders and deadlines in Google Calendar')"
                :href="calendar().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="CalendarDays" :size="22" />
                    </span>
                </template>
            </FmRow>
        </FmCard>
        <FmCard :title="$t('The family')" list>
            <FmRow
                :title="$t('Notices')"
                :subtitle="$t('How many days before, where and to whom')"
                :href="notices().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Bell" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Address for the bills')"
                :subtitle="$t('The bills arrive by email by themselves')"
                :href="mailSettings().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Inbox" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Document categories')"
                :subtitle="
                    $tChoice(
                        '{0} No main categories|{1} :count main category|[2,*] :count main categories',
                        mainCategories,
                    )
                "
                :href="index().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="FolderTree" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Expense categories')"
                :subtitle="
                    $tChoice(
                        '{0} No main categories|{1} :count main category|[2,*] :count main categories',
                        mainExpenseCategories,
                    )
                "
                :href="expenseCategories().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="Tags" :size="22" />
                    </span>
                </template>
            </FmRow>
            <FmRow
                :title="$t('Areas')"
                :subtitle="
                    hiddenAreas === 0
                        ? $t(':count areas', { count: String(areaCount) })
                        : $tChoice(
                              '{1} :total areas, 1 hidden|[2,*] :total areas, :count hidden',
                              hiddenAreas,
                              { total: String(areaCount) },
                          )
                "
                :href="areasSettings().url"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                    >
                        <FmIcon :icon="LayoutGrid" :size="22" />
                    </span>
                </template>
            </FmRow>
        </FmCard>
    </div>
</template>
