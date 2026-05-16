<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-tutor-navy">Mon profil</h1>
            <p class="mt-2 text-slate-600">Modifiez vos informations personnelles et les paramètres de sécurité du compte.</p>
        </div>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="tl-card p-6 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-2xl"
                />
            </div>

            <div class="tl-card p-6 sm:p-8">
                <UpdatePasswordForm class="max-w-2xl" />
            </div>

            <div class="tl-card border-red-100 p-6 sm:p-8">
                <DeleteUserForm class="max-w-2xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
