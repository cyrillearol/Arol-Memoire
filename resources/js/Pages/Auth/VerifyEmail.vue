<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ status: String });
const form = useForm({});
const submit = () => form.post(route('verification.send'));
const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Vérification e-mail" />
        <h1 class="text-center text-4xl font-bold">Vérifier votre adresse e-mail</h1>
        <p class="mt-3 text-center text-slate-600">Cliquez sur le lien envoyé. Vous pouvez demander un nouveau lien si nécessaire.</p>
        <div v-if="verificationLinkSent" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">Un nouveau lien de vérification a été envoyé.</div>
        <form class="mt-7" @submit.prevent="submit">
            <PrimaryButton class="w-full" :disabled="form.processing">Renvoyer le lien</PrimaryButton>
            <Link :href="route('logout')" method="post" as="button" class="mt-5 block w-full text-center text-sm font-bold text-slate-600">Se déconnecter</Link>
        </form>
    </GuestLayout>
</template>
