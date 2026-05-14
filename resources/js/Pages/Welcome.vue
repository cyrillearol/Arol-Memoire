<script setup>
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    publicTutors: {
        type: Array,
        default: () => [],
    },
    tutorCount: {
        type: Number,
        default: 0,
    },
    studentCount: {
        type: Number,
        default: 0,
    },
    sessionCount: {
        type: Number,
        default: 0,
    },
});

const activeTab = ref('reservation');

const tabs = [
    {
        id: 'reservation',
        label: 'Réservation',
        title: 'Planifiez une séance sans échange interminable.',
        text: 'L’étudiant choisit un tuteur validé, sélectionne un créneau et règle la séance avant confirmation définitive.',
    },
    {
        id: 'communication',
        label: 'Communication',
        title: 'Discutez après validation de la réservation.',
        text: 'La messagerie s’ouvre uniquement quand la séance est acceptée. Les documents, appels audio et appels vidéo restent au même endroit.',
    },
    {
        id: 'suivi',
        label: 'Suivi',
        title: 'Gardez un historique clair des cours.',
        text: 'Réservations, paiements, messages et évaluations sont reliés aux profils pour faciliter le suivi pédagogique.',
    },
];

const formatNumber = (value) => new Intl.NumberFormat('fr-FR').format(value || 0);
const formatMoney = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
const tutorSessionImage = '/images/tutor-online-session.jpg';
</script>

<template>
    <Head title="Accueil" />

    <main class="min-h-screen bg-tutor-surface text-tutor-ink">
        <PublicHeader active="home" />

        <section class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[1fr_0.95fr] lg:items-center lg:py-20">
            <div>
                <h1 class="max-w-2xl text-5xl font-bold leading-tight sm:text-6xl">L’excellence académique à portée de main</h1>
                <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600">
                    Connectez-vous à des tuteurs qualifiés, réservez vos séances en ligne et suivez vos échanges dans un espace moderne et sécurisé.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Link v-if="canRegister" :href="route('register')" class="tl-button-primary">Je suis étudiant <span>→</span></Link>
                    <Link v-if="canRegister" :href="route('register')" class="tl-button-secondary">Je suis tuteur</Link>
                </div>
            </div>

            <div class="relative">
                <div class="overflow-hidden rounded-lg shadow-tutor-strong ring-1 ring-slate-200">
                    <img :src="tutorSessionImage" alt="Séance de tutorat en ligne" class="aspect-[4/3] h-full w-full object-cover" />
                </div>
                <div class="absolute bottom-6 left-6 right-6 rounded-lg bg-white/92 p-5 shadow-tutor backdrop-blur">
                    <p class="text-sm font-bold uppercase tracking-wide text-[#9a6200]">Cours en direct</p>
                    <p class="mt-2 text-xl font-bold text-tutor-navy">Réservation, paiement et échange dans un seul espace.</p>
                </div>
            </div>
        </section>

        <section class="bg-tutor-navy text-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-9 text-center sm:px-8 md:grid-cols-3">
                <div>
                    <p class="font-display text-4xl font-bold text-white"><span class="text-tutor-gold">{{ formatNumber(tutorCount) }}</span>+</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-white/45">tuteurs validés</p>
                </div>
                <div>
                    <p class="font-display text-4xl font-bold text-white"><span class="text-tutor-gold">{{ formatNumber(studentCount) }}</span>+</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-white/45">étudiants inscrits</p>
                </div>
                <div>
                    <p class="font-display text-4xl font-bold text-white"><span class="text-tutor-gold">{{ formatNumber(sessionCount) }}</span>+</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-white/45">séances réalisées</p>
                </div>
            </div>
        </section>

        <section id="fonctionnement" class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
            <h2 class="text-center text-3xl font-bold">Comment ça marche</h2>
            <div class="mt-12 grid gap-8 md:grid-cols-3">
                <article class="text-center">
                    <div class="mx-auto grid size-14 place-items-center rounded-full bg-[#d5e3ff] text-tutor-navy">▦</div>
                    <h3 class="mt-5 text-xl font-semibold">Inscription</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Créez votre espace et choisissez votre rôle: étudiant ou tuteur.</p>
                </article>
                <article class="text-center">
                    <div class="mx-auto grid size-14 place-items-center rounded-full bg-[#d5e3ff] text-tutor-navy">□</div>
                    <h3 class="mt-5 text-xl font-semibold">Réservation</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Sélectionnez un tuteur, un créneau disponible et un mode de paiement.</p>
                </article>
                <article class="text-center">
                    <div class="mx-auto grid size-14 place-items-center rounded-full bg-[#d5e3ff] text-tutor-navy">▣</div>
                    <h3 class="mt-5 text-xl font-semibold">Séance</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Échangez avec votre tuteur après acceptation de la demande.</p>
                </article>
            </div>
        </section>

        <section class="bg-[#eef1f4] py-20">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                    <div>
                        <h2 class="text-3xl font-bold">Tuteurs en vedette</h2>
                        <p class="mt-2 text-slate-600">Les profils apparaissent ici uniquement après validation administrative.</p>
                    </div>
                    <Link :href="route('tutors.index')" class="text-sm font-bold text-tutor-navy">Voir tous les tuteurs →</Link>
                </div>

                <div v-if="publicTutors.length" class="mt-10 grid gap-6 md:grid-cols-3">
                    <article v-for="tutor in publicTutors" :key="tutor.id" class="tl-card p-6 transition hover:-translate-y-1 hover:shadow-tutor-strong">
                        <div class="flex items-center gap-4">
                            <div class="grid size-14 place-items-center rounded-full bg-tutor-navy font-bold text-white">{{ tutor.name.charAt(0) }}</div>
                            <div>
                                <h3 class="text-lg font-bold">{{ tutor.name }}</h3>
                                <p class="text-sm text-slate-500">{{ tutor.domain || 'Domaine à préciser' }}</p>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span v-for="subject in tutor.subjects.slice(0, 3)" :key="subject" class="rounded-full bg-[#d5e3ff] px-3 py-1 text-xs font-bold text-tutor-navy">{{ subject }}</span>
                        </div>
                        <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-4">
                            <span class="font-bold text-tutor-navy">{{ formatMoney(tutor.hourly_rate) }} / h</span>
                            <Link :href="route('tutors.show', tutor.id)" class="tl-button-secondary px-4 py-2 text-xs">Voir le profil</Link>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-10 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
                    <h3 class="text-2xl font-bold">Aucun tuteur public pour le moment</h3>
                    <p class="mx-auto mt-3 max-w-2xl text-slate-600">Les candidatures validées par l’administrateur seront affichées automatiquement dans cette section.</p>
                    <Link :href="route('register')" class="tl-button-primary mt-6">Devenir tuteur</Link>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
            <div class="tl-card overflow-hidden">
                <div class="grid md:grid-cols-[0.72fr_1fr]">
                    <div class="bg-tutor-navy p-8 text-white md:p-10">
                        <p class="text-xs font-bold uppercase tracking-wide text-tutor-gold">Services</p>
                        <h2 class="mt-4 text-4xl font-bold text-white">Une plateforme complète pour le tutorat en ligne.</h2>
                        <div class="mt-8 grid gap-3">
                            <button v-for="tab in tabs" :key="tab.id" type="button" class="rounded-lg px-4 py-3 text-left text-sm font-bold transition" :class="activeTab === tab.id ? 'bg-tutor-gold text-tutor-navy' : 'bg-white/8 text-white/70 hover:bg-white/14 hover:text-white'" @click="activeTab = tab.id">
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>
                    <div class="p-8 md:p-10">
                        <div v-for="tab in tabs" v-show="activeTab === tab.id" :key="tab.id">
                            <h3 class="text-3xl font-bold">{{ tab.title }}</h3>
                            <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-600">{{ tab.text }}</p>
                            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                                <div class="rounded-lg bg-tutor-surface p-5">
                                    <p class="text-sm font-bold text-tutor-navy">Paiements</p>
                                    <p class="mt-2 text-sm text-slate-600">Historique et statut des transactions.</p>
                                </div>
                                <div class="rounded-lg bg-tutor-surface p-5">
                                    <p class="text-sm font-bold text-tutor-navy">Notifications</p>
                                    <p class="mt-2 text-sm text-slate-600">Alertes sur réservations, messages et validations.</p>
                                </div>
                                <div class="rounded-lg bg-tutor-surface p-5">
                                    <p class="text-sm font-bold text-tutor-navy">Évaluations</p>
                                    <p class="mt-2 text-sm text-slate-600">Notes et avis après les séances.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="tarifs" class="mx-auto max-w-7xl px-5 pb-20 sm:px-8">
            <div class="rounded-2xl bg-tutor-navy p-10 text-center text-white md:p-14">
                <h2 class="mx-auto max-w-2xl text-4xl font-bold text-white">Rejoignez un espace pensé pour apprendre sérieusement.</h2>
                <p class="mx-auto mt-5 max-w-2xl text-white/65">Chaque tuteur définit son tarif horaire. Le paiement est associé à la réservation et suivi depuis votre tableau de bord.</p>
                <Link :href="route('register')" class="tl-button-primary mt-8">S’inscrire maintenant</Link>
            </div>
        </section>

        <PublicFooter />
    </main>
</template>
