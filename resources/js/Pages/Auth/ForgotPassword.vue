<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ status: String });

const form = useForm({ email: '' });
const submit = () => form.post(route('password.email'));
</script>

<template>
    <GuestLayout>
        <Head title="Mot de passe oublié" />
        <h1 class="text-center text-4xl font-bold">Réinitialiser le mot de passe</h1>
        <p class="mt-3 text-center text-slate-600">Indiquez votre adresse e-mail. Nous vous enverrons un lien de récupération.</p>
        <div v-if="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ status }}</div>
        <form class="mt-7" @submit.prevent="submit">
            <InputLabel for="email" value="Adresse e-mail" />
            <TextInput id="email" type="email" class="mt-2" v-model="form.email" required autofocus autocomplete="username" />
            <InputError class="mt-2" :message="form.errors.email" />
            <PrimaryButton class="mt-6 w-full" :disabled="form.processing">Envoyer le lien</PrimaryButton>
            <p class="mt-5 text-center text-sm"><Link :href="route('login')" class="font-bold text-[#9a6200]">Retour à la connexion</Link></p>
        </form>
    </GuestLayout>
</template>
