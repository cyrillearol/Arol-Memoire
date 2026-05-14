<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ reviews: { type: Object, required: true }, averageRating: { type: Number, default: 0 } });
</script>

<template>
    <Head title="Évaluations" />

    <AuthenticatedLayout>
        <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-4xl font-bold">Évaluations</h1>
                <p class="mt-2 text-slate-600">Consultez les avis laissés par les étudiants.</p>
            </div>
            <div class="tl-card px-6 py-4 text-center">
                <p class="text-sm text-slate-500">Note moyenne</p>
                <p class="text-3xl font-bold text-tutor-navy">{{ averageRating || 0 }}/5</p>
            </div>
        </div>

        <div v-if="reviews.data.length" class="mt-8 grid gap-4 lg:grid-cols-2">
            <article v-for="review in reviews.data" :key="review.id" class="tl-card p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-bold text-tutor-navy">{{ review.student || 'Étudiant' }}</h2>
                        <p class="text-sm text-slate-500">{{ review.subject }} · {{ review.created_at }}</p>
                    </div>
                    <p class="text-tutor-gold">{{ '★'.repeat(review.rating) }}</p>
                </div>
                <p class="mt-4 text-sm leading-6 text-slate-700">{{ review.comment || 'Aucun commentaire.' }}</p>
            </article>
        </div>

        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucune évaluation pour le moment.</div>
    </AuthenticatedLayout>
</template>