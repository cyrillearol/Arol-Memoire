<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ availabilities: { type: Array, default: () => [] } });

const weekdays = { 1: 'Lundi', 2: 'Mardi', 3: 'Mercredi', 4: 'Jeudi', 5: 'Vendredi', 6: 'Samedi', 7: 'Dimanche' };
const editingId = ref(null);

const form = useForm({ weekday: 1, start_time: '08:00', end_time: '09:00', is_available: true });
const editForm = useForm({ weekday: 1, start_time: '08:00', end_time: '09:00', is_available: true });

const submit = () => form.post(route('availabilities.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset('weekday', 'start_time', 'end_time'),
});

const startEdit = (availability) => {
    editingId.value = availability.id;
    editForm.weekday = availability.weekday;
    editForm.start_time = availability.start_time;
    editForm.end_time = availability.end_time;
    editForm.is_available = availability.is_available ?? true;
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.clearErrors();
};

const updateAvailability = (availability) => {
    editForm.patch(route('availabilities.update', availability.id), {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
};

const deleteAvailability = (availability) => {
    if (!window.confirm(`Supprimer la disponibilité du ${weekdays[availability.weekday]} de ${availability.start_time} à ${availability.end_time} ?`)) {
        return;
    }

    router.delete(route('availabilities.destroy', availability.id), { preserveScroll: true });
};
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
                <select v-model="form.weekday" class="tl-input px-4 py-3" required>
                    <option v-for="(label, day) in weekdays" :key="day" :value="Number(day)">{{ label }}</option>
                </select>
                <input v-model="form.start_time" type="time" class="tl-input px-4 py-3" required />
                <input v-model="form.end_time" type="time" class="tl-input px-4 py-3" required />
                <button class="tl-button-primary" :disabled="form.processing">{{ form.processing ? 'Ajout...' : 'Ajouter' }}</button>
            </form>
            <div class="mt-3 grid gap-2 text-sm text-red-600">
                <p v-if="form.errors.weekday">{{ form.errors.weekday }}</p>
                <p v-if="form.errors.start_time">{{ form.errors.start_time }}</p>
                <p v-if="form.errors.end_time">{{ form.errors.end_time }}</p>
            </div>
        </div>

        <div v-if="availabilities.length" class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="availability in availabilities" :key="availability.id" class="tl-card p-5">
                <form v-if="editingId === availability.id" class="space-y-4" @submit.prevent="updateAvailability(availability)">
                    <select v-model="editForm.weekday" class="tl-input w-full px-4 py-3" required>
                        <option v-for="(label, day) in weekdays" :key="day" :value="Number(day)">{{ label }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="editForm.start_time" type="time" class="tl-input px-4 py-3" required />
                        <input v-model="editForm.end_time" type="time" class="tl-input px-4 py-3" required />
                    </div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <input v-model="editForm.is_available" type="checkbox" class="rounded border-slate-300 text-[#9a6200] focus:ring-[#9a6200]" />
                        Créneau disponible
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600" @click="cancelEdit">Annuler</button>
                        <button class="tl-button-primary px-4 py-2 text-sm" :disabled="editForm.processing">Enregistrer</button>
                    </div>
                    <div class="text-sm text-red-600">
                        <p v-if="editForm.errors.end_time">{{ editForm.errors.end_time }}</p>
                    </div>
                </form>

                <template v-else>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xl font-bold text-tutor-navy">{{ weekdays[availability.weekday] }}</p>
                            <p class="mt-2 text-slate-600">{{ availability.start_time }} - {{ availability.end_time }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="availability.is_available ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                            {{ availability.is_available ? 'Disponible' : 'Masqué' }}
                        </span>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" @click="startEdit(availability)">Modifier</button>
                        <button type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50" @click="deleteAvailability(availability)">Supprimer</button>
                    </div>
                </template>
            </article>
        </div>

        <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">Aucune disponibilité définie.</div>
    </AuthenticatedLayout>
</template>
