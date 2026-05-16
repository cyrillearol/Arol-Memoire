<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    name: '',
    email: '',
    role: 'etudiant',
    domain: '',
    subjects: '',
    hourly_rate: '',
    bio: '',
    documents: [],
    password: '',
    password_confirmation: '',
});

const subjectCount = computed(() => form.subjects.split(',').map((subject) => subject.trim()).filter(Boolean).length);
const bioLength = computed(() => form.bio.length);
const bioWords = computed(() => form.bio.trim().split(/\s+/).filter(Boolean).length);
const documentCount = computed(() => form.documents.length);

const updateDocuments = (event) => {
    form.documents = Array.from(event.target.files ?? []);
};

const submit = () => {
    form.post(route('register'), {
        forceFormData: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Inscription" />

        <div class="text-center">
            <h1 class="text-4xl font-bold">Créer votre compte</h1>
            <p class="mt-3 text-base leading-6 text-slate-600">Choisissez votre rôle et complétez uniquement les informations demandées.</p>
        </div>

        <form class="mt-7" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nom complet" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-2" required autofocus autocomplete="name" maxlength="255" placeholder="Votre nom" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div class="mt-5">
                <InputLabel for="email" value="Adresse e-mail" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-2" required autocomplete="username" maxlength="255" placeholder="nom@exemple.com" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-5">
                <InputLabel value="Type de compte" />
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-lg border p-4 transition" :class="form.role === 'etudiant' ? 'border-tutor-gold bg-[#fff7e8]' : 'border-slate-200 bg-white hover:border-tutor-gold/70'">
                        <input v-model="form.role" class="sr-only" type="radio" value="etudiant" />
                        <span class="block font-bold text-tutor-navy">Étudiant</span>
                        <span class="mt-1 block text-sm text-slate-600">Je cherche un accompagnement.</span>
                    </label>
                    <label class="cursor-pointer rounded-lg border p-4 transition" :class="form.role === 'tuteur' ? 'border-tutor-gold bg-[#fff7e8]' : 'border-slate-200 bg-white hover:border-tutor-gold/70'">
                        <input v-model="form.role" class="sr-only" type="radio" value="tuteur" />
                        <span class="block font-bold text-tutor-navy">Tuteur</span>
                        <span class="mt-1 block text-sm text-slate-600">Je veux proposer des séances.</span>
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.role" />
            </div>

            <div v-if="form.role === 'tuteur'" class="mt-6 rounded-lg border border-slate-200 bg-tutor-surface p-5">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-[#9a6200]">Candidature tuteur</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Votre profil restera en attente jusqu’à validation par l’administrateur.</p>
                </div>

                <div class="mt-5">
                    <InputLabel for="domain" value="Domaine de compétence" />
                    <TextInput id="domain" v-model="form.domain" type="text" class="mt-2" :required="form.role === 'tuteur'" maxlength="255" placeholder="Mathématiques, informatique, anglais..." />
                    <p class="mt-2 text-xs text-slate-500">255 caractères maximum.</p>
                    <InputError class="mt-2" :message="form.errors.domain" />
                </div>

                <div class="mt-5">
                    <InputLabel for="subjects" value="Matières enseignées" />
                    <TextInput id="subjects" v-model="form.subjects" type="text" class="mt-2" :required="form.role === 'tuteur'" maxlength="1000" placeholder="Algèbre, statistiques, programmation" />
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>Séparez les matières par des virgules.</span>
                        <span>{{ subjectCount }} matière(s)</span>
                    </div>
                    <InputError class="mt-2" :message="form.errors.subjects" />
                </div>

                <div class="mt-5">
                    <InputLabel for="hourly_rate" value="Tarif horaire en FCFA" />
                    <TextInput id="hourly_rate" v-model="form.hourly_rate" type="number" min="0" step="0.01" class="mt-2" :required="form.role === 'tuteur'" placeholder="5000" />
                    <InputError class="mt-2" :message="form.errors.hourly_rate" />
                </div>

                <div class="mt-5">
                    <InputLabel for="bio" value="Présentation professionnelle" />
                    <textarea id="bio" v-model="form.bio" :required="form.role === 'tuteur'" rows="5" maxlength="2000" class="tl-input mt-2 w-full px-4 py-3" placeholder="Décrivez votre expérience, votre méthode et le type d’accompagnement proposé."></textarea>
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>Minimum 30 caractères, idéalement 30 mots ou plus.</span>
                        <span>{{ bioLength }}/2000 caractères · {{ bioWords }} mot(s)</span>
                    </div>
                    <InputError class="mt-2" :message="form.errors.bio" />
                </div>

                <div class="mt-5">
                    <InputLabel for="documents" value="Documents justificatifs" />
                    <input id="documents" type="file" multiple :required="form.role === 'tuteur'" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="mt-2 block w-full rounded-lg border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-600 file:me-4 file:rounded-lg file:border-0 file:bg-tutor-navy file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:border-tutor-gold" @change="updateDocuments" />
                    <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                        <span>PDF, image ou document Word. Maximum 5 fichiers de 5 Mo.</span>
                        <span>{{ documentCount }}/5 fichier(s)</span>
                    </div>
                    <InputError class="mt-2" :message="form.errors.documents" />
                    <InputError class="mt-2" :message="form.errors['documents.0']" />
                </div>
            </div>

            <div class="mt-5">
                <InputLabel for="password" value="Mot de passe" />
                <TextInput id="password" v-model="form.password" type="password" maxlength="15" class="mt-2" required autocomplete="new-password" placeholder="••••••••" />
                <p class="mt-2 text-xs text-slate-500">Entre 8 et 15 caractères.</p>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-5">
                <InputLabel for="password_confirmation" value="Confirmer le mot de passe" />
                <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" maxlength="15" class="mt-2" required autocomplete="new-password" placeholder="••••••••" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton class="mt-7 w-full" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                {{ form.processing ? 'Création du compte...' : 'Créer mon compte' }}
            </PrimaryButton>
            <p class="mt-7 text-center text-sm text-slate-600">
                Déjà inscrit ?
                <Link :href="route('login')" class="font-bold text-[#9a6200] hover:text-tutor-navy">Connectez-vous ici</Link>
            </p>
        </form>
    </GuestLayout>
</template>
