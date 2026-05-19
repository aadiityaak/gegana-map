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
        class="mb-4 rounded border border-green-500/25 bg-green-500/10 p-3 text-center font-mono text-sm font-medium text-green-200"
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
                <Label for="email" class="text-green-300/80">> user_id:</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="user@system.local"
                    class="border-green-500/25 bg-black/40 text-green-200 placeholder-green-500/40 focus-visible:border-green-400 focus-visible:ring-green-400/35 focus-visible:ring-[3px]"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-green-300/80">> password:</Label>
                    <Link
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs text-green-300/70 underline decoration-green-500/30 underline-offset-4 transition-colors hover:text-green-200 hover:decoration-green-400/60"
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
                    class="border-green-500/25 bg-black/40 text-green-200 placeholder-green-500/40 focus-visible:border-green-400 focus-visible:ring-green-400/35 focus-visible:ring-[3px]"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3 text-green-300/70">
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="border-green-500/35 data-[state=checked]:border-green-400 data-[state=checked]:bg-green-500/20 data-[state=checked]:text-green-200 focus-visible:border-green-400 focus-visible:ring-green-400/35 focus-visible:ring-[3px]"
                    />
                    <span>> maintain_session</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full border border-green-500/35 bg-green-500/10 text-green-200 shadow-[0_0_0_1px_rgba(34,197,94,0.08),0_0_22px_rgba(34,197,94,0.10)] transition-colors hover:border-green-400/60 hover:bg-green-500/15"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? '> AUTHENTICATING...' : '> EXECUTE LOGIN' }}
            </Button>
        </div>

        <div class="text-center text-sm text-green-300/60">
            > need_access_token?
            <Link
                :href="register()"
                :tabindex="5"
                class="ml-1 text-green-300 underline decoration-green-500/30 underline-offset-4 transition-colors hover:text-green-200 hover:decoration-green-400/60"
            >
                register_user
            </Link>
        </div>
    </Form>
</template>
