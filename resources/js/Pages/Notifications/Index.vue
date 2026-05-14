<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Bell, CheckCheck, ExternalLink } from 'lucide-vue-next';

defineProps({
    items: {
        type: Object,
        required: true,
    },
});

const toneClass = (tone) => ({
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    danger: 'border-red-200 bg-red-50 text-red-800',
    info: 'border-blue-200 bg-blue-50 text-tutor-navy',
}[tone] || 'border-slate-200 bg-white text-tutor-navy');
</script>

<template>
    <Head title="Notifications" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <span class="grid size-12 place-items-center rounded-lg bg-tutor-navy text-white">
                        <Bell class="size-6" />
                    </span>
                    <div>
                        <h1 class="text-4xl font-bold">Notifications</h1>
                        <p class="mt-1 text-slate-600">Suivez vos réservations, validations, messages et signalements.</p>
                    </div>
                </div>

                <Link :href="route('notifications.read-all')" method="patch" as="button" class="tl-button-secondary">
                    <CheckCheck class="size-4" />
                    Tout marquer comme lu
                </Link>
            </div>

            <div v-if="items.data.length" class="mt-8 space-y-4">
                <article
                    v-for="notification in items.data"
                    :key="notification.id"
                    class="rounded-lg border p-5 shadow-tutor transition hover:-translate-y-0.5 hover:shadow-tutor-strong"
                    :class="toneClass(notification.tone)"
                >
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <span v-if="!notification.read_at" class="size-2.5 rounded-full bg-tutor-gold"></span>
                                <h2 class="text-xl font-bold">{{ notification.title }}</h2>
                            </div>
                            <p class="mt-3 max-w-3xl text-sm leading-6 opacity-80">{{ notification.body }}</p>
                            <p class="mt-3 text-xs font-semibold opacity-60">{{ notification.created_at }}</p>
                        </div>

                        <div class="flex shrink-0 gap-2">
                            <a v-if="notification.url" :href="notification.url" class="tl-button-secondary px-4 py-2 text-xs">
                                <ExternalLink class="size-4" />
                                Ouvrir
                            </a>
                            <Link v-if="!notification.read_at" :href="route('notifications.read', notification.id)" method="patch" as="button" class="tl-button-primary px-4 py-2 text-xs">
                                Lu
                            </Link>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center">
                <Bell class="mx-auto size-10 text-slate-400" />
                <h2 class="mt-4 text-2xl font-bold">Aucune notification</h2>
                <p class="mt-2 text-slate-600">Les nouvelles alertes apparaîtront ici.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>