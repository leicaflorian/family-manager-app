<script setup lang="ts">
/**
 * The pages of the document in the scan, as small pages: a row on the phone, a column with the numbers on the
 * computer. The chosen page has the blue border and a tap chooses another; the dashed one at the end adds a page.
 * A page held for half a second is lifted and carried to its place, the others make room; a finger that moves
 * before then is scrolling the strip, and the phone taking the touch away (`pointercancel`) leaves the order as
 * it was. A mouse lifts the page by dragging it.
 *
 * @group files
 */
import { Plus } from '@lucide/vue';
import { cn } from '@/lib/utils';
import { onBeforeUnmount, ref } from 'vue';

/** A page of the strip. */
export type StripPage = {
    /** Identifies the page. */
    id: string;
    /** The small picture of the page; without it a sketch of a sheet is drawn. */
    url: string | null;
    /** How much the picture is turned, in degrees clockwise (the scanner turns a side only in the PDF). */
    rotation?: 0 | 90 | 180 | 270;
};

/** How long a finger stays on a page before the page is lifted, in milliseconds. */
const HOLD_MS = 500;
/** A finger that moves more than this, in pixels, before then is scrolling. */
const HOLD_SLACK = 10;
/** A page carried this close to an end of the strip makes the strip scroll under it. */
const EDGE = 28;
/** The time after a carried page is put down in which a tap on it chooses nothing, in milliseconds. */
const QUIET_MS = 300;

const props = withDefaults(
    defineProps<{
        /** The pages, in order. */
        pages: StripPage[];
        /** The id of the chosen page. */
        selected: string | null;
        /** `row` on the phone, `column` on the computer. */
        orientation?: 'row' | 'column';
    }>(),
    { orientation: 'row' },
);

const emit = defineEmits<{
    /** A page was chosen. */
    select: [id: string];
    /** A page was carried from a place to another (positions in the list). */
    move: [from: number, to: number];
    /** The dashed button was pressed. */
    add: [];
}>();

/** The page under the finger: waited for, or lifted and following it. */
type Hold = {
    from: number;
    pointer: number;
    x: number;
    y: number;
    start: number;
    at: number;
    mouse: boolean;
    lifted: boolean;
    step: number;
    scroll: number;
    timer: ReturnType<typeof setTimeout> | undefined;
    frame: number;
};

const nav = ref<HTMLElement | null>(null);
const items = ref<HTMLElement[]>([]);
/** Where the lifted page is, for the drawing: its place and the place it would be put down in. */
const lifted = ref<{ from: number; to: number; offset: number } | null>(null);

let hold: Hold | null = null;
let putDownAt = -Infinity;

/** Whether the strip runs along x (a row) or y (a column). */
const horizontal = (): boolean => props.orientation === 'row';

/** The position of a pointer event along the strip. */
const along = (event: MouseEvent): number =>
    horizontal() ? event.clientX : event.clientY;

/** Scroll position of the strip along its axis. */
const scrolled = (): number =>
    horizontal() ? (nav.value?.scrollLeft ?? 0) : (nav.value?.scrollTop ?? 0);

/** Letting go of the page under the finger, wherever it is: the strip is as it was drawn. */
function drop(): void {
    if (!hold) return;

    clearTimeout(hold.timer);
    cancelAnimationFrame(hold.frame);

    if (hold.lifted) {
        putDownAt = Date.now();
    }

    hold = null;
    lifted.value = null;
}

/** The lifted page follows the finger, and the others make room where it would be put down. */
function carry(): void {
    if (!hold || !hold.step) return;

    const { from, step } = hold;
    const last = props.pages.length - 1;
    const moved = Math.min(
        (last - from) * step,
        Math.max(-from * step, hold.at - hold.start + scrolled() - hold.scroll),
    );

    lifted.value = { from, to: from + Math.round(moved / step), offset: moved };
}

/** A page carried to an end of the strip makes it scroll, as long as it stays there. */
function scrollUnderCarried(): void {
    if (!hold || !hold.lifted || !nav.value) return;

    const box = nav.value.getBoundingClientRect();
    const near = horizontal() ? box.left : box.top;
    const far = horizontal() ? box.right : box.bottom;
    // only for a page that was carried there, not for one lifted where it already was
    const push =
        hold.at < near + EDGE && hold.at < hold.start - HOLD_SLACK
            ? -8
            : hold.at > far - EDGE && hold.at > hold.start + HOLD_SLACK
              ? 8
              : 0;

    if (push) {
        const before = scrolled();

        if (horizontal()) nav.value.scrollLeft += push;
        else nav.value.scrollTop += push;

        if (scrolled() !== before) carry();
    }

    hold.frame = requestAnimationFrame(scrollUnderCarried);
}

/** Lifts the page under the finger. */
function lift(): void {
    if (!hold) return;

    const first = items.value[0]?.getBoundingClientRect();
    const second = items.value[1]?.getBoundingClientRect();

    hold.lifted = true;
    hold.step =
        first && second
            ? horizontal()
                ? second.left - first.left
                : second.top - first.top
            : 0;
    hold.scroll = scrolled();

    try {
        // the page is followed outside the strip too
        items.value[hold.from]?.setPointerCapture?.(hold.pointer);
    } catch {
        // the finger has already left
    }

    carry();
    hold.frame = requestAnimationFrame(scrollUnderCarried);
}

/**
 * A pointer goes down on a page: the page is waited for (a touch) or ready to be dragged (a mouse).
 *
 * @param event - the pointer event
 * @param index - the position of the page
 */
function onDown(event: PointerEvent, index: number): void {
    if (hold || props.pages.length < 2) return;

    const mouse = event.pointerType === 'mouse';

    if (mouse && event.button !== 0) return;

    hold = {
        from: index,
        pointer: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        start: along(event),
        at: along(event),
        mouse,
        lifted: false,
        step: 0,
        scroll: 0,
        timer: mouse ? undefined : setTimeout(lift, HOLD_MS),
        frame: 0,
    };
}

/**
 * The pointer moves: a page lifted follows it; a finger that moves before the lift is scrolling.
 *
 * @param event - the pointer event
 */
function onMove(event: PointerEvent): void {
    if (!hold || event.pointerId !== hold.pointer) return;

    if (!hold.lifted) {
        if (
            Math.hypot(event.clientX - hold.x, event.clientY - hold.y) <=
            HOLD_SLACK
        )
            return;

        // a finger that moves before the page is lifted is scrolling the strip
        if (!hold.mouse) return drop();

        lift();
    }

    if (!hold) return;

    hold.at = along(event);
    carry();
}

/**
 * The pointer goes up or is taken away: a page put down moves, a cancelled one stays where it was.
 *
 * @param event - the pointer event
 */
function onEnd(event: PointerEvent): void {
    if (!hold || event.pointerId !== hold.pointer) return;

    const { from } = hold;
    const to = lifted.value?.to ?? from;
    const wasLifted = hold.lifted;

    drop();

    if (wasLifted && event.type === 'pointerup' && to !== from) {
        emit('move', from, to);
    }
}

/**
 * A tap on a page chooses it, unless the page was just carried.
 *
 * @param id - the page
 */
function choose(id: string): void {
    if (Date.now() - putDownAt < QUIET_MS) return;

    emit('select', id);
}

/**
 * The transform of a page while one is carried.
 *
 * @param index - the position of the page
 * @returns the style, empty for a page that stays
 */
function styleOf(index: number): Record<string, string> {
    const move = lifted.value;

    if (!move || !hold) return {};

    const axis = horizontal() ? 'translateX' : 'translateY';

    if (index === move.from) {
        return { transform: `${axis}(${move.offset}px) scale(1.06)` };
    }

    const shift =
        index > move.from && index <= move.to
            ? -hold.step
            : index < move.from && index >= move.to
              ? hold.step
              : 0;

    return shift ? { transform: `${axis}(${shift}px)` } : {};
}

/**
 * The pointer leaves the strip before the page was lifted: the page is let go.
 *
 * @param event - the pointer event
 */
function onLeave(event: PointerEvent): void {
    if (hold && !hold.lifted) onEnd(event);
}

/** The phone must not scroll under a page that is carried. */
function onTouchMove(event: TouchEvent): void {
    if (hold?.lifted && event.cancelable) event.preventDefault();
}

/** A long touch on a picture would open the menu of the phone: here it lifts the page. */
function onContextMenu(event: Event): void {
    event.preventDefault();
}

onBeforeUnmount(drop);

/**
 * The picture of a page turned as it was given; lying, it is made smaller so it stays in its frame.
 *
 * @param page - the page
 * @returns the style of its picture, empty when it is not turned
 */
function turnOf(page: StripPage): Record<string, string> {
    if (!page.rotation) return {};

    const lying = page.rotation === 90 || page.rotation === 270;

    return {
        transform: `rotate(${page.rotation}deg)${lying ? ' scale(0.74)' : ''}`,
    };
}

/** Classes of the page buttons, the chosen one with the blue border. */
const frame = (chosen: boolean): string =>
    cn(
        'flex rounded-md border-[3px] border-transparent',
        chosen && 'border-action',
    );
</script>

<template>
    <nav
        ref="nav"
        :aria-label="$t('The pages')"
        :class="
            cn(
                'flex gap-2.5 select-none',
                orientation === 'row'
                    ? 'flex-row items-start overflow-x-auto'
                    : 'flex-col items-center gap-3 overflow-y-auto',
                lifted && 'touch-none',
            )
        "
        @pointermove="onMove"
        @pointerup="onEnd"
        @pointercancel="onEnd"
        @pointerleave="onLeave"
        @touchmove="onTouchMove"
        @contextmenu="onContextMenu"
    >
        <button
            v-for="(page, index) in pages"
            :key="page.id"
            :ref="(el) => (items[index] = el as HTMLElement)"
            type="button"
            data-page
            :data-lifted="lifted?.from === index ? '' : undefined"
            :aria-label="$t('Page :number', { number: String(index + 1) })"
            :aria-current="page.id === selected ? 'true' : undefined"
            :style="styleOf(index)"
            :class="
                cn(
                    'flex shrink-0 cursor-pointer flex-col items-center gap-1 border-0 bg-transparent p-0 transition-transform',
                    lifted?.from === index &&
                        'relative z-10 cursor-grabbing shadow-float transition-none',
                )
            "
            @pointerdown="onDown($event, index)"
            @click="choose(page.id)"
        >
            <span :class="frame(page.id === selected)">
                <img
                    v-if="page.url"
                    :src="page.url"
                    alt=""
                    draggable="false"
                    :style="turnOf(page)"
                    class="h-[70px] w-[52px] rounded-sm border border-edge-soft bg-card object-cover"
                />
                <span
                    v-else
                    aria-hidden="true"
                    class="flex h-[70px] w-[52px] flex-col gap-0.5 overflow-hidden rounded-sm border border-edge-soft bg-card p-[5px]"
                >
                    <span class="h-[3px] w-5 rounded-xs bg-insurance-soft" />
                    <span class="h-0.5 w-8 rounded-xs bg-sketch" />
                    <span class="h-0.5 w-7 rounded-xs bg-sketch" />
                    <span class="mt-1 h-0.5 w-9 rounded-xs bg-sketch" />
                    <span class="h-0.5 w-8 rounded-xs bg-sketch" />
                    <span class="h-0.5 w-6 rounded-xs bg-sketch" />
                    <span
                        class="mt-auto h-[5px] w-[19px] self-end rounded-xs bg-sketch-accent"
                    />
                </span>
            </span>
            <span
                v-if="orientation === 'column'"
                aria-hidden="true"
                :class="
                    cn(
                        'text-meta text-ink-soft',
                        page.id === selected && 'text-meta-strong text-ink',
                    )
                "
                >{{ index + 1 }}</span
            >
        </button>
        <button
            type="button"
            :aria-label="$t('Add a page')"
            class="flex h-[76px] w-[58px] shrink-0 cursor-pointer items-center justify-center rounded-md border-2 border-dashed border-edge bg-card text-action"
            @click="emit('add')"
        >
            <Plus aria-hidden="true" :size="24" :stroke-width="2.5" />
        </button>
        <span
            v-if="orientation === 'column'"
            class="max-w-[100px] text-center text-caption text-ink-soft"
            >{{ $t('Drag to reorder') }}</span
        >
    </nav>
</template>
