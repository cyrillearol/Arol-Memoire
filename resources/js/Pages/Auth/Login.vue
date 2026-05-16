<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

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
    <GuestLayout>
        <Head title="Connexion" />

        <div class="text-center">
            <h1 class="text-4xl font-bold">Bon retour parmi nous</h1>
            <p class="mt-3 text-base leading-6 text-slate-600">Connectez-vous pour accéder à votre espace personnel.</p>
        </div>



        <div v-if="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ status }}
        </div>

        <form class="mt-7" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Adresse e-mail" />
                <TextInput id="email" type="email" class="mt-2" v-model="form.email" required autofocus autocomplete="username" placeholder="nom@exemple.com" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-5">
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Mot de passe" />
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-semibold text-[#9a6200] hover:text-tutor-navy">Mot de passe oublié?</Link>
                </div>
                <TextInput id="password" type="password" maxlength="15" class="mt-2" v-model="form.password" required autocomplete="current-password" placeholder="••••••••" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <label class="mt-5 flex items-center gap-2 text-sm text-slate-600">
                <Checkbox name="remember" v-model:checked="form.remember" />
                Se souvenir de moi
            </label>

            <PrimaryButton class="mt-7 w-full" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                Connexion
            </PrimaryButton>
            <p class="mt-7 text-center text-sm text-slate-600">
                Vous n’avez pas encore de compte ?
                <Link :href="route('register')" class="font-bold text-[#9a6200] hover:text-tutor-navy">Inscrivez-vous ici</Link>
            </p>
        </form>
    </GuestLayout>
</template>
