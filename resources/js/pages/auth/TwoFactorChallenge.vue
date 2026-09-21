<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'login using an authentication code',
        };
    }

    return {
        title: 'Authentication code',
        description:
            'Enter the authentication code provided by your authenticator application.',
        buttonText: 'login using a recovery code',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                            class="[&_[data-slot='input-otp-slot']]:border-gray-200 [&_[data-slot='input-otp-slot']]:bg-gray-50"
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <Button
                    type="submit"
                    class="w-full rounded-md border-2 border-transparent bg-[#003B7D] px-3 py-1.5 text-sm font-bold text-white transition-colors duration-150 hover:bg-[#002b5c] disabled:opacity-70 lg:text-base"
                    :disabled="processing"
                    >Continue</Button
                >
                <div class="text-muted-foreground text-center text-sm">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="text-[#003B7D] underline decoration-[#003B7D]/40 underline-offset-4 transition-colors duration-300 ease-out hover:text-[#003B7D] hover:decoration-[#003B7D]/40"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    placeholder="Enter recovery code"
                    :autofocus="showRecoveryInput"
                    required
                    class="h-12 border-gray-200 bg-gray-50 text-gray-900 placeholder:text-slate-500 focus-visible:border-[#003B7D]/60 focus-visible:ring-[#003B7D]/20"
                />
                <InputError :message="errors.recovery_code" />
                <Button
                    type="submit"
                    class="w-full rounded-md border-2 border-transparent bg-[#003B7D] px-3 py-1.5 text-sm font-bold text-white transition-colors duration-150 hover:bg-[#002b5c] disabled:opacity-70 lg:text-base"
                    :disabled="processing"
                    >Continue</Button
                >

                <div class="text-muted-foreground text-center text-sm">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="text-[#003B7D] underline decoration-[#003B7D]/40 underline-offset-4 transition-colors duration-300 ease-out hover:text-[#003B7D] hover:decoration-[#003B7D]/40"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
