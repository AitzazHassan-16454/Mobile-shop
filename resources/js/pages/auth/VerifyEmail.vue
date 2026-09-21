<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 rounded-xl border border-[#003B7D]/20 bg-[#003B7D]/5 px-4 py-3 text-center text-sm font-medium text-[#003B7D]"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button
            :disabled="processing"
            class="rounded-md border-2 border-transparent bg-[#003B7D] px-3 py-1.5 text-sm font-bold text-white transition-colors duration-150 hover:bg-[#002b5c] disabled:opacity-70 lg:text-base"
        >
            <Spinner v-if="processing" />
            Resend verification email
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
