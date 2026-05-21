<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({ users: { type: Object, required: true } });

const patchWithConfirmation = (url, message) => {
    if (window.confirm(message)) {
        router.patch(url, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Gestion des étudiants" />

    <AuthenticatedLayout>
        <div>
            <h1 class="text-3xl font-bold sm:text-4xl">Gestion des étudiants</h1>
            <p class="mt-2 text-slate-600">Supervisez les comptes étudiants et leurs statuts.</p>
        </div>

        <div v-if="users.data.length" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article v-for="user in users.data" :key="user.id" class="tl-card p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-tutor-navy">{{ user.name }}</h2>
                        <p class="break-all text-sm text-slate-600">{{ user.email }}</p>
                        <p class="mt-2 text-xs font-bold uppercase text-slate-500">Inscrit le {{ user.created_at }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold">{{ user.status }}</span>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Link :href="route('admin.users.show', user.id)" class="tl-button-secondary px-4 py-2 text-xs">Détails</Link>
                    <button v-if="user.status !== 'suspendu'" type="button" class="rounded-lg border border-red-200 px-4 py-2 text-xs font-bold text-red-600" @click="patchWithConfirmation(route('admin.users.suspend', user.id), `Suspendre le compte de ${user.name} ?`)">Suspendre</button>
                    <button v-else type="button" class="rounded-lg bg-emerald-100 px-4 py-2 text-xs font-bold text-emerald-700" @click="patchWithConfirmation(route('admin.users.activate', user.id), `Réactiver le compte de ${user.name} ?`)">Réactiver</button>
                </div>
            </article>
        </div>
        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucun étudiant inscrit.</div>
    </AuthenticatedLayout>
</template>
