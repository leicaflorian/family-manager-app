<script setup lang="ts">
/**
 * The layout of the sign-in pages (Log in, Sign up, Forgot password…).
 * On the phone the page is white, with the logo at the top and the button at the
 * bottom. On the computer the blue panel is on the left and the form on the right
 * in a card, as in the *Log in* board.
 */
import { Link } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import FmHeader from '@/components/fm/FmHeader.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmLogo from '@/components/fm/FmLogo.vue';

/** What the blue panel of the computer says, already translated. */
export type AuthPanel = {
    /** The big sentence. */
    title: string;
    /** The text below. */
    text: string;
    /** The points at the bottom of the panel, with the tick. */
    points?: string[];
};

/** The line at the bottom that leads to the other page («Already have an account? Log in»). */
export type OtherWay = {
    /** The question. */
    text: string;
    /** The text of the link. */
    link: string;
    /** Where it leads. */
    href: string;
};

const {
    title = '',
    subtitle,
    panel = {
        title: trans('Everything about the family, in one place.'),
        text: '',
    },
    otherWay,
} = defineProps<{
    /** The title of the page. Without it, the page writes its own (for example with a name). */
    title?: string;
    /** The sentence below the title. */
    subtitle?: string;
    /** The blue panel of the computer. */
    panel?: AuthPanel;
    /** The line at the bottom. */
    otherWay?: OtherWay;
}>();

defineSlots<{
    /** The form of the page, with its button. */
    default: () => unknown;
}>();
</script>

<template>
    <div class="flex min-h-dvh bg-white text-ink lg:bg-canvas">
        <aside
            class="hidden w-[42%] max-w-[600px] flex-col justify-between gap-5 bg-linear-160 from-action to-action-dark p-12 text-white lg:flex"
        >
            <div class="flex items-center gap-3">
                <FmLogo white :size="44" />
                <span class="text-[22px] font-extrabold">Family Manager</span>
            </div>
            <div class="flex max-w-[420px] flex-col gap-3.5">
                <p class="m-0 text-[34px] leading-[1.15] font-extrabold">
                    {{ panel.title }}
                </p>
                <p v-if="panel.text" class="m-0 text-[17px] leading-normal">
                    {{ panel.text }}
                </p>
            </div>
            <ul
                v-if="panel.points?.length"
                class="m-0 flex list-none flex-col gap-2.5 p-0 text-base"
            >
                <li
                    v-for="point in panel.points"
                    :key="point"
                    class="flex items-center gap-2.5"
                >
                    <FmIcon :icon="CircleCheck" :size="22" />
                    {{ point }}
                </li>
            </ul>
            <span v-else />
        </aside>

        <main
            class="flex grow items-stretch justify-center lg:items-center lg:p-12"
        >
            <div
                class="flex w-full max-w-[480px] flex-col gap-[18px] px-5 pt-8 pb-7 lg:rounded-3xl lg:bg-white lg:p-9"
            >
                <div class="flex items-center gap-2.5 lg:hidden">
                    <FmLogo :size="36" />
                    <span class="text-[17px] font-extrabold"
                        >Family Manager</span
                    >
                </div>
                <FmHeader v-if="title" :title="title" :subtitle="subtitle" />
                <div class="flex grow flex-col">
                    <slot />
                </div>
                <p
                    v-if="otherWay"
                    class="m-0 text-center text-[15px] text-[#4A5565]"
                >
                    {{ otherWay.text }}
                    <Link
                        :href="otherWay.href"
                        class="font-semibold text-action hover:text-action-dark"
                        >{{ otherWay.link }}</Link
                    >
                </p>
            </div>
        </main>
    </div>
</template>
