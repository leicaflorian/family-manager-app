<script setup lang="ts">
/**
 * *My account*: first name, last name, email and photo; the password; the family
 * and the level; *Log out* and *Delete my account*. The only administrator of the
 * family cannot delete themselves, and the page tells them why.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, LogOut, Users } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import FmAvatar from '@/components/fm/FmAvatar.vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmPasswordField from '@/components/fm/FmPasswordField.vue';
import FmRow from '@/components/fm/FmRow.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { errors } from '@/lib/errors';
import { logout } from '@/routes';
import { destroy, password, photo, update } from '@/routes/account';

const { account } = defineProps<{
    /** My account. */
    account: App.Data.AccountData;
}>();

const details = useForm({
    first_name: account.first_name,
    last_name: account.last_name,
    email: account.email,
});

const newPhoto = useForm({ photo: null as File | null });
const photoPicker = useTemplateRef<HTMLInputElement>('photoPicker');

/**
 * Sends the photo just chosen.
 */
function uploadPhoto(): void {
    newPhoto.photo = photoPicker.value?.files?.[0] ?? null;

    if (newPhoto.photo !== null) {
        newPhoto.submit(photo(), { preserveScroll: true });
    }
}

const passwordSheet = ref(false);
const passwordChange = useForm({ current_password: '', password: '' });

/**
 * Changes the password and closes the panel.
 */
function changePassword(): void {
    passwordChange.submit(password(), {
        preserveScroll: true,
        onSuccess: () => (passwordSheet.value = false),
        onFinish: () => passwordChange.reset(),
    });
}

const deleteSheet = ref(false);
const deletion = useForm({ password: '' });

/**
 * Deletes the account: the server leads to Log in.
 */
function deleteAccount(): void {
    deletion.submit(destroy(), { onFinish: () => deletion.reset() });
}
</script>

<template>
    <Head :title="$t('My account')" />

    <div class="flex max-w-[720px] flex-col gap-5">
        <FmTitle
            :title="$t('My account')"
            :subtitle="
                $t(
                    'Yours only: the rest of the settings applies to the whole family',
                )
            "
        />

        <form
            novalidate
            @submit.prevent="details.submit(update(), { preserveScroll: true })"
        >
            <FmCard>
                <div class="flex items-center gap-4">
                    <FmAvatar
                        :initials="account.initials"
                        :photo="account.photo"
                        :name="`${account.first_name} ${account.last_name}`"
                        :size="64"
                    />
                    <div class="flex flex-col gap-1">
                        <span class="text-lg font-extrabold"
                            >{{ account.first_name }}
                            {{ account.last_name }}</span
                        >
                        <button
                            type="button"
                            class="cursor-pointer border-0 bg-transparent p-0 text-left text-sm font-semibold text-action hover:text-action-dark"
                            :disabled="newPhoto.processing"
                            @click="photoPicker?.click()"
                        >
                            {{ $t('Change the photo') }}
                        </button>
                        <input
                            ref="photoPicker"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="uploadPhoto"
                        />
                        <span
                            v-if="newPhoto.errors.photo"
                            class="text-[13px] text-overdue"
                            >{{ newPhoto.errors.photo }}</span
                        >
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <FormKit
                        v-model="details.first_name"
                        type="text"
                        :label="$t('First name')"
                        name="first_name"
                        autocomplete="given-name"
                        :errors="errors(details.errors.first_name)"
                    />
                    <FormKit
                        v-model="details.last_name"
                        type="text"
                        :label="$t('Last name')"
                        name="last_name"
                        autocomplete="family-name"
                        :errors="errors(details.errors.last_name)"
                    />
                </div>
                <FormKit
                    v-model="details.email"
                    type="email"
                    :label="$t('Email')"
                    name="email"
                    autocomplete="email"
                    :help="
                        $t(
                            'If you change it, we will send you an email to confirm it',
                        )
                    "
                    :errors="errors(details.errors.email)"
                />
                <div class="flex justify-end">
                    <FmButton type="submit" :disabled="details.processing">{{
                        $t('Save')
                    }}</FmButton>
                </div>
            </FmCard>
        </form>

        <FmCard :title="$t('Access')" list>
            <FmRow
                :title="$t('Password')"
                :subtitle="$t('At least 10 characters')"
                button
                @click="passwordSheet = true"
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-canvas text-[#4A5565]"
                    >
                        <FmIcon :icon="KeyRound" :size="22" />
                    </span>
                </template>
                <template #after>
                    <span class="text-[15px] font-semibold text-action">{{
                        $t('Change')
                    }}</span>
                </template>
            </FmRow>
        </FmCard>

        <FmCard :title="$t('The family')" list>
            <FmRow
                :title="$t(':family family', { family: account.family })"
                :subtitle="
                    account.is_admin
                        ? $t(
                              'You are an administrator: you can change the settings and invite',
                          )
                        : $t(
                              'You see everything and upload documents; an administrator changes the settings',
                          )
                "
            >
                <template #before>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-canvas text-[#4A5565]"
                    >
                        <FmIcon :icon="Users" :size="22" />
                    </span>
                </template>
            </FmRow>
        </FmCard>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <FmButton
                variant="outline"
                :icon="LogOut"
                @click="router.post(logout().url)"
                >{{ $t('Log out') }}</FmButton
            >
            <FmButton
                v-if="!account.is_last_admin"
                variant="danger"
                @click="deleteSheet = true"
                >{{ $t('Delete my account') }}</FmButton
            >
        </div>
        <p v-if="account.is_last_admin" class="m-0 text-sm text-ink-soft">
            {{
                $t(
                    'You are the only administrator of the family: to delete your account, first give the level «Everything, like you» to another person.',
                )
            }}
        </p>

        <FmBottomSheet
            v-model:open="passwordSheet"
            :title="$t('Change the password')"
        >
            <form
                class="flex flex-col gap-3"
                novalidate
                @submit.prevent="changePassword"
            >
                <FmPasswordField
                    v-model="passwordChange.current_password"
                    :label="$t('Current password')"
                    name="current_password"
                    autocomplete="current-password"
                    :errors="errors(passwordChange.errors.current_password)"
                />
                <FmPasswordField
                    v-model="passwordChange.password"
                    :label="$t('New password')"
                    name="password"
                    :help="$t('At least 10 characters')"
                    autocomplete="new-password"
                    :errors="errors(passwordChange.errors.password)"
                />
                <div
                    class="flex flex-col gap-1 pt-2 lg:flex-row-reverse lg:gap-2.5"
                >
                    <FmButton
                        type="submit"
                        :disabled="passwordChange.processing"
                        >{{ $t('Change the password') }}</FmButton
                    >
                    <FmButton variant="text" @click="passwordSheet = false">{{
                        $t('Cancel')
                    }}</FmButton>
                </div>
            </form>
        </FmBottomSheet>

        <FmBottomSheet
            v-model:open="deleteSheet"
            :title="$t('Delete my account')"
        >
            <form
                class="flex flex-col gap-3"
                novalidate
                @submit.prevent="deleteAccount"
            >
                <FmNotice tone="warning">
                    {{
                        $t(
                            'Your account disappears and cannot be recovered. Your person stays in the family, with their documents.',
                        )
                    }}
                </FmNotice>
                <FmPasswordField
                    v-model="deletion.password"
                    :label="$t('Your password')"
                    name="password"
                    autocomplete="current-password"
                    :errors="errors(deletion.errors.password)"
                />
                <div
                    class="flex flex-col gap-1 pt-2 lg:flex-row-reverse lg:gap-2.5"
                >
                    <FmButton
                        type="submit"
                        variant="danger"
                        :disabled="deletion.processing"
                        >{{ $t('Delete forever') }}</FmButton
                    >
                    <FmButton variant="text" @click="deleteSheet = false">{{
                        $t('Cancel')
                    }}</FmButton>
                </div>
            </form>
        </FmBottomSheet>
    </div>
</template>
