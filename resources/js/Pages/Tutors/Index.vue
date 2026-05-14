<script setup>
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    tutors: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    subjects: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    subject: props.filters.subject || '',
});

const search = () => {
    form.get(route('tutors.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
</script>

<template>
    <Head title="Tuteurs" />

    <main class="min-h-screen bg-tutor-surface">
        <PublicHeader active="tutors" />

        <section class="mx-auto max-w-7xl px-5 py-12 sm:px-8">
            <div class="grid gap-8 lg:grid-cols-[0.75fr_1.25fr] lg:items-end">
                <div>
                    <h1 class="text-5xl font-bold">Trouver un tuteur</h1>
                    <p class="mt-4 text-lg leading-8 text-slate-600">Consultez uniquement les profils validés par l’administration.</p>
                </div>
                <form class="tl-card flex flex-col gap-3 p-4 sm:flex-row" @submit.prevent="search">
                    <input v-model="form.subject" class="tl-input flex-1 px-4 py-3" placeholder="Matière, domaine ou compétence" />
                    <button class="tl-button-primary" :disabled="form.processing">Rechercher</button>
                </form>
            </div>

            <div v-if="subjects.length" class="mt-8 flex flex-wrap gap-2">
                <Link v-for="subject in subjects" :key="subject" :href="route('tutors.index', { subject })" class="rounded-full bg-white px-4 py-2 text-sm font-bold text-tutor-navy shadow-tutor hover:bg-tutor-gold">{{ subject }}</Link>
            </div>

            <div v-if="tutors.data.length" class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="tutor in tutors.data" :key="tutor.id" class="tl-card overflow-hidden transition hover:-translate-y-1 hover:shadow-tutor-strong">
                    <div class="grid aspect-[4/2.4] place-items-center bg-[#e4edf3]">
                        <div class="grid size-24 place-items-center rounded-full bg-tutor-navy text-3xl font-bold text-white ring-8 ring-white/80">{{ tutor.name.charAt(0) }}</div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-2xl font-bold">{{ tutor.name }}</h2>
                                <p class="text-sm font-bold uppercase tracking-wide text-slate-500">{{ tutor.domain }}</p>
                            </div>
                            <span class="rounded-full bg-[#fff7e8] px-3 py-1 text-sm font-bold text-[#9a6200]">★ {{ tutor.rating || 'N/A' }}</span>
                        </div>
                        <p class="mt-4 line-clamp-3 text-sm leading-6 text-slate-600">{{ tutor.bio }}</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span v-for="subject in tutor.subjects.slice(0, 4)" :key="subject" class="rounded-full bg-[#d5e3ff] px-3 py-1 text-xs font-bold text-tutor-navy">{{ subject }}</span>
                        </div>
                        <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-4">
                            <span class="font-bold text-tutor-navy">{{ money(tutor.hourly_rate) }} / h</span>
                            <Link :href="route('tutors.show', tutor.id)" class="tl-button-secondary px-4 py-2 text-xs">Voir le profil</Link>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-10 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
                <h2 class="text-2xl font-bold">Aucun tuteur trouvé</h2>
                <p class="mt-3 text-slate-600">Les profils apparaîtront ici dès leur validation.</p>
            </div>
        </section>

        <PublicFooter />
    </main>
</template>
