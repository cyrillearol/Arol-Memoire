<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    requests: { type: Object, required: true },
    upcoming: { type: Array, default: () => [] },
});

const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
const patchWithConfirmation = (url, message) => {
    if (window.confirm(message)) {
        router.patch(url, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Demandes" />

    <AuthenticatedLayout>
        <div>
            <h1 class="text-4xl font-bold">Demandes de réservation</h1>
            <p class="mt-2 text-slate-600">Acceptez ou refusez les nouvelles demandes des étudiants.</p>
        </div>

        <section class="mt-8">
            <h2 class="text-2xl font-bold">En attente</h2>
            <div v-if="requests.data.length" class="mt-5 space-y-4">
                <article v-for="booking in requests.data" :key="booking.id" class="tl-card p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-tutor-navy">{{ booking.subject }}</h3>
                            <p class="text-sm text-slate-600">{{ booking.student?.name }} · {{ booking.scheduled_label }}</p>
                            <p class="mt-2 text-sm font-semibold text-[#9a6200]">{{ money(booking.amount) }} · {{ booking.payment_status }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Link :href="route('bookings.show', booking.id)" class="tl-button-secondary px-4 py-2 text-xs">Détails</Link>
                            <button type="button" class="tl-button-secondary px-4 py-2 text-xs" @click="patchWithConfirmation(route('bookings.refuse', booking.id), 'Refuser cette demande de réservation ?')">Refuser</button>
                            <button type="button" class="tl-button-primary px-4 py-2 text-xs" @click="patchWithConfirmation(route('bookings.accept', booking.id), 'Accepter cette demande de réservation ?')">Accepter</button>
                        </div>
                    </div>
                </article>
            </div>
            <div v-else class="mt-5 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Aucune demande en attente.</div>
        </section>

        <section class="mt-10">
            <h2 class="text-2xl font-bold">Séances confirmées</h2>
            <div v-if="upcoming.length" class="mt-5 grid gap-4 lg:grid-cols-2">
                <article v-for="booking in upcoming" :key="booking.id" class="tl-card p-5">
                    <h3 class="text-xl font-bold text-tutor-navy">{{ booking.subject }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ booking.student?.name }} · {{ booking.scheduled_label }}</p>
                    <div class="mt-5 flex gap-2">
                        <Link :href="route('bookings.show', booking.id)" class="tl-button-secondary px-4 py-2 text-xs">Détails</Link>
                        <Link v-if="booking.conversation_id" :href="route('messages.index', booking.conversation_id)" class="tl-button-primary px-4 py-2 text-xs">Messages</Link>
                    </div>
                </article>
            </div>
            <div v-else class="mt-5 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Aucune séance confirmée.</div>
        </section>
    </AuthenticatedLayout>
</template>
