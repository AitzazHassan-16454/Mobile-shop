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
        class="mb-4 rounded-xl border border-[#003B7D]/20 bg-[#003B7D]/5 px-4 py-3 text-center text-sm font-medium text-[#003B7D]"
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
                    placeholder="Username"
                    class="h-12 border-gray-300 bg-[#F7F7F7] text-gray-800 focus-visible:border-gray-500 focus-visible:ring-gray-300/60"
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
                        class="text-sm font-medium text-[#003B7D] transition-colors hover:text-[#003B7D]"
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
                    class="h-12 border-gray-300 bg-[#F7F7F7] text-gray-800 focus-visible:border-gray-500 focus-visible:ring-gray-300/60 [[&_button]_button]:text-gray-500"
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
                        class="border-gray-300 data-[state=checked]:border-[#003B7D] data-[state=checked]:bg-[#003B7D]"
                    />
                    <span>Keep me signed in</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 block w-full rounded-md border-2 border-transparent bg-[#003B7D] px-3 py-1.5 text-sm font-bold text-white transition-colors duration-150 hover:bg-[#002b5c] disabled:opacity-70 lg:text-base"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Opening workspace...' : 'Login' }}
            </Button>
        </div>

        <div
            class="flex items-center justify-center gap-2 text-xs text-gray-500"
        >
            <span class="h-1.5 w-1.5 rounded-full bg-[#003B7D] shadow-sm" />
            Secure shop access
        </div>
    </Form>
</template>
