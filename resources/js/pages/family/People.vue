<script setup lang="ts">
/**
 * *People*: the people of the family, with who they are and their account. On
 * the phone a list, on the computer a card each with how many documents and
 * records they have. An `admin` adds people and invites them.
 */
import { Head, Link } from '@inertiajs/vue3';
import { Plus, UserPlus } from '@lucide/vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmFamilyViews from '@/components/fm/FmFamilyViews.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmRow from '@/components/fm/FmRow.vue';
import { accountText, personSubtitle } from '@/lib/family';
import { create as invite } from '@/routes/family/invitations';
import { create, show } from '@/routes/people';

const { people, family, canEdit } = defineProps<{
    /** The people, in the order they were added. */
    people: App.Data.PersonData[];
    /** The family, with how many people and organizations. */
    family: App.Data.FamilySummaryData;
    /** Whoever is looking adds and invites people (an `admin`). */
    canEdit: boolean;
}>();
</script>

<template>
    <Head :title="$t('People')" />

    <div class="flex flex-col gap-4">
        <FmFamilyViews :family="family" current="people">
            <template v-if="canEdit" #actions>
                <FmActions
                    :actions="[
                        {
                            label: $t('New person'),
                            icon: Plus,
                            variant: 'soft',
                            href: create().url,
                        },
                        {
                            label: $t('Invite a family member'),
                            icon: UserPlus,
                            href: invite().url,
                        },
                    ]"
                />
            </template>
        </FmFamilyViews>

        <FmCard list class="lg:hidden">
            <FmRow
                v-for="person in people"
                :key="person.id"
                :title="`${person.first_name} ${person.last_name}`"
                :subtitle="`${personSubtitle(person)} · ${accountText(person)}`"
                :href="show.url(person.id)"
            >
                <template #before>
                    <FmAvatar
                        :initials="person.initials"
                        :photo="person.photo"
                        :name="`${person.first_name} ${person.last_name}`"
                        :size="44"
                    />
                </template>
                <template #after>
                    <span class="text-meta text-ink-soft">{{
                        $t(':count doc.', {
                            count: String(person.documents_count),
                        })
                    }}</span>
                </template>
            </FmRow>
        </FmCard>

        <div
            class="hidden gap-5 lg:grid lg:grid-cols-[repeat(auto-fill,minmax(260px,1fr))]"
        >
            <Link
                v-for="person in people"
                :key="person.id"
                :href="show.url(person.id)"
                class="flex flex-col gap-3.5 rounded-card-lg bg-card p-[22px] text-inherit no-underline"
            >
                <span class="flex items-center gap-3.5">
                    <FmAvatar
                        :initials="person.initials"
                        :photo="person.photo"
                        :name="`${person.first_name} ${person.last_name}`"
                        :size="56"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span class="text-subtitle"
                            >{{ person.first_name }}
                            {{ person.last_name }}</span
                        >
                        <span class="text-body-sm text-ink-soft">{{
                            personSubtitle(person)
                        }}</span>
                    </span>
                </span>
                <span
                    class="text-label"
                    :class="
                        person.account_label ? 'text-paid' : 'text-ink-soft'
                    "
                    >{{ accountText(person) }}</span
                >
                <span class="flex gap-6 border-t border-divider pt-3">
                    <span class="flex flex-col">
                        <span class="text-stat-lg">{{
                            person.documents_count
                        }}</span>
                        <span class="text-meta text-ink-soft">{{
                            $t('documents')
                        }}</span>
                    </span>
                    <span class="flex flex-col">
                        <span class="text-stat-lg">{{
                            person.records_count
                        }}</span>
                        <span class="text-meta text-ink-soft">{{
                            $t('records')
                        }}</span>
                    </span>
                </span>
            </Link>
        </div>

        <Link
            v-if="canEdit"
            :href="invite().url"
            class="flex items-center gap-3 rounded-card border-[1.5px] border-dashed border-ink-faint bg-card px-4 py-3.5 text-action no-underline lg:hidden"
        >
            <span
                class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
            >
                <FmIcon :icon="UserPlus" :size="22" />
            </span>
            <span class="flex grow flex-col gap-0.5">
                <span class="text-amount">{{
                    $t('Invite a family member')
                }}</span>
                <span class="text-meta text-ink-soft">{{
                    $t('They get an email to join the family')
                }}</span>
            </span>
        </Link>

        <FmNotice>{{
            $t(
                'Deadline alerts go to whoever has an account: those of the people without one go to the others.',
            )
        }}</FmNotice>
    </div>
</template>
