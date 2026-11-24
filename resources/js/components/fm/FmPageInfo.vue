<script setup lang="ts">
/**
 * The «i» beside the title of a page and the explanation it opens, as the boards *Posta-info*, *Posta-info-pc* and
 * the card *What is this page* of *Pannelli* draw it: a 44 px button with the Info icon, grey while closed and on
 * light blue while open. The explanation (`pageInfo.ts`) has the name of the page, *What it is for*, *How to use
 * it* with the steps numbered, *Good to know* on grey when there are notes, *Got it* and the link to a page nearby.
 * On the phone, and while the window is not wide, it rises from below (`FmBottomSheet`); in the wide layout
 * (`useWideLayout()`) it floats beside the page (`FmSidePanel`), which stays usable. The ×, Esc and *Got it* close it;
 * the address of the page does not change. Used by `FmTitle`, `FmCardHeader`, the *Scanner* and the linked phone.
 *
 * @group panels
 */
import { Link, usePage } from '@inertiajs/vue3';
import { Check, Copy, Info, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import { useCopy } from '@/composables/useCopy';
import { useFeatures } from '@/composables/useFeatures';
import { useOnColor } from '@/composables/useOnColor';
import { useWideLayout } from '@/composables/useWideLayout';
import type { PageInfo, PageInfoKey, PageInfoStep } from '@/lib/pageInfo';
import { pageInfo } from '@/lib/pageInfo';
import { cn } from '@/lib/utils';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmSidePanel from './FmSidePanel.vue';

const { infoKey } = defineProps<{
    /** The key of the page in `pageInfo.ts`. */
    infoKey: PageInfoKey;
}>();

/** The explanation is open. */
const open = ref(false);

/**
 * The explanation was opened once: the panel from below is made only then, and stays to slide away when it closes,
 * so a page that never opens it carries no panel.
 */
const opened = ref(false);

watch(open, (now) => {
    opened.value ||= now;
});

const wide = useWideLayout();
const features = useFeatures();
const page = usePage();
const onColor = useOnColor();
const copier = useCopy();

/**
 * The account is an administrator: the links to pages only an administrator opens are shown to it alone. A linked
 * phone shares no level, and counts as not one.
 */
const admin = computed((): boolean => {
    const user: unknown = page.props.auth?.user;

    return (
        typeof user === 'object' &&
        user !== null &&
        'role' in user &&
        user.role === 'admin'
    );
});

/** The explanation of the page, without the lines of the features that are off nor the links closed to the account. */
const info = computed((): PageInfo =>
    pageInfo(infoKey, features.value, { admin: admin.value }),
);

/** The name of the panel beside the page, for screen readers. */
const sideLabel = computed((): string =>
    trans(':page, what is this page', { page: info.value.title }),
);

/** The circle of the button: grey (white on a colour) while closed, light blue while open. */
const circle = computed((): string =>
    cn(
        'flex size-8 items-center justify-center rounded-full',
        open.value
            ? 'bg-action-soft text-action-dark'
            : onColor
              ? 'text-on-hero'
              : 'text-ink-soft',
    ),
);

/** The panel that holds the explanation: beside the page in the wide layout, from below otherwise. */
const container = computed(
    (): { is: Component; props: Record<string, unknown> } =>
        wide.value
            ? {
                  is: FmSidePanel,
                  props: { label: sideLabel.value, onClose: close },
              }
            : {
                  is: FmBottomSheet,
                  props: {
                      open: open.value,
                      title: info.value.title,
                      hideTitle: true,
                      'onUpdate:open': (next: boolean) => {
                          open.value = next;
                      },
                  },
              },
);

/** Closes the explanation. */
function close(): void {
    open.value = false;
}

/**
 * The text of a step around its link: before and after `:link`.
 *
 * @param step - the step
 * @returns the text before the link and the one after
 */
function around(step: PageInfoStep): [string, string] {
    const [before = '', after = ''] = step.text.split(':link');

    return [before, after];
}

/**
 * The text a step shows to copy: the prop of the page it names, when it is a text.
 *
 * @param step - the step
 * @returns the text, or null
 */
function copyText(step: PageInfoStep): string | null {
    if (step.copy === undefined) {
        return null;
    }

    const value: unknown = (page.props as Record<string, unknown>)[step.copy];

    return typeof value === 'string' && value !== '' ? value : null;
}
</script>

<template>
    <button
        type="button"
        :aria-label="$t('What is this page')"
        aria-haspopup="dialog"
        :aria-expanded="open"
        class="-m-1.5 inline-flex size-11 shrink-0 items-center justify-center"
        @click="open = !open"
    >
        <span :class="circle">
            <FmIcon :icon="Info" :size="20" />
        </span>
    </button>

    <component
        :is="container.is"
        v-if="wide ? open : opened"
        v-bind="container.props"
    >
        <div
            class="flex flex-col gap-4 text-ink"
            :class="wide && 'bg-card px-5 pt-4.5 pb-5.5'"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-action-soft text-action-dark"
                >
                    <FmIcon :icon="Info" :size="22" />
                </span>
                <!-- From below the panel already has the title for screen readers -->
                <h2
                    class="m-0 min-w-0 flex-1 text-sheet-title"
                    :aria-hidden="wide ? undefined : 'true'"
                >
                    {{ info.title }}
                </h2>
                <FmButton
                    v-if="wide"
                    variant="neutral"
                    small
                    :icon="X"
                    :label="$t('Close')"
                    @click="close"
                />
                <button
                    v-else
                    type="button"
                    :aria-label="$t('Close')"
                    class="-mr-2.5 flex size-11 shrink-0 items-center justify-center text-ink-soft"
                    @click="close"
                >
                    <FmIcon :icon="X" :size="22" />
                </button>
            </div>

            <section class="flex flex-col gap-1.5">
                <h3 class="m-0 text-row">{{ $t('What it is for') }}</h3>
                <p class="m-0 text-body-sm">{{ info.purpose }}</p>
            </section>

            <section class="flex flex-col gap-2.5">
                <h3 class="m-0 text-row">{{ $t('How to use it') }}</h3>
                <ol class="m-0 flex list-none flex-col gap-3 p-0">
                    <li
                        v-for="(step, index) in info.steps"
                        :key="step.text"
                        class="flex items-start gap-3 text-body-sm"
                    >
                        <span
                            aria-hidden="true"
                            class="flex size-6.5 shrink-0 items-center justify-center rounded-full bg-action-soft text-meta-strong text-action-dark"
                            >{{ index + 1 }}</span
                        >
                        <span
                            class="flex min-w-0 flex-1 flex-col gap-2 pt-0.75"
                        >
                            <span v-if="step.link"
                                >{{ around(step)[0]
                                }}<Link
                                    :href="step.link.href()"
                                    class="text-label text-action no-underline hover:text-action-dark"
                                    >{{ step.link.label }}</Link
                                >{{ around(step)[1] }}</span
                            >
                            <span v-else>{{ step.text }}</span>
                            <span
                                v-if="copyText(step)"
                                class="flex items-center gap-2 rounded-md border border-edge-soft bg-canvas py-1 pr-1 pl-3"
                            >
                                <span
                                    class="min-w-0 flex-1 text-label wrap-anywhere"
                                    >{{ copyText(step) }}</span
                                >
                                <button
                                    type="button"
                                    :aria-label="
                                        $t('Copy the address :address', {
                                            address: copyText(step) ?? '',
                                        })
                                    "
                                    class="flex size-9 shrink-0 items-center justify-center rounded-tag bg-card text-action"
                                    @click="copier.copy(copyText(step) ?? '')"
                                >
                                    <FmIcon
                                        :icon="
                                            copier.copied.value ? Check : Copy
                                        "
                                        :size="18"
                                    />
                                </button>
                            </span>
                        </span>
                    </li>
                </ol>
            </section>

            <section
                v-if="info.notes"
                class="flex flex-col gap-1.5 rounded-lg bg-canvas px-3.5 py-3"
            >
                <h3 class="m-0 text-row">{{ $t('Good to know') }}</h3>
                <ul
                    class="m-0 flex list-disc flex-col gap-1 pl-4.5 text-body-sm text-ink-soft"
                >
                    <li v-for="note in info.notes" :key="note">{{ note }}</li>
                </ul>
            </section>

            <div
                v-if="wide"
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <FmButton
                    v-if="info.link"
                    variant="text"
                    :href="info.link.href()"
                    >{{ info.link.label }}</FmButton
                >
                <FmButton class="ml-auto" @click="close">{{
                    $t('Got it')
                }}</FmButton>
            </div>
            <div v-else class="flex flex-col gap-1">
                <FmButton wide @click="close">{{ $t('Got it') }}</FmButton>
                <FmButton
                    v-if="info.link"
                    variant="text"
                    wide
                    :href="info.link.href()"
                    >{{ info.link.label }}</FmButton
                >
            </div>
        </div>
    </component>
</template>
