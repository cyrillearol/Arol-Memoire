<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-xl font-bold text-tutor-navy">Mot de passe</h2>
            <p class="mt-1 text-sm text-slate-600">Utilisez un mot de passe long et difficile à deviner.</p>
        </header>

        <form class="mt-6 space-y-6" @submit.prevent="updatePassword">
            <div>
                <InputLabel for="current_password" value="Mot de passe actuel" />
                <TextInput id="current_password" ref="currentPasswordInput" v-model="form.current_password" type="password" maxlength="15" class="mt-2 block w-full" autocomplete="current-password" />
                <InputError :message="form.errors.current_password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="password" value="Nouveau mot de passe" />
                <TextInput id="password" ref="passwordInput" v-model="form.password" type="password" maxlength="15" class="mt-2 block w-full" autocomplete="new-password" />
                <p class="mt-2 text-xs text-slate-500">Entre 8 et 15 caractères.</p>
                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirmer le nouveau mot de passe" />
                <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" maxlength="15" class="mt-2 block w-full" autocomplete="new-password" />
                <InputError :message="form.errors.password_confirmation" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Modifier le mot de passe</PrimaryButton>

                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm font-semibold text-emerald-600">Mot de passe modifié.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
