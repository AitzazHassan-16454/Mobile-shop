<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { Team, User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    team?: Team | null;
    onDark?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    team: null,
    onDark: false,
});

const { getInitials } = useInitials();

const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);

const subtitleClass = computed(() =>
    props.onDark ? 'truncate text-xs text-blue-200' : 'truncate text-xs',
);
</script>

<template>
    <div
        class="flex w-full min-w-0 items-center gap-2.5 group-data-[collapsible=icon]:justify-center"
    >
        <Avatar
            class="h-8 w-8 shrink-0 overflow-hidden rounded-lg group-data-[collapsible=icon]:size-8"
        >
            <AvatarImage
                v-if="showAvatar"
                :src="user.avatar!"
                :alt="user.name"
            />
            <AvatarFallback
                class="rounded-lg bg-white/20 font-bold text-white backdrop-blur-md group-data-[collapsible=icon]:bg-white group-data-[collapsible=icon]:text-[#003B7D]"
            >
                {{ getInitials(user.name) }}
            </AvatarFallback>
        </Avatar>

        <div
            class="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden"
        >
            <span class="truncate font-medium">{{ user.name }}</span>
            <span v-if="team" :class="subtitleClass">{{ team.name }}</span>
            <span v-else-if="showEmail" :class="subtitleClass">{{
                user.email
            }}</span>
        </div>
    </div>
</template>
