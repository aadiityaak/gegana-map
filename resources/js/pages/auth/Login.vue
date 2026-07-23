<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: '> ACCESS_REQUIRED',
        description: '> authenticate_user_credentials...',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 rounded border border-sky-500/25 bg-sky-500/10 p-3 text-center font-mono text-sm font-medium text-sky-200"
    >
        > {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6 font-mono"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email" class="text-sky-300">> user_id:</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="user@system.local"
                    class="border-sky-500/25 bg-black/40 text-sky-200 placeholder-sky-500/40 focus-visible:border-sky-400 focus-visible:ring-sky-400/35 focus-visible:ring-[3px]"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-sky-300">> password:</Label>
                    <Link
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs text-sky-300 underline decoration-sky-500/30 underline-offset-4 transition-colors hover:text-sky-200 hover:decoration-sky-400/60"
                        :tabindex="5"
                    >
                        > recovery_mode?
                    </Link>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                    class="border-sky-500/25 bg-black/40 text-sky-200 placeholder-sky-500/40 focus-visible:border-sky-400 focus-visible:ring-sky-400/35 focus-visible:ring-[3px]"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3 text-sky-300">
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="border-sky-500/35 data-[state=checked]:border-sky-400 data-[state=checked]:bg-sky-500/20 data-[state=checked]:text-sky-200 focus-visible:border-sky-400 focus-visible:ring-sky-400/35 focus-visible:ring-[3px]"
                    />
                    <span>> maintain_session</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full border border-sky-500/35 bg-sky-500/10 text-sky-200 shadow-[0_0_0_1px_rgba(171, 213, 229, 0.08),0_0_22px_rgba(171, 213, 229, 0.10)] transition-colors hover:border-sky-400/60 hover:bg-sky-500/15"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? '> AUTHENTICATING...' : '> EXECUTE LOGIN' }}
            </Button>
        </div>

        <div class="text-center text-sm text-sky-300">
            > need_access_token?
            <Link
                :href="register()"
                :tabindex="5"
                class="ml-1 text-sky-300 underline decoration-sky-500/30 underline-offset-4 transition-colors hover:text-sky-200 hover:decoration-sky-400/60"
            >
                register_user
            </Link>
        </div>
    </Form>
</template>
