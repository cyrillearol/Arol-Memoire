<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ availabilities: { type: Array, default: () => [] } });

const form = useForm({ weekday: 1, start_time: '08:00', end_time: '09:00', is_available: true });
const weekdays = { 1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi', 5: 'Vendredi', 6: 'Samedi', 7: 'Dimanche' };
const submit = () => form.post(route('availabilities.store'), { preserveScroll: true });
</script>

<template>
    <Head title="Disponibilités" />

    <AuthenticatedLayout>
        <div>
            <h1 class="text-4xl font-bold">Disponibilités</h1>
            <p class="mt-2 text-slate-600">Définissez les créneaux que les étudiants pourront réserver.</p>
        </div>

        <div class="tl-card mt-8 p-6">
            <form class="grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto]" @submit.prevent="submit">
                <select v-model="form.weekday" class="tl-input px-4 py-3">
                    <option v-for="(label, day) in weekdays" :key="day" :value="Number(day)">{{ label }}</option>
                </select>
                <input v-model="form.start_time" type="time" class="tl-input px-4 py-3" />
                <input v-model="form.end_time" type="time" class="tl-input px-4 py-3" />
                <button class="tl-button-primary" :disabled="form.processing">Ajouter</button>
            </form>
        </div>

        <div v-if="availabilities.length" class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="availability in availabilities" :key="availability.id" class="tl-card p-5">
                <p class="text-xl font-bold text-tutor-navy">{{ weekdays[availability.weekday] }}</p>
                <p class="mt-2 text-slate-600">{{ availability.start_time }} - {{ availability.end_time }}</p>
                <Link :href="route('availabilities.destroy', availability.id)" method="delete" as="button" class="mt-5 rounded-lg border border-red-200 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50">Supprimer</Link>
            </article>
        </div>

        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucune disponibilité définie.</div>
    </AuthenticatedLayout>
</template>