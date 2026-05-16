<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    userItem: { type: Object, required: true },
    studentBookings: { type: Array, default: () => [] },
    tutorBookings: { type: Array, default: () => [] },
});

const patchWithConfirmation = (url, message) => {
    if (window.confirm(message)) {
        router.patch(url, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="userItem.name" />

    <AuthenticatedLayout>
        <div class="grid gap-8 xl:grid-cols-[360px_1fr]">
            <aside class="tl-card h-fit p-6">
                <div class="grid size-20 place-items-center rounded-full bg-tutor-navy text-2xl font-bold text-white">{{ userItem.name.charAt(0) }}</div>
                <h1 class="mt-5 text-3xl font-bold">{{ userItem.name }}</h1>
                <p class="mt-1 break-all text-slate-600">{{ userItem.email }}</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ userItem.role }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ userItem.status }}</span>
                </div>
                <div class="mt-6 grid gap-2">
                    <button v-if="userItem.status !== 'suspendu'" type="button" class="rounded-lg border border-red-200 px-4 py-3 text-sm font-bold text-red-600" @click="patchWithConfirmation(route('admin.users.suspend', userItem.id), `Suspendre le compte de ${userItem.name} ?`)">Suspendre</button>
                    <button v-else type="button" class="tl-button-primary" @click="patchWithConfirmation(route('admin.users.activate', userItem.id), `Réactiver le compte de ${userItem.name} ?`)">Réactiver</button>
                </div>
            </aside>

            <section class="space-y-6">
                <div v-if="userItem.role === 'tuteur'" class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Profil tuteur</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <div><p class="text-xs font-bold uppercase text-slate-500">Domaine</p><p class="mt-1 font-bold text-tutor-navy">{{ userItem.domain || '-' }}</p></div>
                        <div><p class="text-xs font-bold uppercase text-slate-500">Tarif</p><p class="mt-1 font-bold text-tutor-navy">{{ userItem.hourly_rate || 0 }} FCFA</p></div>
                        <div><p class="text-xs font-bold uppercase text-slate-500">Note</p><p class="mt-1 font-bold text-tutor-navy">{{ userItem.rating || 0 }}/5</p></div>
                    </div>
                </div>

                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Activité étudiant</h2>
                    <div v-if="studentBookings.length" class="mt-4 space-y-3">
                        <div v-for="booking in studentBookings" :key="booking.id" class="rounded-lg bg-tutor-surface p-4">
                            <p class="font-bold text-tutor-navy">{{ booking.subject }}</p>
                            <p class="text-sm text-slate-600">{{ booking.other }} · {{ booking.scheduled_label }} · {{ booking.status }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-600">Aucune réservation étudiant.</p>
                </div>

                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Activité tuteur</h2>
                    <div v-if="tutorBookings.length" class="mt-4 space-y-3">
                        <div v-for="booking in tutorBookings" :key="booking.id" class="rounded-lg bg-tutor-surface p-4">
                            <p class="font-bold text-tutor-navy">{{ booking.subject }}</p>
                            <p class="text-sm text-slate-600">{{ booking.other }} · {{ booking.scheduled_label }} · {{ booking.status }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-600">Aucune réservation tuteur.</p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
