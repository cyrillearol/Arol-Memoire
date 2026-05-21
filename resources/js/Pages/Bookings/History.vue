<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ bookings: { type: Object, required: true } });

const labels = { en_attente: 'En attente', acceptee: 'Confirmée', refusee: 'Refusée', annulee: 'Annulée', terminee: 'Terminée' };
const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
</script>

<template>
    <Head title="Historique" />

    <AuthenticatedLayout>
        <div>
            <h1 class="text-3xl font-bold sm:text-4xl">Historique</h1>
            <p class="mt-2 text-slate-600">Retrouvez vos séances terminées, annulées ou refusées.</p>
        </div>

        <div v-if="bookings.data.length" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article v-for="booking in bookings.data" :key="booking.id" class="tl-card p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-tutor-navy">{{ booking.subject }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ booking.tutor?.name }} · {{ booking.scheduled_label }}</p>
                        <p class="mt-2 text-sm font-semibold text-[#9a6200]">{{ money(booking.amount) }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700">{{ labels[booking.status] || booking.status }}</span>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Link :href="route('bookings.show', booking.id)" class="tl-button-secondary px-4 py-2 text-xs">Détails</Link>
                    <Link v-if="booking.conversation_id" :href="route('messages.index', booking.conversation_id)" class="tl-button-primary px-4 py-2 text-xs">Messages</Link>
                </div>
            </article>
        </div>

        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucun élément dans l’historique.</div>
    </AuthenticatedLayout>
</template>
