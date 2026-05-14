<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    bookings: { type: Object, required: true },
    mode: { type: String, default: 'upcoming' },
});

const labels = {
    en_attente: 'En attente',
    acceptee: 'Confirmée',
    refusee: 'Refusée',
    annulee: 'Annulée',
    terminee: 'Terminée',
};

const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
</script>

<template>
    <Head title="Mes réservations" />

    <AuthenticatedLayout>
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-4xl font-bold">Mes réservations</h1>
                <p class="mt-2 text-slate-600">Suivez vos demandes en attente et vos séances confirmées.</p>
            </div>
            <Link :href="route('tutors.index')" class="tl-button-primary">Trouver un tuteur</Link>
        </div>

        <div v-if="bookings.data.length" class="mt-8 space-y-4">
            <article v-for="booking in bookings.data" :key="booking.id" class="tl-card p-5">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="grid size-14 place-items-center rounded-full bg-tutor-navy font-bold text-white">{{ booking.tutor?.name?.charAt(0) || 'T' }}</div>
                        <div>
                            <h2 class="text-xl font-bold text-tutor-navy">{{ booking.subject }}</h2>
                            <p class="text-sm text-slate-600">{{ booking.tutor?.name }} · {{ booking.scheduled_label }}</p>
                            <p class="mt-1 text-sm font-semibold text-[#9a6200]">{{ money(booking.amount) }} · {{ booking.duration_minutes }} min</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700">{{ labels[booking.status] || booking.status }}</span>
                        <Link :href="route('bookings.show', booking.id)" class="tl-button-secondary px-4 py-2 text-xs">Détails</Link>
                        <Link v-if="booking.conversation_id" :href="route('messages.index', booking.conversation_id)" class="tl-button-primary px-4 py-2 text-xs">Messages</Link>
                        <Link v-if="['en_attente', 'acceptee'].includes(booking.status)" :href="route('bookings.cancel', booking.id)" method="patch" as="button" class="rounded-lg border border-red-200 px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50">Annuler</Link>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
            <h2 class="text-2xl font-bold">Aucune réservation active</h2>
            <p class="mt-2 text-slate-600">Réservez une séance avec un tuteur validé pour la voir ici.</p>
            <Link :href="route('tutors.index')" class="tl-button-primary mt-6">Trouver un tuteur</Link>
        </div>
    </AuthenticatedLayout>
</template>