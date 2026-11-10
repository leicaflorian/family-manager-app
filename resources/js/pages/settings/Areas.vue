<script setup lang="ts">
/**
 * *Settings → Areas*: the order of the areas of the family and the ones it
 * hides. A row for each area, with the handle to drag it, the dot of its
 * colour, its name, its active cards and the switch *Show*; the menu of the row
 * moves it up or down for the keyboard. Each change is saved at once, without
 * *Save*, as the filters of the *Agenda*: the page changes first and sends the
 * whole choice, one visit after the other. A hidden area stays in the list,
 * lighter, with «Hidden» and what happens to its cards. *Back to the default
 * order* puts everything back as it was. A member sees the list only.
 */
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, RotateCcw } from '@lucide/vue';
import { transChoice, trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmMenu from '@/components/fm/FmMenu.vue';
import FmMenuItem from '@/components/fm/FmMenuItem.vue';
import FmSortableList from '@/components/fm/FmSortableList.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { useVisitQueue } from '@/composables/useVisitQueue';
import type { Area } from '@/lib/areas';
import { areaFill, areaOf } from '@/lib/areas';
import { cardCountText } from '@/lib/cardTexts';
import { settings } from '@/routes';
import { reset, update } from '@/routes/settings/areas';
import { usePageTrail } from '@/composables/usePageTrail';

const { settings: page } = defineProps<{
    /** The areas of the family in its order, with their cards and whether they are hidden, and whether who looks can change them. */
    settings: App.Data.AreaSettingsData;
}>();

/** A row of the list: the area of the server with the key the list wants. */
type AreaRow = App.Data.AreaSettingData & { key: string };

/** The order shown: the page owns it and changes it before the server answers. */
const order = ref<string[]>([]);
/** The areas hidden on the page. */
const hidden = ref<string[]>([]);

/** Starts again from what the server says: at the start, after a failed save and after going back to the default. */
function fromServer(): void {
    order.value = page.areas.map((row) => row.area);
    hidden.value = page.areas
        .filter((row) => row.hidden)
        .map((row) => row.area);
}

fromServer();

/** The rows in the order of the page; a new array at each change, so the list shows it. */
const rows = computed((): AreaRow[] =>
    order.value.flatMap((area) => {
        const row = page.areas.find((item) => item.area === area);

        return row === undefined ? [] : [{ ...row, key: area }];
    }),
);

const { enqueue } = useVisitQueue();

/**
 * Saves the choice of the page as it is when the visit starts: the visits
 * before it may have changed it. A save that does not succeed (a 422, a 500,
 * a lost connection) puts the page back as the server has it.
 */
function save(): void {
    enqueue((done) => {
        let saved = false;

        router.put(
            update.url(),
            { order: [...order.value], hidden: [...hidden.value] },
            {
                preserveScroll: true,
                onSuccess: () => {
                    saved = true;
                },
                onFinish: () => {
                    if (!saved) {
                        fromServer();
                    }
                    done();
                },
            },
        );
    });
}

/**
 * Shows the areas in a new order and saves it.
 *
 * @param keys - the areas in their new order
 */
function sort(keys: string[]): void {
    order.value = keys;
    save();
}

/**
 * Shows or hides an area and saves the choice.
 *
 * @param area - the area
 * @param value - the value of the switch: on is shown
 */
function setShown(area: string, value: unknown): void {
    const show = value === true;

    // FormKit fires @input also when the model is set from the server: nothing changed, nothing to save
    if (show === !hidden.value.includes(area)) {
        return;
    }

    hidden.value = show
        ? hidden.value.filter((item) => item !== area)
        : [...hidden.value, area];
    save();
}

/** Goes back to the default order with nothing hidden, then shows what the server sends. */
function backToDefault(): void {
    enqueue((done) =>
        router.post(
            reset.url(),
            {},
            { preserveScroll: true, onSuccess: fromServer, onFinish: done },
        ),
    );
}

/**
 * The classes of the dot of an area.
 *
 * @param area - the area
 * @returns the colour of the area
 */
function dot(area: string): string {
    return areaFill[areaOf(area) ?? ('general' as Area)];
}

/**
 * What a hidden area says about its cards, when it has some.
 *
 * @param count - its active cards
 * @returns that they stay with their deadlines
 */
function cardsStay(count: number): string {
    return transChoice(
        '{1} Has 1 card: it stays, with its deadlines.|[2,*] Has :count cards: they stay, with their deadlines.',
        count,
    );
}

usePageTrail(() => [
    { label: trans('Settings'), href: settings().url },
    { label: trans('Areas') },
]);
</script>

<template>
    <Head :title="$t('Areas')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('Areas')"
            :subtitle="$t('Their order, and the ones you hide')"
            :back="settings().url"
            :back-label="$t('Settings')"
        />

        <FmCard>
            <FmSortableList
                :items="rows"
                :disabled="!page.can_change"
                :label="$t('Areas')"
                class="m-0 flex list-none flex-col divide-y divide-divider p-0"
                @sort="sort"
            >
                <template #item="{ item, index, moveUp, moveDown }">
                    <div
                        class="flex min-h-14 items-center gap-3 py-2"
                        :class="hidden.includes(item.area) && 'opacity-60'"
                    >
                        <span
                            data-testid="area-dot"
                            aria-hidden="true"
                            class="size-3 shrink-0 rounded-full"
                            :class="dot(item.area)"
                        />
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span
                                class="text-body-strong break-words text-ink"
                                >{{ item.label }}</span
                            >
                            <span class="text-meta text-ink-soft">
                                {{ cardCountText(item.count)
                                }}<template v-if="hidden.includes(item.area)">
                                    · {{ $t('Hidden') }}</template
                                >
                            </span>
                            <span
                                v-if="
                                    hidden.includes(item.area) && item.count > 0
                                "
                                class="text-meta text-ink-soft"
                                >{{ cardsStay(item.count) }}</span
                            >
                        </div>
                        <template v-if="page.can_change">
                            <FormKit
                                type="toggle"
                                :name="`show-${item.area}`"
                                :label="$t('Show')"
                                :aria-label="
                                    $t('Show :area', { area: item.label })
                                "
                                outer-class="$reset"
                                label-class="sr-only sm:not-sr-only"
                                :model-value="!hidden.includes(item.area)"
                                @input="
                                    (value: unknown) =>
                                        setShown(item.area, value)
                                "
                            />
                            <FmMenu
                                plain
                                small
                                :label="
                                    $t('More actions on :title', {
                                        title: item.label,
                                    })
                                "
                            >
                                <FmMenuItem
                                    v-if="index > 0"
                                    :icon="ArrowUp"
                                    @select="moveUp"
                                    >{{ $t('Move up') }}</FmMenuItem
                                >
                                <FmMenuItem
                                    v-if="index < rows.length - 1"
                                    :icon="ArrowDown"
                                    @select="moveDown"
                                    >{{ $t('Move down') }}</FmMenuItem
                                >
                            </FmMenu>
                        </template>
                    </div>
                </template>
            </FmSortableList>
        </FmCard>

        <div v-if="page.can_change" class="flex justify-end">
            <FmButton
                variant="outline"
                :icon="RotateCcw"
                @click="backToDefault"
                >{{ $t('Back to the default order') }}</FmButton
            >
        </div>
    </div>
</template>
