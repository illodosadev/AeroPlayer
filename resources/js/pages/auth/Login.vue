<script setup lang="ts">
import AeroButton from '@/components/aero/AeroButton.vue';
import AeroCheckbox from '@/components/aero/AeroCheckbox.vue';
import AeroInput from '@/components/aero/AeroInput.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <p v-if="status" class="mb-4 text-center text-sm font-medium text-[var(--aero-text)]">
            {{ status }}
        </p>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <AeroInput
                id="email"
                v-model="form.email"
                label="Email"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
                :error="form.errors.email"
            />

            <AeroInput
                id="password"
                v-model="form.password"
                label="Password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Password"
                :error="form.errors.password"
            />

            <AeroCheckbox id="remember" v-model="form.remember">Remember me</AeroCheckbox>

            <AeroButton type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                Log in
            </AeroButton>
        </form>
    </AuthBase>
</template>
