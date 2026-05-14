<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    profile: Object,
    tutorProfile: Object,
    documents: {
        type: Array,
        default: () => [],
    },
    availabilities: {
        type: Array,
        default: () => [],
    },
    metrics: {
        type: Object,
        default: () => ({}),
    },
    requests: {
        type: Array,
        default: () => [],
    },
    upcomingBookings: {
        type: Array,
        default: () => [],
    },
    recentMessages: {
        type: Array,
        default: () => [],
    },
    reviews: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    weekday: 1,
    start_time: '08:00',
    end_time: '09:00',
    is_available: true,
});

const weekdays = {
    1: 'Lundi',
    2: 'Mardi',
    3: 'Mercredi',
    4: 'Jeudi',
    5: 'Vendredi',
    6: 'Samedi',
    7: 'Dimanche',
};

const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;

const addAvailability = () => {
    form.post(route('availabilities.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Tableau de bord tuteur" />

    <AuthenticatedLayout>
        <div class="flex flex-col justify-between gap-5 md:flex-row md:items-start">
            <div>
                <h1 class="text-4xl font-bold">Tableau de bord</h1>
                <p class="mt-2 text-slate-600">Bon retour, {{ $page.props.auth.user.name }}. Voici l’aperçu de votre activité.</p>
            </div>
            <Link :href="route('dashboard')" class="tl-button-secondary">Nouvelle session</Link>
        </div>

        <div v-if="$page.props.auth.user.status !== 'actif'" class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-900">
            <h2 class="text-xl font-bold text-amber-900">Profil tuteur en attente</h2>
            <p class="mt-2 text-sm leading-6">Votre profil n’est pas encore visible publiquement. L’administrateur doit valider vos informations et vos documents.</p>
        </div>

        <div class="mt-8 grid gap-5 md:grid-cols-4">
            <div class="tl-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Demandes</p>
                <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ metrics.requests || 0 }}</p>
            </div>
            <div class="tl-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sessions prévues</p>
                <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ metrics.upcoming || 0 }}</p>
            </div>
            <div class="tl-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Revenus du mois</p>
                <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ money(metrics.monthRevenue) }}</p>
            </div>
            <div class="tl-card p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Note moyenne</p>
                <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ metrics.averageRating || 0 }}/5</p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[1fr_320px]">
            <section class="space-y-6">
                <div class="tl-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 p-6">
                        <h2 class="text-2xl font-bold">Demandes de cours</h2>
                        <span class="text-sm font-bold text-[#9a6200]">Voir tout</span>
                    </div>
                    <div v-if="requests.length" class="divide-y divide-slate-200">
                        <article v-for="booking in requests" :key="booking.id" class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-center gap-4">
                                <div class="grid size-12 place-items-center rounded-full bg-tutor-navy font-bold text-white">{{ booking.student?.name?.charAt(0) || 'E' }}</div>
                                <div>
                                    <h3 class="font-bold text-tutor-navy">{{ booking.student?.name }}</h3>
                                    <p class="text-sm text-slate-600">{{ booking.subject }} · {{ booking.duration_minutes }} min · {{ money(booking.amount) }}</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Link :href="route('bookings.refuse', booking.id)" method="patch" as="button" class="tl-button-secondary px-4 py-2 text-xs">Décliner</Link>
                                <Link :href="route('bookings.accept', booking.id)" method="patch" as="button" class="tl-button-primary px-4 py-2 text-xs">Accepter</Link>
                            </div>
                        </article>
                    </div>
                    <div v-else class="p-8 text-center text-slate-600">Aucune demande en attente.</div>
                </div>

                <div class="tl-card p-6">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <h2 class="text-2xl font-bold">Prochaines sessions</h2>
                        <span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-tutor-navy">Planning</span>
                    </div>
                    <div v-if="upcomingBookings.length" class="mt-5 space-y-3">
                        <article v-for="booking in upcomingBookings" :key="booking.id" class="rounded-lg border border-slate-200 bg-tutor-surface p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="font-bold text-tutor-navy">{{ booking.subject }}</p>
                                    <p class="text-sm text-slate-600">{{ booking.student?.name }} · {{ booking.scheduled_label }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <Link v-if="booking.conversation_id" :href="route('messages.index', booking.conversation_id)" class="tl-button-secondary px-4 py-2 text-xs">Messages</Link>
                                    <Link :href="route('bookings.complete', booking.id)" method="patch" as="button" class="tl-button-primary px-4 py-2 text-xs">Terminer</Link>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div v-else class="mt-5 rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-600">Aucune session confirmée.</div>
                </div>

                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Disponibilités</h2>
                    <form class="mt-5 grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]" @submit.prevent="addAvailability">
                        <select v-model="form.weekday" class="tl-input px-4 py-3">
                            <option v-for="(label, day) in weekdays" :key="day" :value="Number(day)">{{ label }}</option>
                        </select>
                        <input v-model="form.start_time" type="time" class="tl-input px-4 py-3" />
                        <input v-model="form.end_time" type="time" class="tl-input px-4 py-3" />
                        <button class="tl-button-primary" :disabled="form.processing">Ajouter</button>
                    </form>
                    <div v-if="availabilities.length" class="mt-5 grid gap-3 md:grid-cols-2">
                        <div v-for="availability in availabilities" :key="availability.id" class="flex items-center justify-between rounded-lg border border-slate-200 bg-tutor-surface px-4 py-3">
                            <span class="text-sm font-bold text-tutor-navy">{{ weekdays[availability.weekday] }} · {{ availability.start_time }} - {{ availability.end_time }}</span>
                            <Link :href="route('availabilities.destroy', availability.id)" method="delete" as="button" class="text-sm font-bold text-red-600">Supprimer</Link>
                        </div>
                    </div>
                    <div v-else class="mt-5 text-sm text-slate-600">Aucun créneau défini pour le moment.</div>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Messages récents</h2>
                    <div v-if="recentMessages.length" class="mt-4 divide-y divide-slate-200">
                        <Link v-for="message in recentMessages" :key="message.id" :href="route('messages.index', message.id)" class="block py-4">
                            <p class="font-bold text-tutor-navy">{{ message.other_user?.name }}</p>
                            <p class="truncate text-sm text-slate-600">{{ message.last_message || 'Aucun message' }}</p>
                        </Link>
                    </div>
                    <div v-else class="mt-4 text-sm text-slate-600">Aucune conversation active.</div>
                    <Link :href="route('messages.index')" class="tl-button-secondary mt-5 w-full px-4 py-2 text-xs">Ouvrir la messagerie</Link>
                </div>

                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Dernières évaluations</h2>
                    <div v-if="reviews.length" class="mt-4 space-y-4">
                        <article v-for="review in reviews" :key="review.id" class="border-b border-slate-200 pb-4 last:border-0 last:pb-0">
                            <p class="text-tutor-gold">★★★★★</p>
                            <p class="mt-2 text-sm italic leading-6 text-slate-700">“{{ review.comment || 'Aucun commentaire.' }}”</p>
                            <p class="mt-2 text-xs font-bold text-slate-500">{{ review.student }}</p>
                        </article>
                    </div>
                    <div v-else class="mt-4 text-sm text-slate-600">Aucun avis pour le moment.</div>
                </div>

                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Documents</h2>
                    <div v-if="documents.length" class="mt-4 space-y-2 text-sm">
                        <div v-for="document in documents" :key="document.id" class="rounded-lg bg-tutor-surface px-3 py-2 font-semibold text-slate-700">{{ document.original_name }}</div>
                    </div>
                    <div v-else class="mt-4 text-sm text-slate-600">Aucun document transmis.</div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
