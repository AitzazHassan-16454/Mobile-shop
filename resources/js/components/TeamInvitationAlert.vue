<script setup lang="ts">
import { Info } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'Log in' | 'Register';
};

defineProps<Props>();
</script>

<template>
    <div data-test="team-invitation-alert">
        <Alert
            class="border-[#003b7d]/20 bg-[#003b7d]/5 text-[#003b7d] [&>svg]:text-[#003b7d]"
        >
            <Info class="size-4" />
            <AlertDescription class="text-[#003b7d]">
                {{ action }} to join the "{{ invitation.teamName }}" team.
            </AlertDescription>
        </Alert>
    </div>
</template>
