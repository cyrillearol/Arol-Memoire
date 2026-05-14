<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
    pendingTutors: {
        type: Array,
        default: () => [],
    },
    reports: {
        type: Array,
        default: () => [],
    },
    subjectStats: {
        type: Array,
        default: () => [],
    },
});

const totalSubjects = () => props.subjectStats.reduce((sum, item) => sum + Number(item.total || 0), 0) || 1;
</script>

<template>
    <Head title="Administration" />

    <AuthenticatedLayout>
        <div class="flex flex-col justify-between gap-5 md:flex-row md:items-center">
            <div>
                <h1 class="text-4xl font-bold">Tableau de Bord</h1>
                <p class="mt-2 text-slate-600">Surveillance globale de l’écosystème TutorLink.</p>
            </div>
            <div class="relative w-full max-w-sm">
                <input class="tl-input w-full px-4 py-3 pl-10" placeholder="Rechercher..." />
                <span class="absolute left-3 top-3 text-slate-400">⌕</span>
            </div>
        </div>

        <div class="mt-8 grid gap-5 md:grid-cols-4">
            <div class="tl-card p-6">
                <p class="text-sm text-slate-600">Total étudiants</p>
                <p class="mt-5 text-3xl font-bold text-tutor-navy">{{ stats.students || 0 }}</p>
            </div>
            <div class="tl-card p-6">
                <p class="text-sm text-slate-600">Tuteurs validés</p>
                <p class="mt-5 text-3xl font-bold text-tutor-navy">{{ stats.validatedTutors || 0 }}</p>
            </div>
            <div class="tl-card p-6">
                <p class="text-sm text-slate-600">Réservations</p>
                <p class="mt-5 text-3xl font-bold text-tutor-navy">{{ stats.bookings || 0 }}</p>
            </div>
            <div class="tl-card p-6">
                <p class="text-sm text-slate-600">Signalements ouverts</p>
                <p class="mt-5 text-3xl font-bold text-red-600">{{ stats.pendingReports || 0 }}</p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[1fr_320px]">
            <section class="space-y-6">
                <div class="tl-card p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Croissance des utilisateurs</h2>
                        <div class="flex gap-4 text-sm font-bold"><span class="text-tutor-navy">● Tuteurs</span><span class="text-[#9a6200]">● Étudiants</span></div>
                    </div>
                    <div class="mt-8 h-56 rounded-lg bg-tutor-surface p-6">
                        <div class="flex h-full items-end gap-4">
                            <div v-for="height in [42, 58, 48, 72, 88, 36, 95]" :key="height" class="flex-1 rounded-t-lg bg-slate-300" :style="{ height: height + '%' }"></div>
                        </div>
                    </div>
                </div>

                <div class="tl-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 p-6">
                        <h2 class="text-2xl font-bold">Validations en attente</h2>
                        <span class="text-sm font-bold text-slate-500">{{ pendingTutors.length }} dossier(s)</span>
                    </div>

                    <div v-if="pendingTutors.length" class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-100 text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">Tuteur</th>
                                    <th class="px-5 py-4">Matière</th>
                                    <th class="px-5 py-4">Date</th>
                                    <th class="px-5 py-4">Docs</th>
                                    <th class="px-5 py-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr v-for="tutor in pendingTutors" :key="tutor.id">
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-tutor-navy">{{ tutor.name }}</p>
                                        <p class="text-xs text-slate-500">{{ tutor.email }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">{{ tutor.domain }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ tutor.created_at }}</td>
                                    <td class="px-5 py-4">
                                        <div v-if="tutor.documents.length" class="flex flex-wrap gap-2">
                                            <a v-for="document in tutor.documents" :key="document.id" :href="route('admin.tutor-documents.download', document.id)" class="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-tutor-navy">Doc</a>
                                        </div>
                                        <span v-else class="text-xs text-slate-400">Aucun</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex gap-2">
                                            <Link :href="route('admin.tutors.accept', tutor.id)" method="patch" as="button" class="rounded-lg bg-emerald-100 px-3 py-2 text-xs font-bold text-emerald-700">Valider</Link>
                                            <Link :href="route('admin.tutors.reject', tutor.id)" method="patch" as="button" class="rounded-lg bg-red-100 px-3 py-2 text-xs font-bold text-red-700">Refuser</Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-8 text-center text-slate-600">Aucune candidature tuteur en attente.</div>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Réservations par matière</h2>
                    <div v-if="subjectStats.length" class="mt-6 space-y-5">
                        <div v-for="item in subjectStats" :key="item.subject">
                            <div class="flex justify-between text-sm font-bold text-tutor-navy"><span>{{ item.subject }}</span><span>{{ Math.round((item.total / totalSubjects()) * 100) }}%</span></div>
                            <div class="mt-2 h-2 rounded bg-slate-100"><div class="h-2 rounded bg-tutor-gold" :style="{ width: Math.max(8, Math.round((item.total / totalSubjects()) * 100)) + '%' }"></div></div>
                        </div>
                    </div>
                    <div v-else class="mt-6 text-sm text-slate-600">Aucune réservation enregistrée.</div>
                </div>

                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Signalements récents</h2>
                    <div v-if="reports.length" class="mt-5 space-y-4">
                        <article v-for="report in reports" :key="report.id" class="rounded-lg border border-slate-200 p-4">
                            <p class="font-bold text-tutor-navy">{{ report.subject }}</p>
                            <p class="mt-1 text-xs text-slate-500">Signalé par: {{ report.reporter || 'Utilisateur' }} · {{ report.created_at }}</p>
                            <p class="mt-3 line-clamp-2 text-sm text-slate-600">{{ report.description }}</p>
                            <Link :href="route('admin.reports.update', report.id)" method="patch" :data="{ status: 'resolu' }" as="button" class="tl-button-secondary mt-4 w-full px-4 py-2 text-xs">Marquer résolu</Link>
                        </article>
                    </div>
                    <div v-else class="mt-5 text-sm text-slate-600">Aucun signalement.</div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
