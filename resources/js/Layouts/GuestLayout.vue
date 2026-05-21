<script setup>
import { Link } from '@inertiajs/vue3';

const tutorSessionImage = '/images/tutor-online-session.jpg';
</script>

<template>
    <main class="min-h-screen bg-tutor-surface text-tutor-ink lg:grid lg:h-screen lg:grid-cols-2 lg:overflow-hidden">
        <section class="relative hidden h-screen overflow-hidden bg-white px-10 py-10 text-tutor-ink lg:grid lg:grid-rows-[auto_minmax(0,1fr)_auto]">
            <Link :href="route('home')" class="relative z-10 inline-flex items-center text-2xl font-bold text-tutor-navy">
                <span class="font-display">Tutor</span><span class="font-display text-tutor-gold">Link</span>
            </Link>

            <div class="relative z-10 flex min-h-0 max-w-xl flex-col justify-center py-6">
                <h1 class="max-w-lg text-5xl font-bold leading-tight text-tutor-navy xl:text-6xl">Votre réussite commence ici</h1>
                <p class="mt-5 max-w-md text-lg leading-7 text-slate-700 xl:text-xl xl:leading-8">
                    Rejoignez un espace de tutorat fiable, clair et pensé pour apprendre, réserver et échanger sans friction.
                </p>

                <div class="mt-8 aspect-[3/2] w-full max-w-[520px] overflow-hidden rounded-lg bg-slate-50 shadow-tutor-strong ring-1 ring-slate-200">
                    <img :src="tutorSessionImage" alt="Séance de tutorat en ligne" class="h-full w-full object-cover" />
                </div>
            </div>

            <p class="relative z-10 text-xs text-slate-400">© 2026 TutorLink. Excellence académique et réussite professionnelle.</p>
        </section>

        <section class="flex min-h-screen justify-center overflow-y-auto px-4 sm:px-8 lg:h-screen">
            <div class="my-auto w-full max-w-[520px] py-6 sm:py-10">
                <div class="mb-6 flex items-center justify-between gap-4 lg:hidden">
                    <Link :href="route('home')" class="text-2xl font-bold text-tutor-navy">
                        <span class="font-display">Tutor</span><span class="font-display text-[#9a6200]">Link</span>
                    </Link>
                    <Link :href="route('home')" class="tl-button-secondary px-4 py-2">Accueil</Link>
                </div>

                <div class="tl-card p-5 sm:p-10">
                    <slot />
                </div>
            </div>
        </section>
    </main>
</template>
