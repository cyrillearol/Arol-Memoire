<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
const submit = () => form.post(route('password.confirm'), { onFinish: () => form.reset() });
</script>

<template>
    <GuestLayout>
        <Head title="Confirmation" />
        <h1 class="text-center text-4xl font-bold">Confirmer votre mot de passe</h1>
        <p class="mt-3 text-center text-slate-600">Cette action nécessite une vérification supplémentaire.</p>
        <form class="mt-7" @submit.prevent="submit">
            <InputLabel for="password" value="Mot de passe" />
            <TextInput id="password" type="password" maxlength="15" class="mt-2" v-model="form.password" required autocomplete="current-password" autofocus />
            <InputError class="mt-2" :message="form.errors.password" />
            <PrimaryButton class="mt-6 w-full" :disabled="form.processing">Confirmer</PrimaryButton>
        </form>
    </GuestLayout>
</template>
