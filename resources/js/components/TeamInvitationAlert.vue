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
            class="border-[#003B7D]/20 bg-[#003B7D]/5 text-[#003B7D] [&>svg]:text-[#003B7D]"
        >
            <Info class="size-4" />
            <AlertDescription class="text-[#003B7D]">
                {{ action }} to join the "{{ invitation.teamName }}" team.
            </AlertDescription>
        </Alert>
    </div>
</template>
