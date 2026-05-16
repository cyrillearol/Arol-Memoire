<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-xl font-bold text-tutor-navy">Informations personnelles</h2>
            <p class="mt-1 text-sm text-slate-600">Mettez à jour votre nom et votre adresse e-mail.</p>
        </header>

        <form class="mt-6 space-y-6" @submit.prevent="form.patch(route('profile.update'))">
            <div>
                <InputLabel for="name" value="Nom complet" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-2 block w-full" required autofocus autocomplete="name" maxlength="255" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Adresse e-mail" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-2 block w-full" required autocomplete="username" maxlength="255" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-slate-700">
                    Votre adresse e-mail n’est pas encore vérifiée.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-bold text-[#9a6200] underline hover:text-tutor-navy"
                    >
                        Renvoyer l’e-mail de vérification.
                    </Link>
                </p>

                <div v-show="props.status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-emerald-600">
                    Un nouveau lien de vérification a été envoyé à votre adresse e-mail.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>

                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm font-semibold text-emerald-600">Modifications enregistrées.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
