<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Grid2X2, Search, CalendarDays, Mail, History, UserRound, Settings, Star, ClipboardList, AlertCircle, GraduationCap } from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');
const notifications = computed(() => page.props.notifications || { unread_count: 0, recent: [] });
const liveUnreadCount = ref(0);
const unreadNotificationsCount = computed(() => liveUnreadCount.value);

watch(
    notifications,
    (value) => {
        liveUnreadCount.value = Number(value.unread_count || 0);
    },
    { immediate: true },
);

onMounted(() => {
    if (!window.Echo || !user.value?.id) {
        return;
    }

    window.Echo.private(`users.${user.value.id}`).listen('.notification.created', () => {
        liveUnreadCount.value += 1;
    });
});

onBeforeUnmount(() => {
    if (window.Echo && user.value?.id) {
        window.Echo.leave(`users.${user.value.id}`);
    }
});
const iconMap = {
    dashboard: Grid2X2,
    tutors: Search,
    bookings: CalendarDays,
    messages: Mail,
    history: History,
    profile: UserRound,
    settings: Settings,
    reviews: Star,
    requests: ClipboardList,
    reports: AlertCircle,
    students: GraduationCap,
    notifications: Bell,
};

const navItems = computed(() => {
    const notificationItem = {
        label: 'Notifications',
        routeName: 'notifications.index',
        icon: 'notifications',
        activeRoutes: ['notifications.index'],
    };

    if (user.value?.role === 'admin') {
        return [
            { label: 'Vue d’ensemble', routeName: 'dashboard', icon: 'dashboard', activeRoutes: ['dashboard'] },
            { label: 'Tuteurs', routeName: 'admin.tutors.index', icon: 'tutors', activeRoutes: ['admin.tutors.index'] },
            { label: 'Étudiants', routeName: 'admin.students.index', icon: 'students', activeRoutes: ['admin.students.index'] },
            { label: 'Signalements', routeName: 'admin.reports.index', icon: 'reports', activeRoutes: ['admin.reports.index'] },
            notificationItem,
            { label: 'Messages', routeName: 'messages.index', icon: 'messages', activeRoutes: ['messages.index'] },
            { label: 'Paramètres', routeName: 'profile.edit', icon: 'settings', activeRoutes: ['profile.edit'] },
        ];
    }

    if (user.value?.role === 'tuteur') {
        return [
            { label: 'Tableau de bord', routeName: 'dashboard', icon: 'dashboard', activeRoutes: ['dashboard'] },
            { label: 'Disponibilités', routeName: 'tutor.availabilities', icon: 'bookings', activeRoutes: ['tutor.availabilities'] },
            { label: 'Demandes', routeName: 'tutor.requests', icon: 'requests', activeRoutes: ['tutor.requests', 'bookings.show'] },
            notificationItem,
            { label: 'Messages', routeName: 'messages.index', icon: 'messages', activeRoutes: ['messages.index'] },
            { label: 'Évaluations', routeName: 'tutor.reviews', icon: 'reviews', activeRoutes: ['tutor.reviews'] },
            { label: 'Mon profil', routeName: 'profile.edit', icon: 'profile', activeRoutes: ['profile.edit'] },
        ];
    }

    return [
        { label: 'Tableau de bord', routeName: 'dashboard', icon: 'dashboard', activeRoutes: ['dashboard'] },
        { label: 'Trouver un tuteur', routeName: 'tutors.index', icon: 'tutors', activeRoutes: ['tutors.index', 'tutors.show', 'bookings.create'] },
        { label: 'Mes réservations', routeName: 'bookings.index', icon: 'bookings', activeRoutes: ['bookings.index', 'bookings.show'] },
        notificationItem,
        { label: 'Mes messages', routeName: 'messages.index', icon: 'messages', activeRoutes: ['messages.index'] },
        { label: 'Historique', routeName: 'bookings.history', icon: 'history', activeRoutes: ['bookings.history'] },
        { label: 'Mon profil', routeName: 'profile.edit', icon: 'profile', activeRoutes: ['profile.edit'] },
    ];
});

const initials = computed(() => (user.value?.name || 'U').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase());

const isNavActive = (item) => (item.activeRoutes || [item.routeName]).some((routeName) => route().current(routeName));
</script>

<template>
    <div class="min-h-screen bg-tutor-surface text-tutor-ink lg:flex">
        <aside
            class="hidden min-h-screen w-64 shrink-0 border-r border-slate-200 px-6 py-7 lg:flex lg:flex-col"
            :class="isAdmin ? 'border-[#0f365e] bg-tutor-navy text-white' : 'bg-white text-tutor-ink'"
        >
            <div class="flex items-center justify-between gap-4">
                <Link :href="route('home')" class="text-2xl font-bold" :class="isAdmin ? 'text-white' : 'text-tutor-navy'">
                    <span class="font-display">Tutor</span><span class="font-display text-tutor-gold">Link</span>
                </Link>
                <Link
                    :href="route('notifications.index')"
                    class="relative grid size-10 place-items-center rounded-lg border transition"
                    :class="route().current('notifications.index') ? 'border-tutor-gold bg-tutor-gold text-tutor-navy' : (isAdmin ? 'border-white/15 text-white hover:bg-white/10' : 'border-slate-200 text-tutor-navy hover:bg-slate-50')"
                    title="Notifications"
                >
                    <Bell class="size-5" />
                    <span v-if="unreadNotificationsCount" class="absolute right-2 top-2 size-2.5 rounded-full bg-red-500 ring-2" :class="isAdmin ? 'ring-tutor-navy' : 'ring-white'"></span>
                </Link>
            </div>
            <p v-if="isAdmin" class="mt-1 text-xs uppercase tracking-wide text-white/35">Admin console</p>

            <div v-if="user?.role === 'tuteur'" class="mt-10 rounded-lg p-4 text-center" :class="isAdmin ? 'bg-white/10' : 'bg-slate-50'">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-tutor-navy text-lg font-bold text-white ring-4 ring-slate-200/70">
                    {{ initials }}
                </div>
                <p class="mt-3 text-sm font-bold">{{ user.name }}</p>
                <p class="text-xs" :class="isAdmin ? 'text-white/50' : 'text-slate-500'">Statut: {{ user.status }}</p>
            </div>

            <nav class="mt-10 space-y-2">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="route(item.routeName)"
                    class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-bold transition"
                    :class="isNavActive(item)
                        ? 'bg-tutor-gold text-tutor-navy shadow-tutor'
                        : (isAdmin ? 'text-white/52 hover:bg-white/10 hover:text-white' : 'text-slate-700 hover:bg-tutor-gold/15 hover:text-tutor-navy')"
                >
                    <span class="relative grid size-5 place-items-center">
                        <component :is="iconMap[item.icon]" class="size-5" />
                        <span v-if="item.icon === 'notifications' && unreadNotificationsCount" class="absolute -right-1 -top-1 size-2 rounded-full bg-red-500"></span>
                    </span>
                    {{ item.label }}
                </Link>
            </nav>

            <div class="mt-auto rounded-lg p-3" :class="isAdmin ? 'bg-white/10' : 'bg-slate-50'">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-full bg-tutor-gold text-sm font-bold text-tutor-navy">{{ initials }}</div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold">{{ user?.name }}</p>
                        <p class="truncate text-xs" :class="isAdmin ? 'text-white/45' : 'text-slate-500'">{{ user?.email }}</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Link :href="route('profile.edit')" class="rounded-md border px-3 py-2 text-center text-xs font-bold" :class="isAdmin ? 'border-white/15 text-white/70' : 'border-slate-200 text-slate-600'">Profil</Link>
                    <Link :href="route('logout')" method="post" as="button" class="rounded-md bg-tutor-gold px-3 py-2 text-xs font-bold text-tutor-navy">Sortir</Link>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/92 px-5 py-4 backdrop-blur lg:hidden">
                <div class="flex items-center justify-between">
                    <Link :href="route('home')" class="text-xl font-bold text-tutor-navy"><span class="font-display">Tutor</span><span class="font-display text-[#9a6200]">Link</span></Link>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('notifications.index')"
                            class="relative grid size-10 place-items-center rounded-lg border transition"
                            :class="route().current('notifications.index') ? 'border-tutor-gold bg-tutor-gold text-tutor-navy' : 'border-slate-200 text-tutor-navy'"
                        >
                            <Bell class="size-5" />
                            <span v-if="unreadNotificationsCount" class="absolute right-2 top-2 size-2.5 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="tl-button-primary px-4 py-2">Sortir</Link>
                    </div>
                </div>
                <nav class="mt-4 flex gap-2 overflow-x-auto pb-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.label"
                        :href="route(item.routeName)"
                        class="shrink-0 rounded-lg border px-3 py-2 text-xs font-bold transition"
                        :class="isNavActive(item) ? 'border-tutor-gold bg-tutor-gold text-tutor-navy' : 'border-slate-200 bg-white text-tutor-navy'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </header>

            <main class="px-5 py-8 sm:px-8 lg:px-10">
                <div v-if="$page.props.flash?.success" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $page.props.flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
