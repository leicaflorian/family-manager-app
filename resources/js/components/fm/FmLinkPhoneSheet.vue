<script setup lang="ts">
/**
 * The panel *Link the phone*, opened from the computer: the QR to frame with the
 * phone camera, the three steps, the code to write when the QR cannot be framed
 * (it lasts 10 minutes), the phones already linked with *Unlink* (after a
 * question), and *Done*. Opening it makes a new code. While it is open it asks
 * the server every 2 seconds for the linked phones only, so the phone that has
 * just entered shows up as «Linked now»; it stops when the panel closes. During a
 * visit of the owner no code is made: the panel shows only the phones. Used by
 * *To sort* and by *My account*.
 *
 * @group panels
 */
import { Smartphone, Clock } from '@lucide/vue';
import { computed, ref } from 'vue';
import { usePhoneLink } from '@/composables/usePhoneLink';
import { useFormat } from '@/composables/useFormat';
import { deviceSummary } from '@/lib/linkedPhones';
import { trans } from 'laravel-vue-i18n';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmNotice from './FmNotice.vue';
import FmQrCode from './FmQrCode.vue';
import FmUnlinkPhone from './FmUnlinkPhone.vue';

/** Open or closed. */
const open = defineModel<boolean>('open', { default: false });

const { date } = useFormat();
const { link, failed, load } = usePhoneLink(() => open.value);

/** The phone the person is about to unlink, while the question is asked. */
const toUnlink = ref<App.Data.LinkedDeviceData | null>(null);

/** The marks that stand for the address and the code in the translated sentence. */
const ADDRESS = '%%address%%';
const CODE = '%%code%%';

/**
 * The sentence under the steps in pieces, so the address and the code can be in bold.
 * The code never breaks across two lines.
 */
const hint = computed(() =>
    trans(
        'The code is valid for 10 minutes. If you cannot frame it, open :address on the phone and write :code.',
        { address: ADDRESS, code: CODE },
    )
        .split(new RegExp(`(${ADDRESS}|${CODE})`))
        .map((text) => ({
            text:
                text === ADDRESS
                    ? (link.value?.address ?? '')
                    : text === CODE
                      ? (link.value?.code ?? '')
                      : text,
            strong: text === ADDRESS || text === CODE,
        })),
);
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="$t('Link the phone')" size="lg">
        <p class="m-0 text-[15px] leading-normal text-ink-soft">
            {{
                $t(
                    'Frame the code with the phone camera: Family Manager opens already with your account, without typing the password.',
                )
            }}
        </p>

        <FmNotice v-if="failed" tone="danger">
            {{ $t('The code could not be made.') }}
            <template #action>
                <FmButton variant="text" @click="load">{{
                    $t('Try again')
                }}</FmButton>
            </template>
        </FmNotice>

        <template v-else-if="link !== null">
            <template v-if="link.code !== null && link.qr !== null">
                <div class="flex flex-wrap items-center gap-7">
                    <FmQrCode
                        :svg="link.qr"
                        :label="$t('Code to link the phone')"
                    />
                    <ol
                        class="m-0 flex min-w-60 flex-1 list-none flex-col gap-3.5 p-0"
                    >
                        <li
                            v-for="(step, index) in [
                                $t('Open the phone camera and frame the code.'),
                                $t(
                                    'Tap the link that appears: you upload documents as :person, in the :family family.',
                                    {
                                        person: link.person,
                                        family: link.family,
                                    },
                                ),
                                $t(
                                    'Put it on the Home screen, so you find it like an app.',
                                ),
                            ]"
                            :key="index"
                            class="flex items-start gap-3 text-[15px] leading-snug"
                        >
                            <span
                                aria-hidden="true"
                                class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-action-soft text-[13px] font-extrabold text-action-dark"
                                >{{ index + 1 }}</span
                            >
                            <span>{{ step }}</span>
                        </li>
                    </ol>
                </div>

                <p
                    class="m-0 flex items-start gap-2.5 rounded-lg bg-canvas px-3.5 py-3 text-sm leading-snug text-ink-soft"
                >
                    <FmIcon :icon="Clock" :size="18" class="mt-px shrink-0" />
                    <span>
                        <template v-for="(piece, index) in hint" :key="index">
                            <strong
                                v-if="piece.strong"
                                class="whitespace-nowrap text-ink"
                                >{{ piece.text }}</strong
                            >
                            <template v-else>{{ piece.text }}</template>
                        </template>
                    </span>
                </p>
            </template>
            <p v-else class="m-0 text-[15px] leading-normal text-ink-soft">
                {{ $t('During a visit no code is made to link a phone.') }}
            </p>

            <section class="flex flex-col gap-2">
                <h3 class="m-0 text-sm font-bold">
                    {{ $t('Linked phones') }}
                </h3>
                <p
                    v-if="link.devices.length === 0"
                    class="m-0 text-sm text-ink-soft"
                >
                    {{ $t('No phone is linked yet') }}
                </p>
                <ul v-else class="m-0 flex list-none flex-col gap-2 p-0">
                    <li
                        v-for="device in link.devices"
                        :key="device.id"
                        class="flex items-center gap-3 rounded-lg border border-divider px-3 py-2.5"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-canvas text-ink-soft"
                        >
                            <FmIcon :icon="Smartphone" :size="20" />
                        </span>
                        <span class="flex min-w-0 grow flex-col gap-0.5">
                            <span class="text-[15px] font-semibold">{{
                                device.name
                            }}</span>
                            <span class="text-[13px] text-ink-soft">{{
                                deviceSummary(device, date)
                            }}</span>
                        </span>
                        <button
                            type="button"
                            :aria-label="
                                $t('Unlink :name', { name: device.name })
                            "
                            class="cursor-pointer border-0 bg-transparent p-1 text-sm font-semibold text-overdue"
                            @click="toUnlink = device"
                        >
                            {{ $t('Unlink') }}
                        </button>
                    </li>
                </ul>
            </section>
        </template>

        <div class="flex justify-end">
            <FmButton @click="open = false">{{ $t('Done') }}</FmButton>
        </div>

        <FmUnlinkPhone
            :device="toUnlink"
            preserve-state
            @close="toUnlink = null"
        />
    </FmBottomSheet>
</template>
