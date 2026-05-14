<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ users: { type: Object, required: true } });
</script>

<template>
    <Head title="Gestion des étudiants" />

    <AuthenticatedLayout>
        <div>
            <h1 class="text-4xl font-bold">Gestion des étudiants</h1>
            <p class="mt-2 text-slate-600">Supervisez les comptes étudiants et leurs statuts.</p>
        </div>

        <div v-if="users.data.length" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article v-for="user in users.data" :key="user.id" class="tl-card p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-tutor-navy">{{ user.name }}</h2>
                        <p class="text-sm text-slate-600">{{ user.email }}</p>
                        <p class="mt-2 text-xs font-bold uppercase text-slate-500">Inscrit le {{ user.created_at }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold">{{ user.status }}</span>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Link :href="route('admin.users.show', user.id)" class="tl-button-secondary px-4 py-2 text-xs">Détails</Link>
                    <Link v-if="user.status !== 'suspendu'" :href="route('admin.users.suspend', user.id)" method="patch" as="button" class="rounded-lg border border-red-200 px-4 py-2 text-xs font-bold text-red-600">Suspendre</Link>
                    <Link v-else :href="route('admin.users.activate', user.id)" method="patch" as="button" class="rounded-lg bg-emerald-100 px-4 py-2 text-xs font-bold text-emerald-700">Réactiver</Link>
                </div>
            </article>
        </div>
        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucun étudiant inscrit.</div>
    </AuthenticatedLayout>
</template>