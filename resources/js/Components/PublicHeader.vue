<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    active: {
        type: String,
        default: 'home',
    },
});

const nav = [
    { id: 'home', label: 'Accueil', href: 'home' },
    { id: 'tutors', label: 'Tuteurs', href: 'tutors.index' },
    { id: 'how', label: 'Comment ça marche', anchor: '/#fonctionnement' },
    { id: 'pricing', label: 'Tarifs', anchor: '/#tarifs' },
];
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-8">
            <Link :href="route('home')" class="text-xl font-bold text-tutor-navy">
                <span class="font-display">Tutor</span><span class="font-display text-[#9a6200]">Link</span>
            </Link>

            <nav class="hidden items-center gap-8 text-sm font-semibold text-slate-600 md:flex">
                <component
                    :is="item.anchor ? 'a' : Link"
                    v-for="item in nav"
                    :key="item.id"
                    :href="item.anchor || route(item.href)"
                    class="border-b-2 py-6 transition hover:text-tutor-navy"
                    :class="active === item.id ? 'border-tutor-gold text-tutor-navy' : 'border-transparent'"
                >
                    {{ item.label }}
                </component>
            </nav>

            <div class="flex items-center gap-3 text-sm font-bold">
                <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="tl-button-secondary px-4 py-2">Tableau de bord</Link>
                <template v-else>
                    <Link :href="route('login')" class="hidden rounded-lg px-4 py-2 text-tutor-navy transition hover:bg-slate-100 sm:inline-flex">Connexion</Link>
                    <Link :href="route('register')" class="tl-button-primary px-4 py-2">S’inscrire</Link>
                </template>
            </div>
        </div>
    </header>
</template>
