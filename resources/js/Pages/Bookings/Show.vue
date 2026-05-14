<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    booking: { type: Object, required: true },
    viewerRole: { type: String, required: true },
});

const reviewForm = useForm({ rating: props.booking.review?.rating || 5, comment: props.booking.review?.comment || '' });
const labels = { en_attente: 'En attente', acceptee: 'Confirmée', refusee: 'Refusée', annulee: 'Annulée', terminee: 'Terminée' };
const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;

const submitReview = () => reviewForm.post(route('reviews.store', props.booking.id), { preserveScroll: true });
</script>

<template>
    <Head :title="`Réservation ${booking.subject}`" />

    <AuthenticatedLayout>
        <div class="grid gap-8 xl:grid-cols-[1fr_360px]">
            <section class="space-y-6">
                <div class="tl-card p-7">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h1 class="text-4xl font-bold">{{ booking.subject }}</h1>
                            <p class="mt-2 text-slate-600">{{ booking.scheduled_label }} · {{ booking.duration_minutes }} min</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-tutor-navy">{{ labels[booking.status] || booking.status }}</span>
                    </div>

                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg bg-tutor-surface p-4">
                            <p class="text-xs font-bold uppercase text-slate-500">Tuteur</p>
                            <p class="mt-2 font-bold text-tutor-navy">{{ booking.tutor?.name }}</p>
                            <p class="text-sm text-slate-600">{{ booking.tutor?.domain }}</p>
                        </div>
                        <div class="rounded-lg bg-tutor-surface p-4">
                            <p class="text-xs font-bold uppercase text-slate-500">Paiement</p>
                            <p class="mt-2 font-bold text-tutor-navy">{{ money(booking.amount) }}</p>
                            <p class="text-sm text-slate-600">{{ booking.payment?.status || 'Non renseigné' }}</p>
                        </div>
                        <div class="rounded-lg bg-tutor-surface p-4">
                            <p class="text-xs font-bold uppercase text-slate-500">Référence</p>
                            <p class="mt-2 break-all text-sm font-bold text-tutor-navy">{{ booking.payment?.reference || 'Aucune' }}</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h2 class="text-2xl font-bold">Notes de séance</h2>
                        <p class="mt-3 rounded-lg bg-tutor-surface p-5 text-sm leading-6 text-slate-700">{{ booking.notes || 'Aucune note fournie.' }}</p>
                    </div>
                </div>

                <div v-if="viewerRole === 'etudiant' && booking.status === 'terminee'" class="tl-card p-7">
                    <h2 class="text-2xl font-bold">Évaluer le tuteur</h2>
                    <form class="mt-5 space-y-4" @submit.prevent="submitReview">
                        <select v-model="reviewForm.rating" class="tl-input w-full px-4 py-3 md:w-48">
                            <option v-for="rating in [5,4,3,2,1]" :key="rating" :value="rating">{{ rating }} étoile(s)</option>
                        </select>
                        <textarea v-model="reviewForm.comment" class="tl-input w-full px-4 py-3" rows="4" placeholder="Votre avis sur la séance..."></textarea>
                        <button class="tl-button-primary" :disabled="reviewForm.processing">Enregistrer l’avis</button>
                    </form>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="tl-card p-6">
                    <h2 class="text-2xl font-bold">Actions</h2>
                    <div class="mt-5 grid gap-3">
                        <Link v-if="booking.conversation_id" :href="route('messages.index', booking.conversation_id)" class="tl-button-primary w-full">Ouvrir la messagerie</Link>
                        <Link v-if="['en_attente', 'acceptee'].includes(booking.status)" :href="route('bookings.cancel', booking.id)" method="patch" as="button" class="rounded-lg border border-red-200 px-5 py-3 text-sm font-bold text-red-600 hover:bg-red-50">Annuler la réservation</Link>
                        <Link :href="viewerRole === 'tuteur' ? route('tutor.requests') : route('bookings.index')" class="tl-button-secondary w-full">Retour</Link>
                    </div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>