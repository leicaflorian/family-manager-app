<script setup lang="ts">
/**
 * Where the *Scan the sheets* page is with the agent of the scanner, when it is not connected: looking for it, the
 * question open on the computer, refused, closed, not installed (with the download and the steps for the system) or
 * on a device that has no agent (a phone, a tablet, Linux).
 */
import { Download, Play, RotateCcw } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import type { Phase } from '@/lib/scan/agent';
import { scan } from '@/routes';
import { agent as agentDownload } from '@/routes/scanner';

const { phase, system, origin, release, blocked, hasSides } = defineProps<{
    /** Where the page is with the agent (never `connected`). */
    phase: Exclude<Phase, 'connected'>;
    /** The system of the computer. */
    system: 'mac' | 'windows' | 'other';
    /** The address of this app, as the agent names it in its question. */
    origin: string;
    /**
     * The last version of the agent: `undefined` while it is asked for, `null` when GitHub does not answer, and
     * which systems it is built for.
     */
    release: App.Data.AgentReleaseData | null | undefined;
    /** Chrome was told not to let this site reach the programs of the computer. */
    blocked: boolean;
    /** Some sides are already in the page. */
    hasSides: boolean;
}>();

const emit = defineEmits<{
    /** *Try again*, or *Start the agent*: the page looks for the agent again. */
    retry: [];
}>();

/** Where the icon of the agent is, for the computer in use. */
const iconPlace = (): string =>
    system === 'windows'
        ? trans('next to the clock')
        : trans('in the menu bar');
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-6">
        <FmNotice v-if="phase === 'unsupported'" tone="info">
            {{
                $t(
                    'The scanner is used from a Mac or a Windows PC, the one it is plugged into: it cannot be driven from here. To scan a sheet with the camera use the Scan page, from the phone.',
                )
            }}
            <template #action>
                <FmButton variant="text" :href="scan().url">{{
                    $t('Scan')
                }}</FmButton>
            </template>
        </FmNotice>

        <div
            v-else-if="phase === 'searching'"
            role="status"
            class="flex items-center gap-3 text-ink-soft"
        >
            {{ $t('Looking for the scanner agent…') }}
        </div>

        <FmNotice v-else-if="phase === 'pending'" tone="info">
            {{
                $t(
                    'On the computer the agent asks: «Allow :origin to use the scanner?». Answer Allow: the page goes on by itself. If you cannot see the question, it is behind the other windows.',
                    { origin },
                )
            }}
        </FmNotice>

        <template v-else-if="phase === 'denied'">
            <FmNotice tone="warning">
                {{
                    $t(
                        'The agent does not accept this site (:origin): its question was answered no. From the icon of the agent, :place, choose Forget the accepted sites, then press Try again.',
                        { origin, place: iconPlace() },
                    )
                }}
            </FmNotice>
            <div>
                <FmButton :icon="RotateCcw" @click="emit('retry')">{{
                    $t('Try again')
                }}</FmButton>
            </div>
        </template>

        <template v-else-if="phase === 'stopped'">
            <FmNotice tone="info">
                {{
                    $t(
                        'The scanner agent has closed: it does so by itself after ten minutes without requests.',
                    )
                }}
                <template v-if="hasSides">
                    {{ $t('The sides already scanned are still here.') }}
                </template>
            </FmNotice>
            <div>
                <FmButton :icon="Play" @click="emit('retry')">{{
                    $t('Start the agent')
                }}</FmButton>
            </div>
        </template>

        <template v-else-if="phase === 'missing'">
            <h2 class="m-0 text-stat">
                {{ $t('The scanner agent does not answer') }}
            </h2>
            <p class="m-0 text-ink-soft">
                {{
                    $t(
                        'To drive the scanner a small program is needed on this computer, Family Manager Agent. It is installed once; then it starts by itself when you open this page and closes after ten minutes of not being used.',
                    )
                }}
            </p>
            <FmNotice v-if="blocked" tone="danger">
                {{
                    $t(
                        'Chrome does not let this site reach the programs of the computer: the permission was denied. From the icon at the left of the address, allow access to the apps and devices of the local network, then press Try again.',
                    )
                }}
            </FmNotice>

            <FmNotice v-if="release === null" tone="warning">
                {{
                    $t(
                        'I cannot tell which is the last version of the agent to download: GitHub, where it is published, does not answer. Try again in a few minutes.',
                    )
                }}
            </FmNotice>
            <FmNotice
                v-else-if="
                    release &&
                    (system === 'mac' ? !release.mac : !release.windows)
                "
                tone="warning"
            >
                {{
                    $t(
                        'There is no version :version of the agent for :system.',
                        {
                            version: release.version,
                            system: system === 'mac' ? 'Mac' : 'Windows',
                        },
                    )
                }}
            </FmNotice>

            <div class="flex flex-wrap gap-2.5">
                <FmButton
                    v-if="
                        release &&
                        (system === 'mac' ? release.mac : release.windows)
                    "
                    :icon="Download"
                    download
                    :href="agentDownload(system).url"
                    >{{
                        $t(
                            'Download the agent for :system (version :version)',
                            {
                                system: system === 'mac' ? 'Mac' : 'Windows',
                                version: release.version,
                            },
                        )
                    }}</FmButton
                >
                <FmButton
                    variant="outline"
                    :icon="RotateCcw"
                    @click="emit('retry')"
                    >{{ $t('Try again') }}</FmButton
                >
            </div>

            <ol
                v-if="system === 'mac'"
                class="m-0 flex list-decimal flex-col gap-1.5 pl-5 text-ink"
            >
                <li>
                    {{
                        $t(
                            'Open the downloaded file: Family Manager Agent appears.',
                        )
                    }}
                </li>
                <li>
                    {{
                        $t('Move it into the Applications folder and open it.')
                    }}
                </li>
                <li>
                    {{
                        $t(
                            'The first time macOS refuses it, because it is not signed: go to System Settings → Privacy & Security and press Open Anyway.',
                        )
                    }}
                </li>
                <li>
                    {{
                        $t(
                            'Its icon appears in the menu bar. Come back here and press Try again.',
                        )
                    }}
                </li>
            </ol>
            <ol
                v-else
                class="m-0 flex list-decimal flex-col gap-1.5 pl-5 text-ink"
            >
                <li>
                    {{
                        $t(
                            'Open the downloaded file, then Family Manager Agent.',
                        )
                    }}
                </li>
                <li>
                    {{
                        $t(
                            'The first time Windows shows "Windows protected your PC", because it is not signed: press More info and then Run anyway.',
                        )
                    }}
                </li>
                <li>
                    {{
                        $t(
                            'Its icon appears next to the clock. Come back here and press Try again.',
                        )
                    }}
                </li>
            </ol>
            <p class="m-0 text-body-sm text-ink-soft">
                {{
                    $t(
                        'If the agent is already open (its icon is :place) and the page does not find it, the browser is not letting it call the programs of the computer: open this page in Chrome.',
                        { place: iconPlace() },
                    )
                }}
            </p>
        </template>
    </div>
</template>
