<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
    upcomingBookings: {
        type: Array,
        default: () => [],
    },
    recommendedTutors: {
        type: Array,
        default: () => [],
    },
    subjects: {
        type: Array,
        default: () => [],
    },
});

const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;

const statusLabel = {
    en_attente: 'En attente',
    acceptee: 'Confirmée',
    refusee: 'Refusée',
    annulee: 'Annulée',
    terminee: 'Terminée',
};
</script>

<template>
    <Head title="Tableau de bord étudiant" />

    <AuthenticatedLayout>
        <div class="grid gap-8 xl:grid-cols-[1fr_300px]">
            <section>
                <div class="flex flex-col justify-between gap-5 md:flex-row md:items-start">
                    <div>
                        <h1 class="text-4xl font-bold">Bonjour, {{ $page.props.auth.user.name }}</h1>
                        <p class="mt-2 text-slate-600">Heureux de vous revoir. Prêt pour vos prochaines leçons ?</p>
                    </div>
                    <div class="relative w-full max-w-sm">
                        <input class="tl-input w-full px-4 py-3 pl-10" placeholder="Rechercher un sujet, un tuteur..." />
                        <span class="absolute left-3 top-3 text-slate-400">⌕</span>
                    </div>
                </div>

                <div class="mt-8 grid gap-5 md:grid-cols-4">
                    <div class="tl-card p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Séances à venir</p>
                        <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ metrics.upcoming || 0 }}</p>
                    </div>
                    <div class="tl-card p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Séances terminées</p>
                        <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ metrics.completed || 0 }}</p>
                    </div>
                    <div class="tl-card p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Tuteurs favoris</p>
                        <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ metrics.favoriteTutors || 0 }}</p>
                    </div>
                    <div class="tl-card p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Messages non lus</p>
                        <p class="mt-4 text-3xl font-bold text-tutor-navy">{{ metrics.unreadMessages || 0 }}</p>
                    </div>
                </div>

                <div class="mt-10">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Prochaines séances</h2>
                        <Link :href="route('dashboard')" class="text-sm font-bold text-[#9a6200]">Voir tout l’emploi du temps</Link>
                    </div>

                    <div v-if="upcomingBookings.length" class="mt-5 space-y-4">
                        <article v-for="booking in upcomingBookings" :key="booking.id" class="tl-card flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-center gap-4">
                                <div class="grid size-14 place-items-center rounded-full bg-tutor-navy font-bold text-white">{{ booking.tutor?.name?.charAt(0) || 'T' }}</div>
                                <div>
                                    <h3 class="text-xl font-bold text-tutor-navy">{{ booking.tutor?.name }}</h3>
                                    <p class="text-sm text-slate-600">{{ booking.subject }} · {{ booking.scheduled_label }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ statusLabel[booking.status] || booking.status }}</span>
                                <Link v-if="booking.conversation_id" :href="route('messages.index', booking.conversation_id)" class="tl-button-primary px-4 py-2 text-xs">Rejoindre</Link>
                                <span v-else class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-500">En attente</span>
                            </div>
                        </article>
                    </div>

                    <div v-else class="mt-5 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
                        <h3 class="text-xl font-bold">Aucune séance planifiée</h3>
                        <p class="mt-2 text-slate-600">Trouvez un tuteur validé et envoyez votre première demande.</p>
                        <Link :href="route('tutors.index')" class="tl-button-primary mt-5">Trouver un tuteur</Link>
                    </div>
                </div>

                <div class="mt-12">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Tuteurs récents et recommandés</h2>
                        <Link :href="route('tutors.index')" class="text-sm font-bold text-tutor-navy">Tout voir →</Link>
                    </div>
                    <div v-if="recommendedTutors.length" class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <article v-for="tutor in recommendedTutors" :key="tutor.id" class="tl-card overflow-hidden transition hover:-translate-y-1 hover:shadow-tutor-strong">
                            <div class="grid aspect-[4/3] place-items-center bg-[#d8e4ea]">
                                <div class="grid size-20 place-items-center rounded-full bg-tutor-navy text-2xl font-bold text-white">{{ tutor.name.charAt(0) }}</div>
                            </div>
                            <div class="p-5">
                                <h3 class="text-xl font-bold text-tutor-navy">{{ tutor.name }}</h3>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ tutor.domain }}</p>
                                <p class="mt-2 text-sm font-bold text-[#9a6200]">{{ money(tutor.hourly_rate) }} / h</p>
                                <Link :href="route('bookings.create', tutor.id)" class="tl-button-secondary mt-4 w-full px-4 py-2 text-xs">Réserver</Link>
                            </div>
                        </article>
                    </div>
                    <div v-else class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Aucun tuteur validé pour le moment.</div>
                </div>
            </section>

            <aside class="space-y-5">
                <div class="rounded-lg bg-tutor-navy p-6 text-white shadow-tutor">
                    <h2 class="text-2xl font-bold text-white">Trouver un tuteur</h2>
                    <input class="mt-5 w-full rounded-lg border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder:text-white/45" placeholder="Discipline ou compétence" />
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="subject in subjects.slice(0, 6)" :key="subject" class="rounded-full bg-white/12 px-3 py-1 text-xs font-bold text-white/80">{{ subject }}</span>
                    </div>
                    <Link :href="route('tutors.index')" class="tl-button-primary mt-5 w-full">Rechercher</Link>
                </div>

                <div class="tl-card bg-slate-100 p-6">
                    <h3 class="font-bold text-tutor-navy">Astuce du jour</h3>
                    <p class="mt-3 text-sm italic leading-6 text-slate-600">Les étudiants qui réservent leurs séances à l’avance évitent les conflits de créneaux.</p>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
