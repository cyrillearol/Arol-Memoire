<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({ users: { type: Object, required: true }, filters: { type: Object, default: () => ({}) } });
const money = (value) => value === null ? '-' : `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
const filterIsActive = (status) => (props.filters.status || null) === status;
const filterClass = (status) => filterIsActive(status)
    ? 'border-tutor-gold bg-tutor-gold text-tutor-navy shadow-tutor'
    : 'border-slate-200 bg-white text-tutor-navy hover:border-tutor-gold/70 hover:bg-tutor-gold/10';
const patchWithConfirmation = (url, message) => {
    if (window.confirm(message)) {
        router.patch(url, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Gestion des tuteurs" />

    <AuthenticatedLayout>
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold sm:text-4xl">Gestion des tuteurs</h1>
                <p class="mt-2 text-slate-600">Validez, rejetez, suspendez ou réactivez les profils tuteurs.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.tutors.index')" class="rounded-lg border px-4 py-2 text-xs font-bold transition" :class="filterClass(null)">Tous</Link>
                <Link :href="route('admin.tutors.index', { status: 'en_attente' })" class="rounded-lg border px-4 py-2 text-xs font-bold transition" :class="filterClass('en_attente')">En attente</Link>
                <Link :href="route('admin.tutors.index', { status: 'actif' })" class="rounded-lg border px-4 py-2 text-xs font-bold transition" :class="filterClass('actif')">Actifs</Link>
            </div>
        </div>

        <div v-if="users.data.length" class="mt-8 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-tutor">
            <table class="min-w-[820px] text-left text-sm">
                <thead class="bg-slate-100 text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-4">Tuteur</th>
                        <th class="px-5 py-4">Domaine</th>
                        <th class="px-5 py-4">Statut</th>
                        <th class="px-5 py-4">Docs</th>
                        <th class="px-5 py-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="px-5 py-4"><p class="font-bold text-tutor-navy">{{ user.name }}</p><p class="text-xs text-slate-500">{{ user.email }}</p></td>
                        <td class="px-5 py-4"><p>{{ user.domain || '-' }}</p><p class="text-xs text-slate-500">{{ money(user.hourly_rate) }}/h</p></td>
                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ user.status }}</span></td>
                        <td class="px-5 py-4"><a v-for="doc in user.documents" :key="doc.id" :href="route('admin.tutor-documents.download', doc.id)" class="mr-2 rounded bg-slate-100 px-2 py-1 text-xs font-bold text-tutor-navy">Doc</a></td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <Link :href="route('admin.users.show', user.id)" class="tl-button-secondary px-3 py-2 text-xs">Détails</Link>
                                <Link v-if="user.status === 'actif'" :href="route('admin.tutors.message', user.id)" method="post" as="button" class="rounded-lg bg-tutor-navy px-3 py-2 text-xs font-bold text-white">Message</Link>
                                <button v-if="user.status !== 'actif'" type="button" class="rounded-lg bg-emerald-100 px-3 py-2 text-xs font-bold text-emerald-700" @click="patchWithConfirmation(route('admin.tutors.accept', user.id), `Valider le profil tuteur de ${user.name} ?`)">Valider</button>
                                <button v-if="user.status === 'en_attente'" type="button" class="rounded-lg bg-red-100 px-3 py-2 text-xs font-bold text-red-700" @click="patchWithConfirmation(route('admin.tutors.reject', user.id), `Refuser la candidature de ${user.name} ?`)">Refuser</button>
                                <button v-if="user.status !== 'suspendu'" type="button" class="rounded-lg bg-amber-100 px-3 py-2 text-xs font-bold text-amber-700" @click="patchWithConfirmation(route('admin.tutors.suspend', user.id), `Suspendre le profil tuteur de ${user.name} ?`)">Suspendre</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucun tuteur trouvé.</div>
    </AuthenticatedLayout>
</template>
