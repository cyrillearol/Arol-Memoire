<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout>
        <Head title="Nouveau mot de passe" />
        <h1 class="text-center text-4xl font-bold">Choisir un nouveau mot de passe</h1>
        <p class="mt-3 text-center text-slate-600">Utilisez un mot de passe solide et différent de vos autres services.</p>
        <form class="mt-7" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Adresse e-mail" />
                <TextInput id="email" type="email" class="mt-2" v-model="form.email" required autofocus autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div class="mt-5">
                <InputLabel for="password" value="Nouveau mot de passe" />
                <TextInput id="password" type="password" maxlength="15" class="mt-2" v-model="form.password" required autocomplete="new-password" />
                <p class="mt-2 text-xs text-slate-500">Entre 8 et 15 caractères.</p>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div class="mt-5">
                <InputLabel for="password_confirmation" value="Confirmer le mot de passe" />
                <TextInput id="password_confirmation" type="password" maxlength="15" class="mt-2" v-model="form.password_confirmation" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>
            <PrimaryButton class="mt-6 w-full" :disabled="form.processing">Enregistrer</PrimaryButton>
        </form>
    </GuestLayout>
</template>
