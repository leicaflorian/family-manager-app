<script setup lang="ts">
/**
 * The *Welcome* of a new family, in four steps: who you are (your birth date
 * and tax code, and the people of the family), the home, which can be left for
 * later, what you use (the switches of the Budget and of the mail for the
 * bills) and how it works. *Done, let's go in* at the last step saves
 * everything together and leads to the Home. On the computer the panel on the
 * left lists the steps, with a tick on those done.
 */
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    FileText,
    Info,
    LayoutGrid,
    Lock,
    Mail,
    ScanLine,
    Upload,
    UserPlus,
    Wallet,
    X,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmHeader from '@/components/fm/FmHeader.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import { useAppName } from '@/composables/useAppName';
import { useComputerLayout } from '@/composables/useLayoutMode';
import { errors } from '@/lib/errors';
import { cn } from '@/lib/utils';
import { store } from '@/routes/welcome';

const { me, roles, features } = defineProps<{
    /** The person of whoever signed in, or null when the account has none. */
    me: App.Data.PersonDetailData | null;
    /** Who a person can be in the family. */
    roles: App.Data.OptionData[];
    /** The switches of the family, and whether whoever is looking can change them. */
    features: App.Data.FeatureSettingsData;
}>();

/** The name of the app, for the sentence of the third step. */
const appName = useAppName();

/** The buttons sit in a row on the computer, one under the other elsewhere. */
const computer = useComputerLayout();

/** A person to add, as written in the form; `key` only tells the rows apart on the page and is not sent. */
type NewPerson = {
    key: number;
    first_name: string;
    last_name: string;
    role: string;
    birth_date: string;
    tax_code: string;
};

/** The four steps, with the title and the sentence of each. */
const steps = [
    {
        title: trans('Who you are'),
        sentence: trans(
            'Who lives with you? Add the people of the family: you can invite them to join later too.',
        ),
    },
    {
        title: trans('The home'),
        sentence: trans(
            'Where do you live? The bills, the contracts and the documents are linked to the home.',
        ),
    },
    {
        title: trans('What you use'),
        sentence: trans(
            ':app keeps documents, cards and deadlines. The rest you turn on only if you need it.',
            { app: appName.value },
        ),
    },
    {
        title: trans('How it works'),
        sentence: trans('Four things to know to begin.'),
    },
] as const;

/** The index of a step. */
type Step = 0 | 1 | 2 | 3;

const step = ref<Step>(0);
const current = computed(() => steps[step.value]);

// The panel of the computer follows the step; the people and the switches need a wider card than the home
watch(
    step,
    (index) => {
        setLayoutProps({
            panel: {
                title: trans('Everything about the family, in one place.'),
                text: trans(
                    'Four steps and your family is ready. You can change everything later too.',
                ),
                steps: steps.map((s) => s.title),
                step: index,
            },
            width: index === 1 ? 'narrow' : index === 3 ? 'wider' : 'wide',
        });
    },
    { immediate: true },
);

const form = useForm({
    me: { birth_date: me?.birth_date ?? '', tax_code: me?.tax_code ?? '' },
    people: [] as NewPerson[],
    home: { name: trans('House'), address: '', city: '', is_owned: false },
    features: { budget: features.budget, mail: features.mail },
});

/** True once «I'll add it later» was chosen at the second step: the home is not sent. */
const withoutHome = ref(false);

/** A row of the lists of the third and fourth step: the icon on its coloured tile, the name and the sentence. */
type Tile = { icon: LucideIcon; tile: string; title: string; text: string };

/** What is always there, at the third step. */
const alwaysThere: Tile[] = [
    {
        icon: FileText,
        tile: cn('bg-general-soft text-general-text'),
        title: trans('Documents'),
        text: trans('Upload and find all the papers'),
    },
    {
        icon: LayoutGrid,
        tile: cn('bg-home-soft text-home-text'),
        title: trans('Cards'),
        text: trans('Home, car, policies, health…'),
    },
    {
        icon: CalendarDays,
        tile: cn('bg-action-soft text-action-dark'),
        title: trans('Agenda'),
        text: trans('Deadlines and reminders'),
    },
];

/** The two switches of the third step. */
const switches: (Tile & { key: 'budget' | 'mail' })[] = [
    {
        key: 'budget',
        icon: Wallet,
        tile: cn('bg-expenses-soft text-expenses-text'),
        title: trans('Budget'),
        text: trans(
            "Expenses, income and deadlines to pay, month by month. If you don't use it, the app doesn't talk about money.",
        ),
    },
    {
        key: 'mail',
        icon: Mail,
        tile: cn('bg-paperwork-soft text-paperwork-text'),
        title: trans('Mail for the bills'),
        text: trans(
            'An email address of the family: the bills you forward to it arrive here and we read them.',
        ),
    },
];

/**
 * The four things to know of the last step. The last one is the mail for the
 * bills when it is switched on at the third step, the scan from the phone
 * otherwise.
 */
const howItWorks = computed<Tile[]>(() => [
    {
        icon: Upload,
        tile: cn('bg-action-soft text-action-dark'),
        title: trans('Upload a document'),
        text: trans(
            'A photo, a PDF, from the phone or from the scanner: we read it.',
        ),
    },
    {
        icon: LayoutGrid,
        tile: cn('bg-home-soft text-home-text'),
        title: trans('The cards'),
        text: trans('Cars, policies, home: the dates become reminders.'),
    },
    {
        icon: CalendarDays,
        tile: cn('bg-insurance-soft text-insurance-text'),
        title: trans('The agenda'),
        text: trans('Deadlines and reminders, on your calendar too.'),
    },
    form.features.mail
        ? {
              icon: Mail,
              tile: cn('bg-paperwork-soft text-paperwork-text'),
              title: trans('Mail for the bills'),
              text: trans(
                  'Forward the bills that arrive by email: we read them',
              ),
          }
        : {
              icon: ScanLine,
              tile: cn('bg-general-soft text-general-text'),
              title: trans('Scan from your phone'),
              text: trans(
                  'Link the phone and take photos of the documents: they arrive already straightened.',
              ),
          },
]);

/** The sentence about the «i» beside the titles, split where the icon goes. */
const infoSentence = computed(() =>
    trans(
        'On every page you find the :icon beside the title: it tells you what you can do there.',
    ).split(':icon'),
);

/** The key of the next row added: a removed row never gives its key to another one. */
let nextKey = 0;

/**
 * Adds a row for a person, with the surname of the family already written.
 */
function addPerson(): void {
    form.people.push({
        key: nextKey++,
        first_name: '',
        last_name: me?.last_name ?? '',
        role: roles[0]?.value ?? '',
        birth_date: '',
        tax_code: '',
    });
}

/**
 * Goes to the next step; from the home, `later` leaves it out.
 *
 * @param later - true for «I'll add it later»
 */
function next(later = false): void {
    if (step.value === 1) {
        withoutHome.value = later;
    }
    step.value = Math.min(step.value + 1, 3) as Step;
}

/**
 * Goes back one step.
 */
function back(): void {
    step.value = Math.max(step.value - 1, 0) as Step;
}

/**
 * Saves everything and leads to the Home. An error on a person or on the home
 * brings back its step.
 */
function done(): void {
    form.transform((data) => ({
        ...data,
        me: me === null ? null : data.me,
        people: data.people.map(({ key: _key, ...person }) => person),
        home: withoutHome.value ? null : data.home,
        features: features.can_change ? data.features : null,
    })).submit(store(), {
        onError: (fieldErrors) => {
            const fields = Object.keys(fieldErrors);
            if (
                fields.some((f) => f.startsWith('people') || f.startsWith('me'))
            ) {
                step.value = 0;
            } else if (fields.some((f) => f.startsWith('home'))) {
                step.value = 1;
            }
        },
    });
}
</script>

<template>
    <Head :title="$t('Welcome')" />

    <form
        class="flex grow flex-col gap-5"
        novalidate
        @submit.prevent="step === 3 ? done() : next()"
    >
        <div class="flex flex-col gap-2.5">
            <span class="text-label text-ink-soft">{{
                $t('Step :step of :total', {
                    step: String(step + 1),
                    total: String(steps.length),
                })
            }}</span>
            <div class="grid grid-cols-4 gap-1.5" aria-hidden="true">
                <span
                    v-for="(s, i) in steps"
                    :key="s.title"
                    class="h-1.5 rounded-full"
                    :class="i <= step ? 'bg-action' : 'bg-divider'"
                />
            </div>
        </div>
        <FmHeader :title="current.title" :subtitle="current.sentence" />

        <div v-if="step === 0" class="flex flex-col gap-3">
            <fieldset
                v-if="me"
                class="m-0 flex flex-col gap-3 rounded-card border border-edge-soft p-4"
            >
                <legend class="float-left flex w-full flex-col gap-0.5 p-0">
                    <span class="text-row"
                        >{{ me.first_name }} {{ me.last_name }}</span
                    >
                    <span class="text-meta text-ink-soft">{{
                        $t('You · :role', { role: me.role_label })
                    }}</span>
                </legend>
                <div class="grid gap-3 computer:grid-cols-2">
                    <FormKit
                        v-model="form.me.birth_date"
                        type="datepicker"
                        :label="$t('Date of birth')"
                        optional
                        name="me.birth_date"
                        :errors="errors(form.errors['me.birth_date'])"
                    />
                    <FormKit
                        v-model="form.me.tax_code"
                        type="text"
                        :label="$t('Tax code')"
                        optional
                        name="me.tax_code"
                        autocomplete="off"
                        :placeholder="$t('16 letters and digits')"
                        :errors="errors(form.errors['me.tax_code'])"
                    />
                </div>
                <span class="text-meta text-ink-soft">{{
                    $t(
                        'They help to recognise the person in the documents. You can leave them empty.',
                    )
                }}</span>
            </fieldset>
            <fieldset
                v-for="(person, i) in form.people"
                :key="person.key"
                class="m-0 flex flex-col gap-3 rounded-card border-[1.5px] border-edge p-4"
            >
                <legend class="sr-only">
                    {{ $t('Person :number', { number: String(i + 1) }) }}
                </legend>
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="person.first_name"
                        type="text"
                        :label="$t('First name')"
                        :name="`people.${i}.first_name`"
                        :errors="errors(form.errors[`people.${i}.first_name`])"
                    />
                    <FormKit
                        v-model="person.last_name"
                        type="text"
                        :label="$t('Last name')"
                        :name="`people.${i}.last_name`"
                        :errors="errors(form.errors[`people.${i}.last_name`])"
                    />
                </div>
                <FormKit
                    v-model="person.role"
                    type="dropdown"
                    :label="$t('In the family they are')"
                    :name="`people.${i}.role`"
                    :options="roles"
                    :errors="errors(form.errors[`people.${i}.role`])"
                />
                <div class="grid gap-3 computer:grid-cols-2">
                    <FormKit
                        v-model="person.birth_date"
                        type="datepicker"
                        :label="$t('Date of birth')"
                        optional
                        :name="`people.${i}.birth_date`"
                        :errors="errors(form.errors[`people.${i}.birth_date`])"
                    />
                    <FormKit
                        v-model="person.tax_code"
                        type="text"
                        :label="$t('Tax code')"
                        optional
                        :name="`people.${i}.tax_code`"
                        autocomplete="off"
                        :placeholder="$t('16 letters and digits')"
                        :errors="errors(form.errors[`people.${i}.tax_code`])"
                    />
                </div>
                <div class="flex">
                    <FmButton
                        variant="danger"
                        :icon="X"
                        @click="form.people.splice(i, 1)"
                        >{{ $t('Remove') }}</FmButton
                    >
                </div>
            </fieldset>
            <FmButton variant="soft" :icon="UserPlus" @click="addPerson">{{
                $t('Add a person')
            }}</FmButton>
        </div>

        <div v-else-if="step === 1" class="flex flex-col gap-3">
            <FormKit
                v-model="form.home.name"
                type="text"
                :label="$t('What you call it')"
                name="home.name"
                :errors="errors(form.errors['home.name'])"
            />
            <FormKit
                v-model="form.home.address"
                type="text"
                :label="$t('Address')"
                name="home.address"
                autocomplete="street-address"
                :errors="errors(form.errors['home.address'])"
            />
            <FormKit
                v-model="form.home.city"
                type="text"
                :label="$t('City')"
                name="home.city"
                autocomplete="address-level2"
                :errors="errors(form.errors['home.city'])"
            />
            <FormKit
                v-model="form.home.is_owned"
                type="toggle"
                :label="$t('It is your property')"
                name="home.is_owned"
            />
        </div>

        <template v-else-if="step === 2">
            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="m-0 text-label text-ink-soft">
                        {{ $t('Always there') }}
                    </h2>
                    <span
                        v-if="computer"
                        class="shrink-0 rounded-tag bg-card-sunken px-2 py-0.5 text-badge whitespace-nowrap text-ink-soft"
                        >{{ $t('Always on') }}</span
                    >
                </div>
                <ul
                    class="m-0 flex list-none flex-col p-0 computer:grid computer:grid-cols-2 computer:gap-x-5"
                >
                    <li
                        v-for="(item, i) in alwaysThere"
                        :key="item.title"
                        class="flex items-center gap-3 py-2.5"
                        :class="{
                            'border-t border-divider': i > 0 && !computer,
                        }"
                    >
                        <span
                            :class="[
                                'flex size-9 shrink-0 items-center justify-center rounded-md',
                                item.tile,
                            ]"
                        >
                            <FmIcon :icon="item.icon" :size="20" />
                        </span>
                        <span class="flex min-w-0 grow flex-col gap-0.5">
                            <span class="text-row">{{ item.title }}</span>
                            <span class="text-meta text-ink-soft">{{
                                item.text
                            }}</span>
                        </span>
                        <span
                            v-if="!computer"
                            class="shrink-0 rounded-tag bg-card-sunken px-2 py-0.5 text-badge whitespace-nowrap text-ink-soft"
                            >{{ $t('Always on') }}</span
                        >
                    </li>
                </ul>
            </div>
            <div class="flex flex-col gap-2">
                <h2 class="m-0 text-label text-ink-soft">
                    {{ $t('Turn it on if you need it') }}
                </h2>
                <FmNotice v-if="!features.can_change" :icon="Lock">{{
                    $t('Only whoever manages the family can change them.')
                }}</FmNotice>
                <div
                    v-for="item in switches"
                    :key="item.key"
                    class="flex flex-col gap-2.5 rounded-card border-[1.5px] border-edge p-4"
                >
                    <div class="flex items-center gap-3">
                        <span
                            :class="[
                                'flex size-9 shrink-0 items-center justify-center rounded-md',
                                item.tile,
                            ]"
                        >
                            <FmIcon :icon="item.icon" :size="20" />
                        </span>
                        <span class="grow text-body-strong">{{
                            item.title
                        }}</span>
                        <FormKit
                            v-model="form.features[item.key]"
                            type="toggle"
                            :name="`features.${item.key}`"
                            :aria-label="item.title"
                            :disabled="!features.can_change"
                        />
                    </div>
                    <span class="text-body-sm text-ink-soft">{{
                        item.text
                    }}</span>
                </div>
                <span class="text-meta text-ink-soft">{{
                    $t(
                        'You can turn them on or off whenever you like from Settings › What you use.',
                    )
                }}</span>
            </div>
        </template>

        <template v-else>
            <ul
                class="m-0 flex list-none flex-col gap-2.5 p-0 computer:grid computer:grid-cols-2"
            >
                <li
                    v-for="item in howItWorks"
                    :key="item.title"
                    class="flex items-start gap-3 rounded-card border border-edge-soft p-3.5"
                >
                    <span
                        :class="[
                            'flex size-9 shrink-0 items-center justify-center rounded-md',
                            item.tile,
                        ]"
                    >
                        <FmIcon :icon="item.icon" :size="20" />
                    </span>
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-row">{{ item.title }}</span>
                        <span class="text-body-sm text-ink-soft">{{
                            item.text
                        }}</span>
                    </span>
                </li>
            </ul>
            <p
                class="m-0 flex items-start gap-2.5 rounded-lg bg-action-soft px-3.5 py-3 text-body-sm text-ink"
            >
                <span class="flex shrink-0 text-action-dark">
                    <FmIcon :icon="Info" :size="20" />
                </span>
                <span
                    >{{ infoSentence[0]
                    }}<span
                        class="inline-flex align-text-bottom text-action-dark"
                        ><FmIcon :icon="Info" :size="16" /><span class="sr-only"
                            >i</span
                        ></span
                    >{{ infoSentence[1] }}</span
                >
            </p>
        </template>

        <span class="min-h-2 grow" />
        <div
            class="flex flex-col gap-1 computer:flex-row-reverse computer:flex-wrap computer:items-center computer:justify-between computer:gap-3"
        >
            <div
                class="flex flex-col gap-1 computer:flex-row-reverse computer:gap-2"
            >
                <FmButton
                    v-if="step === 3"
                    type="submit"
                    :wide="!computer"
                    :disabled="form.processing"
                    >{{ $t("Done, let's go in") }}</FmButton
                >
                <FmButton v-else type="submit" :wide="!computer">{{
                    $t('Next')
                }}</FmButton>
                <FmButton
                    v-if="step === 1"
                    variant="text"
                    @click="next(true)"
                    >{{ $t("I'll add it later") }}</FmButton
                >
            </div>
            <FmButton v-if="step > 0" variant="text" @click="back">{{
                $t('Back')
            }}</FmButton>
        </div>
    </form>
</template>
