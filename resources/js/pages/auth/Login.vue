<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Access your shop control center securely',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 rounded-xl border border-[#003b7d]/20 bg-[#003b7d]/5 px-4 py-3 text-center text-sm font-medium text-[#003b7d]"
    >
        {{ status }}
    </div>

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Log in"
    />

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name" class="text-sm font-medium text-slate-600"
                    >Username</Label
                >
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="admin"
                    class="h-12 border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003b7d]/60 focus-visible:ring-violet-200"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="text-sm font-medium text-slate-600"
                        >Password</Label
                    >
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm font-medium text-violet-600 transition-colors hover:text-violet-600"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                    class="h-12 border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003b7d]/60 focus-visible:ring-violet-200 placeholder:disabled:opacity-50 [[&_button]_button]:text-slate-500"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex items-center gap-3 text-sm text-slate-500"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="border-gray-200 data-[state=checked]:border-[#003b7d] data-[state=checked]:bg-[#003b7d]"
                    />
                    <span>Keep me signed in</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-xl bg-[#003b7d] font-semibold text-white shadow-sm transition-all hover:-translate-y-0.5 hover:brightness-110 disabled:opacity-70"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Opening workspace...' : 'Enter workspace' }}
            </Button>
        </div>

        <div
            class="flex items-center justify-center gap-2 text-xs text-slate-500"
        >
            <span class="h-1.5 w-1.5 rounded-full bg-[#003b7d] shadow-sm" />
            Secure shop access
        </div>
    </Form>
</template>
