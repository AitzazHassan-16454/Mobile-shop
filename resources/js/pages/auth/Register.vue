<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Set up your staff or shop management account',
    },
});

defineProps<{
    teamInvitation?: TeamInvitationContext | null;
}>();
</script>

<template>
    <Head title="Register" />

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Register"
    />

    <Form
        action="/register"
        method="post"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label for="name" class="text-sm font-medium text-slate-600">
                    Username / Full Name
                </Label>
                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    placeholder="Enter your name"
                    class="h-12 border-gray-300 text-gray-800 focus-visible:border-gray-500 focus-visible:ring-gray-300/60"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-sm font-medium text-slate-600">
                    Email Address
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    placeholder="name@example.com"
                    class="h-12 border-gray-300 text-gray-800 focus-visible:border-gray-500 focus-visible:ring-gray-300/60"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password"
                    class="text-sm font-medium text-slate-600"
                >
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    placeholder="Create a strong password"
                    class="h-12 border-gray-300 text-gray-800 focus-visible:border-gray-500 focus-visible:ring-gray-300/60 [[&_button]_button]:text-gray-500"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password_confirmation"
                    class="text-sm font-medium text-slate-600"
                >
                    Confirm Password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    placeholder="Confirm your password"
                    class="h-12 border-gray-300 text-gray-800 focus-visible:border-gray-500 focus-visible:ring-gray-300/60 [[&_button]_button]:text-gray-500"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 block w-full rounded-md border-2 border-transparent bg-[#003B7D] px-3 py-1.5 text-sm font-bold text-white transition-colors duration-150 hover:bg-[#002b5c] disabled:opacity-70 lg:text-base"
                :tabindex="5"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Creating account...' : 'Create Account' }}
            </Button>
        </div>

        <div class="text-center text-xs text-slate-600">
            Already have an account?
            <TextLink
                :href="login()"
                class="font-bold text-[#003B7D] hover:underline"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
