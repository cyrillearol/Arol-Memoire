<script setup>
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    tutor: Object,
    availabilities: {
        type: Array,
        default: () => [],
    },
    reviews: {
        type: Array,
        default: () => [],
    },
    canMessage: Boolean,
});

const days = [
    { id: 1, label: 'Lun' },
    { id: 2, label: 'Mar' },
    { id: 3, label: 'Mer' },
    { id: 4, label: 'Jeu' },
    { id: 5, label: 'Ven' },
    { id: 6, label: 'Sam' },
    { id: 7, label: 'Dim' },
];

const available = (day, period) => props.availabilities.some((slot) => {
    if (Number(slot.weekday) !== day || !slot.is_available) return false;
    return period === 'morning' ? slot.start_time < '12:00' : slot.start_time >= '12:00';
});

const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
</script>

<template>
    <Head :title="tutor.name" />

    <main class="min-h-screen bg-tutor-surface">
        <PublicHeader active="tutors" />

        <section class="mx-auto grid max-w-7xl gap-8 px-5 py-12 sm:px-8 lg:grid-cols-[340px_1fr]">
            <aside class="tl-card h-fit p-8 text-center">
                <div class="mx-auto grid size-36 place-items-center rounded-full bg-tutor-navy text-5xl font-bold text-white ring-8 ring-[#d5e3ff]">{{ tutor.name.charAt(0) }}</div>
                <h1 class="mt-6 text-3xl font-bold">{{ tutor.name }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ tutor.domain }}</p>
                <p class="mt-4 font-bold text-[#9a6200]">★ {{ tutor.rating || 'N/A' }} <span class="font-normal text-slate-500">({{ tutor.reviews_count }} avis)</span></p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <span v-for="subject in tutor.subjects" :key="subject" class="rounded-full bg-[#d5e3ff] px-3 py-1 text-xs font-bold text-tutor-navy">{{ subject }}</span>
                </div>
                <div class="my-7 border-t border-slate-200"></div>
                <p class="text-2xl font-bold text-tutor-navy">{{ money(tutor.hourly_rate) }} <span class="text-sm font-normal text-slate-500">/ heure</span></p>
                <Link :href="route('bookings.create', tutor.id)" class="tl-button-primary mt-7 w-full">Réserver une séance</Link>
                <Link v-if="canMessage" :href="route('messages.index')" class="tl-button-secondary mt-3 w-full">Envoyer un message</Link>
                <button v-else class="tl-button-secondary mt-3 w-full opacity-50" disabled>Message après réservation</button>
            </aside>

            <div class="space-y-10">
                <section>
                    <h2 class="text-3xl font-bold">À propos</h2>
                    <p class="mt-5 max-w-4xl text-lg leading-8 text-slate-700">{{ tutor.bio || 'Ce tuteur n’a pas encore ajouté de présentation détaillée.' }}</p>
                </section>

                <section>
                    <h2 class="text-3xl font-bold">Compétences</h2>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div v-for="(subject, index) in tutor.subjects" :key="subject">
                            <div class="flex justify-between text-sm font-bold text-tutor-navy"><span>{{ subject }}</span><span>{{ 95 - index * 3 }}%</span></div>
                            <div class="mt-2 h-2 rounded bg-slate-200"><div class="h-2 rounded bg-tutor-gold" :style="{ width: Math.max(76, 95 - index * 3) + '%' }"></div></div>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="text-3xl font-bold">Disponibilités</h2>
                    <div class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
                        <table class="min-w-full text-center text-sm">
                            <thead class="bg-slate-100 font-bold text-tutor-navy">
                                <tr>
                                    <th class="border-r border-slate-200 px-4 py-4">H/J</th>
                                    <th v-for="day in days" :key="day.id" class="border-r border-slate-200 px-4 py-4 last:border-r-0">{{ day.label }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="border-r border-t border-slate-200 px-4 py-4 font-bold">Matin</td>
                                    <td v-for="day in days" :key="day.id" class="border-r border-t border-slate-200 px-4 py-4 last:border-r-0" :class="available(day.id, 'morning') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-300'">{{ available(day.id, 'morning') ? '✓' : '○' }}</td>
                                </tr>
                                <tr>
                                    <td class="border-r border-t border-slate-200 px-4 py-4 font-bold">Soir</td>
                                    <td v-for="day in days" :key="day.id" class="border-r border-t border-slate-200 px-4 py-4 last:border-r-0" :class="available(day.id, 'evening') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-300'">{{ available(day.id, 'evening') ? '✓' : '○' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <div class="flex items-center justify-between">
                        <h2 class="text-3xl font-bold">Avis des étudiants</h2>
                        <span class="text-sm font-bold text-tutor-navy">Voir tout →</span>
                    </div>
                    <div v-if="reviews.length" class="mt-6 space-y-4">
                        <article v-for="review in reviews" :key="review.id" class="tl-card p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-10 place-items-center rounded-full bg-[#d5e3ff] font-bold text-tutor-navy">{{ review.student?.charAt(0) || 'E' }}</div>
                                    <div>
                                        <p class="font-bold text-tutor-navy">{{ review.student || 'Étudiant' }}</p>
                                        <p class="text-xs text-slate-500">{{ review.created_at }}</p>
                                    </div>
                                </div>
                                <p class="text-tutor-gold">★★★★★</p>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-700">{{ review.comment }}</p>
                        </article>
                    </div>
                    <div v-else class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">Aucun avis pour le moment.</div>
                </section>
            </div>
        </section>

        <PublicFooter />
    </main>
</template>
