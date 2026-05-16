<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-xl font-bold text-red-700">Supprimer le compte</h2>
            <p class="mt-1 text-sm leading-6 text-slate-600">
                Cette action supprime définitivement votre compte et les données qui y sont liées. Une confirmation par mot de passe est obligatoire.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Supprimer mon compte</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-xl font-bold text-tutor-navy">Confirmer la suppression du compte</h2>

                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Cette action est irréversible. Saisissez votre mot de passe pour confirmer la suppression définitive de votre compte.
                </p>

                <div class="mt-6">
                    <InputLabel for="password" value="Mot de passe" class="sr-only" />
                    <TextInput id="password" ref="passwordInput" v-model="form.password" type="password" maxlength="15" class="mt-1 block w-full" placeholder="Mot de passe" @keyup.enter="deleteUser" />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <SecondaryButton @click="closeModal">Annuler</SecondaryButton>
                    <DangerButton class="sm:ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="deleteUser">
                        Confirmer la suppression
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
