<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ reports: { type: Object, required: true }, filters: { type: Object, default: () => ({}) } });

const filterIsActive = (status) => (props.filters.status || null) === status;
const filterClass = (status) => filterIsActive(status)
    ? 'border-tutor-gold bg-tutor-gold text-tutor-navy shadow-tutor'
    : 'border-slate-200 bg-white text-tutor-navy hover:border-tutor-gold/70 hover:bg-tutor-gold/10';
</script>

<template>
    <Head title="Signalements" />

    <AuthenticatedLayout>
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold sm:text-4xl">Signalements</h1>
                <p class="mt-2 text-slate-600">Traitez les problèmes techniques et comportements signalés.</p>
            </div>
            <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                <Link :href="route('admin.reports.index')" class="rounded-lg border px-4 py-2 text-xs font-bold transition" :class="filterClass(null)">Tous</Link>
                <Link :href="route('admin.reports.index', { status: 'ouvert' })" class="rounded-lg border px-4 py-2 text-xs font-bold transition" :class="filterClass('ouvert')">Ouverts</Link>
                <Link :href="route('admin.reports.index', { status: 'resolu' })" class="rounded-lg border px-4 py-2 text-xs font-bold transition" :class="filterClass('resolu')">Résolus</Link>
            </div>
        </div>

        <div v-if="reports.data.length" class="mt-8 space-y-4">
            <article v-for="report in reports.data" :key="report.id" class="tl-card p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                            <h2 class="text-xl font-bold text-tutor-navy">{{ report.subject }}</h2>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ report.status }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500">Signalé par {{ report.reporter?.name || 'Utilisateur' }} · {{ report.created_at }}</p>
                        <p class="mt-4 text-sm leading-6 text-slate-700">{{ report.description }}</p>
                        <p v-if="report.reported_user" class="mt-3 text-sm font-semibold text-slate-600">Utilisateur concerné: {{ report.reported_user.name }}</p>
                    </div>
                    <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                        <Link :href="route('admin.reports.update', report.id)" method="patch" :data="{ status: 'en_cours' }" as="button" class="tl-button-secondary px-4 py-2 text-xs">En cours</Link>
                        <Link :href="route('admin.reports.update', report.id)" method="patch" :data="{ status: 'resolu' }" as="button" class="tl-button-primary px-4 py-2 text-xs">Résolu</Link>
                        <Link :href="route('admin.reports.update', report.id)" method="patch" :data="{ status: 'ferme' }" as="button" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700">Fermer</Link>
                    </div>
                </div>
            </article>
        </div>
        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucun signalement.</div>
    </AuthenticatedLayout>
</template>
