<script setup>
import { Link } from '@inertiajs/vue3';

const tutorSessionImage = '/images/tutor-online-session.jpg';
</script>

<template>
    <main class="min-h-screen bg-tutor-surface text-tutor-ink lg:grid lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-white px-10 py-10 text-tutor-ink lg:flex lg:flex-col">
            <Link :href="route('home')" class="relative z-10 inline-flex items-center text-2xl font-bold text-tutor-navy">
                <span class="font-display">Tutor</span><span class="font-display text-tutor-gold">Link</span>
            </Link>

            <div class="relative z-10 my-auto max-w-xl">
                <h1 class="max-w-lg text-6xl font-bold leading-tight text-tutor-navy">Votre réussite commence ici</h1>
                <p class="mt-6 max-w-md text-xl leading-8 text-slate-700">
                    Rejoignez un espace de tutorat fiable, clair et pensé pour apprendre, réserver et échanger sans friction.
                </p>

                <div class="mt-10 overflow-hidden rounded-lg shadow-tutor-strong ring-1 ring-slate-200">
                    <img :src="tutorSessionImage" alt="Séance de tutorat en ligne" class="aspect-[4/2.5] h-full w-full object-cover" />
                </div>
            </div>

            <p class="relative z-10 text-xs text-slate-400">© 2026 TutorLink. Excellence académique et réussite professionnelle.</p>
        </section>

        <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-[520px]">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <Link :href="route('home')" class="text-2xl font-bold text-tutor-navy">
                        <span class="font-display">Tutor</span><span class="font-display text-[#9a6200]">Link</span>
                    </Link>
                    <Link :href="route('home')" class="tl-button-secondary px-4 py-2">Accueil</Link>
                </div>

                <div class="tl-card p-7 sm:p-10">
                    <slot />
                </div>
            </div>
        </section>
    </main>
</template>
