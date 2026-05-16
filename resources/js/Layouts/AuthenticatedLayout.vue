<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Bell,
    CalendarDays,
    ClipboardList,
    GraduationCap,
    Grid2X2,
    History,
    Mail,
    Menu,
    PhoneIncoming,
    PhoneOff,
    Search,
    Video,
    Settings,
    Star,
    UserRound,
    X,
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');
const notifications = computed(() => page.props.notifications || { unread_count: 0, recent: [] });
const liveUnreadCount = ref(0);
const mobileMenuOpen = ref(false);
const reportModalOpen = ref(false);
const liveNotice = ref(null);
const incomingCall = ref(null);
const incomingCallDeclining = ref(false);
const incomingCallJoining = ref(false);
const globalCallState = ref('idle');
const globalCallStatus = ref('');
const globalCallError = ref('');
const globalLocalVideo = ref(null);
const globalRemoteVideo = ref(null);
const globalRemoteAudio = ref(null);
let globalPeerConnection = null;
let globalLocalStream = null;
let globalRemoteStream = null;
let globalPendingCandidates = [];
let latestCallSignalId = 0;
let callSignalPoller = null;
const processedCallSignalIds = new Set();
const unreadNotificationsCount = computed(() => liveUnreadCount.value);
const incomingCallIcon = computed(() => incomingCall.value?.mode === 'video' ? Video : PhoneIncoming);
const globalCallTitle = computed(() => {
    if (!incomingCall.value) return '';
    if (globalCallState.value === 'incoming') return `${incomingCall.value.sender_name || 'Un utilisateur'} vous appelle`;
    if (globalCallState.value === 'connecting') return 'Connexion de l appel...';

    return `En appel avec ${incomingCall.value.sender_name || 'votre correspondant'}`;
});

const reportForm = useForm({
    subject: '',
    description: '',
});

watch(
    notifications,
    (value) => {
        liveUnreadCount.value = Number(value.unread_count || 0);
    },
    { immediate: true },
);

const showLiveNotice = (notification) => {
    if (!notification?.title) return;

    liveNotice.value = notification;

    window.setTimeout(() => {
        if (liveNotice.value?.id === notification.id) {
            liveNotice.value = null;
        }
    }, 8000);
};

const rememberCallSignal = (event) => {
    const signalId = Number(event?.signal_id || 0);

    if (!signalId) {
        return true;
    }

    latestCallSignalId = Math.max(latestCallSignalId, signalId);

    if (processedCallSignalIds.has(signalId)) {
        return false;
    }

    processedCallSignalIds.add(signalId);

    if (processedCallSignalIds.size > 200) {
        processedCallSignalIds.delete(processedCallSignalIds.values().next().value);
    }

    return true;
};

const attachGlobalStreams = async () => {
    await nextTick();

    if (globalLocalVideo.value) {
        globalLocalVideo.value.srcObject = globalLocalStream;
    }

    if (globalRemoteVideo.value) {
        globalRemoteVideo.value.srcObject = globalRemoteStream;
    }

    if (globalRemoteAudio.value) {
        globalRemoteAudio.value.srcObject = globalRemoteStream;
    }
};

const mediaErrorMessage = (error) => {
    if (error?.message === 'WEBRTC_UNAVAILABLE') {
        return 'Les appels exigent HTTPS et un navigateur compatible WebRTC.';
    }

    if (['NotAllowedError', 'PermissionDeniedError'].includes(error?.name)) {
        return 'Le navigateur refuse le micro ou la camera. Ouvrez le cadenas du site, autorisez micro/camera, puis rechargez la page.';
    }

    if (['NotFoundError', 'DevicesNotFoundError'].includes(error?.name)) {
        return 'Aucun micro ou camera utilisable n a ete trouve sur cet appareil.';
    }

    if (['NotReadableError', 'TrackStartError', 'OperationError', 'AbortError'].includes(error?.name)) {
        return 'Le micro ou la camera n a pas pu demarrer. Fermez les autres onglets ou applications qui utilisent la camera, puis reessayez.';
    }

    return `Impossible d allumer le micro ou la camera${error?.name ? ` (${error.name})` : ''}.`;
};

const requestGlobalLocalStream = async (mode) => {
    if (!window.RTCPeerConnection || !window.isSecureContext) {
        throw new Error('WEBRTC_UNAVAILABLE');
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        globalLocalStream = new MediaStream();
        incomingCall.value = { ...incomingCall.value, mode: 'audio' };
        globalCallStatus.value = 'Micro/camera indisponibles, appel lance en reception.';
        await attachGlobalStreams();
        return globalLocalStream;
    }

    const audio = {
        echoCancellation: true,
        noiseSuppression: true,
    };
    const video = {
        width: { ideal: 1280 },
        height: { ideal: 720 },
        facingMode: 'user',
    };
    const attempts = mode === 'video'
        ? [
            { audio, video },
            { audio: true, video: true },
            { audio, video: false },
            { audio: true, video: false },
        ]
        : [
            { audio, video: false },
            { audio: true, video: false },
        ];

    let lastError = null;

    for (const constraints of attempts) {
        try {
            globalLocalStream = await navigator.mediaDevices.getUserMedia(constraints);

            if (mode === 'video' && constraints.video === false) {
                incomingCall.value = { ...incomingCall.value, mode: 'audio' };
                globalCallStatus.value = 'Camera indisponible, appel audio lance.';
            }

            await attachGlobalStreams();
            return globalLocalStream;
        } catch (error) {
            lastError = error;
        }
    }

    globalLocalStream = new MediaStream();
    incomingCall.value = { ...incomingCall.value, mode: 'audio' };
    globalCallStatus.value = 'Micro/camera indisponibles, appel lance en reception.';
    console.warn('Media local indisponible', lastError);
    await attachGlobalStreams();

    return globalLocalStream;
};

const sendGlobalCallSignal = async (type, payload = {}) => {
    if (!incomingCall.value?.conversation_id) return;

    await window.axios.post(route('calls.signal', incomingCall.value.conversation_id), {
        type,
        mode: incomingCall.value.mode || 'audio',
        payload,
    }, { timeout: 10000 });
};

const cleanupGlobalCall = () => {
    if (globalPeerConnection) {
        globalPeerConnection.onicecandidate = null;
        globalPeerConnection.ontrack = null;
        globalPeerConnection.onconnectionstatechange = null;
        globalPeerConnection.close();
    }

    if (globalLocalStream) {
        globalLocalStream.getTracks().forEach((track) => track.stop());
    }

    globalPeerConnection = null;
    globalLocalStream = null;
    globalRemoteStream = null;
    globalPendingCandidates = [];
    incomingCall.value = null;
    incomingCallDeclining.value = false;
    incomingCallJoining.value = false;
    globalCallState.value = 'idle';
    globalCallStatus.value = '';
    globalCallError.value = '';

    void attachGlobalStreams();
};

const createGlobalPeerConnection = () => {
    if (globalPeerConnection) return globalPeerConnection;

    globalPeerConnection = new RTCPeerConnection({
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
        ],
    });

    globalPeerConnection.onicecandidate = (event) => {
        if (event.candidate) {
            void sendGlobalCallSignal('ice-candidate', { candidate: event.candidate.toJSON() });
        }
    };

    globalPeerConnection.ontrack = (event) => {
        globalRemoteStream = event.streams?.[0] || globalRemoteStream || new MediaStream();

        if (!event.streams?.length) {
            globalRemoteStream.addTrack(event.track);
        }

        globalCallState.value = 'active';
        globalCallStatus.value = 'Appel en cours';
        void attachGlobalStreams();
    };

    globalPeerConnection.onconnectionstatechange = () => {
        if (!globalPeerConnection) return;

        if (globalPeerConnection.connectionState === 'connected') {
            globalCallState.value = 'active';
            globalCallStatus.value = 'Appel en cours';
        }

        if (['failed', 'disconnected'].includes(globalPeerConnection.connectionState)) {
            globalCallStatus.value = 'Connexion interrompue';
        }
    };

    return globalPeerConnection;
};

const flushGlobalCandidates = async () => {
    if (!globalPeerConnection || !globalPeerConnection.remoteDescription) return;

    const candidates = [...globalPendingCandidates];
    globalPendingCandidates = [];

    for (const candidate of candidates) {
        try {
            await globalPeerConnection.addIceCandidate(new RTCIceCandidate(candidate));
        } catch (error) {
            console.warn('Candidat ICE ignore', error);
        }
    }
};

const addGlobalCandidate = async (candidate) => {
    if (!candidate) return;

    if (!globalPeerConnection || !globalPeerConnection.remoteDescription) {
        globalPendingCandidates.push(candidate);
        return;
    }

    try {
        await globalPeerConnection.addIceCandidate(new RTCIceCandidate(candidate));
    } catch (error) {
        console.warn('Candidat ICE ignore', error);
    }
};

const endGlobalCall = (notify = true) => {
    const shouldNotify = notify && incomingCall.value?.conversation_id && globalCallState.value !== 'idle';

    if (shouldNotify) {
        void sendGlobalCallSignal('call-end').catch((error) => console.warn('Fin d appel non envoyee', error));
    }

    cleanupGlobalCall();
};

const handleUserCallSignal = async (event) => {
    if (!event || Number(event.sender_id) === Number(user.value?.id)) {
        return;
    }

    if (!rememberCallSignal(event) || route().current('messages.index')) {
        return;
    }

    if (event.type !== 'call-offer' && incomingCall.value?.conversation_id && Number(event.conversation_id) !== Number(incomingCall.value.conversation_id)) {
        return;
    }

    if (event.type === 'call-offer') {
        if (!event.payload?.description) return;

        if (globalCallState.value !== 'idle') {
            if (incomingCall.value?.conversation_id && Number(incomingCall.value.conversation_id) === Number(event.conversation_id)) {
                return;
            }

            await window.axios.post(route('calls.signal', event.conversation_id), {
                type: 'call-decline',
                mode: event.mode || 'audio',
                payload: { reason: 'busy' },
            }, { timeout: 10000 }).catch((error) => console.warn('Signal occupe non envoye', error));
            return;
        }

        incomingCall.value = {
            ...event,
            payload: {
                ...(event.payload || {}),
                candidates: event.payload?.candidates || [],
            },
        };
        globalPendingCandidates = incomingCall.value.payload.candidates.filter(Boolean);
        incomingCallJoining.value = false;
        incomingCallDeclining.value = false;
        globalCallState.value = 'incoming';
        globalCallStatus.value = event.mode === 'video' ? 'Appel video entrant' : 'Appel audio entrant';
        globalCallError.value = '';
        return;
    }

    if (event.type === 'ice-candidate') {
        await addGlobalCandidate(event.payload?.candidate);
        return;
    }

    if (['call-answer', 'call-decline', 'call-end'].includes(event.type)) {
        cleanupGlobalCall();
    }
};

const acceptIncomingCall = async () => {
    if (!incomingCall.value?.payload?.description || incomingCallJoining.value) return;

    incomingCallJoining.value = true;
    globalCallError.value = '';
    globalCallState.value = 'connecting';
    globalCallStatus.value = 'Demande d acces au micro et a la camera...';
    mobileMenuOpen.value = false;

    try {
        await requestGlobalLocalStream(incomingCall.value.mode || 'audio');
        if (!globalCallStatus.value.includes('Camera indisponible')) {
            globalCallStatus.value = 'Connexion de l appel...';
        }

        const connection = createGlobalPeerConnection();
        globalLocalStream?.getTracks().forEach((track) => connection.addTrack(track, globalLocalStream));

        await connection.setRemoteDescription(new RTCSessionDescription(incomingCall.value.payload.description));
        await flushGlobalCandidates();

        const answer = await connection.createAnswer();
        await connection.setLocalDescription(answer);

        await sendGlobalCallSignal('call-answer', { description: connection.localDescription });

        globalCallState.value = 'active';
        globalCallStatus.value = 'Appel en cours';
    } catch (error) {
        globalCallError.value = mediaErrorMessage(error);
        globalCallState.value = 'incoming';
        incomingCallJoining.value = false;
    }
};

const declineIncomingCall = () => {
    if (!incomingCall.value || incomingCallDeclining.value) return;

    incomingCallDeclining.value = true;

    sendGlobalCallSignal('call-decline', { reason: 'declined' })
        .catch((error) => {
            console.warn('Refus d appel non envoye', error);
        })
        .finally(() => {
            cleanupGlobalCall();
        });
};

const pollPendingCallSignals = async () => {
    if (!user.value?.id || route().current('messages.index')) return;

    try {
        const response = await window.axios.get(route('calls.pending'), {
            params: { after: latestCallSignalId },
            timeout: 8000,
        });

        for (const signal of response.data?.signals || []) {
            await handleUserCallSignal(signal);
        }

        latestCallSignalId = Math.max(latestCallSignalId, Number(response.data?.latest_id || 0));
    } catch (error) {
        console.warn('Lecture des appels entrants impossible', error);
    }
};

onMounted(() => {
    if (!user.value?.id) {
        return;
    }

    if (window.Echo) {
        window.Echo.private(`users.${user.value.id}`)
            .listen('.notification.created', (event) => {
                liveUnreadCount.value += 1;
                showLiveNotice(event.notification);
            })
            .listen('.call.signal', handleUserCallSignal);
    }

    void pollPendingCallSignals();
    callSignalPoller = window.setInterval(pollPendingCallSignals, 2500);
});

onBeforeUnmount(() => {
    if (callSignalPoller) {
        window.clearInterval(callSignalPoller);
    }

    if (globalCallState.value === 'incoming') {
        void sendGlobalCallSignal('call-decline', { reason: 'declined' }).catch(() => {});
        cleanupGlobalCall();
    } else if (globalCallState.value !== 'idle') {
        endGlobalCall();
    }

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

const closeReportModal = () => {
    reportModalOpen.value = false;
    reportForm.reset();
    reportForm.clearErrors();
};

const submitReport = () => {
    reportForm.post(route('reports.store'), {
        preserveScroll: true,
        onSuccess: closeReportModal,
    });
};

const openReportModal = () => {
    mobileMenuOpen.value = false;
    reportModalOpen.value = true;
};
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
            <p v-if="isAdmin" class="mt-1 text-xs uppercase tracking-wide text-white/35">Console admin</p>

            <div v-if="user?.role === 'tuteur'" class="mt-10 rounded-lg p-4 text-center" :class="isAdmin ? 'bg-white/10' : 'bg-slate-50'">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-tutor-navy text-lg font-bold text-white ring-4 ring-slate-200/70">
                    {{ initials }}
                </div>
                <p class="mt-3 text-sm font-bold">{{ user.name }}</p>
                <p class="text-xs" :class="isAdmin ? 'text-white/50' : 'text-slate-500'">Statut : {{ user.status }}</p>
            </div>

            <nav class="mt-10 space-y-2">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="route(item.routeName)"
                    class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-bold transition"
                    :class="isNavActive(item)
                        ? 'bg-tutor-gold text-tutor-navy shadow-tutor'
                        : (isAdmin ? 'text-white/70 hover:bg-white/10 hover:text-white' : 'text-slate-700 hover:bg-tutor-gold/15 hover:text-tutor-navy')"
                >
                    <span class="relative grid size-5 place-items-center">
                        <component :is="iconMap[item.icon]" class="size-5" />
                        <span v-if="item.icon === 'notifications' && unreadNotificationsCount" class="absolute -right-1 -top-1 size-2 rounded-full bg-red-500"></span>
                    </span>
                    {{ item.label }}
                </Link>
            </nav>

            <div class="mt-auto rounded-lg p-3" :class="isAdmin ? 'bg-white/10' : 'bg-slate-50'">
                <button
                    v-if="!isAdmin"
                    type="button"
                    class="mb-3 flex w-full items-center justify-center gap-2 rounded-md border px-3 py-2 text-xs font-bold"
                    :class="isAdmin ? 'border-white/15 text-white/70' : 'border-slate-200 text-slate-600 hover:border-tutor-gold hover:text-tutor-navy'"
                    @click="openReportModal"
                >
                    <AlertCircle class="size-4" />
                    Signaler un problème
                </button>
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-full bg-tutor-gold text-sm font-bold text-tutor-navy">{{ initials }}</div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold">{{ user?.name }}</p>
                        <p class="truncate text-xs" :class="isAdmin ? 'text-white/45' : 'text-slate-500'">{{ user?.email }}</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Link :href="route('profile.edit')" class="rounded-md border px-3 py-2 text-center text-xs font-bold" :class="isAdmin ? 'border-white/15 text-white/70' : 'border-slate-200 text-slate-600'">Profil</Link>
                    <Link :href="route('logout')" method="post" as="button" class="rounded-md bg-tutor-gold px-3 py-2 text-xs font-bold text-tutor-navy">Se déconnecter</Link>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 px-4 py-4 backdrop-blur lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <Link :href="route('home')" class="text-xl font-bold text-tutor-navy"><span class="font-display">Tutor</span><span class="font-display text-[#9a6200]">Link</span></Link>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('notifications.index')"
                            class="relative grid size-10 place-items-center rounded-lg border transition"
                            :class="route().current('notifications.index') ? 'border-tutor-gold bg-tutor-gold text-tutor-navy' : 'border-slate-200 text-tutor-navy'"
                            title="Notifications"
                        >
                            <Bell class="size-5" />
                            <span v-if="unreadNotificationsCount" class="absolute right-2 top-2 size-2.5 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </Link>
                        <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-tutor-navy" @click="mobileMenuOpen = !mobileMenuOpen">
                            <X v-if="mobileMenuOpen" class="size-5" />
                            <Menu v-else class="size-5" />
                        </button>
                    </div>
                </div>

                <div v-if="mobileMenuOpen" class="mt-4 rounded-lg border border-slate-200 bg-white p-3 shadow-tutor">
                    <nav class="grid gap-2">
                        <Link
                            v-for="item in navItems"
                            :key="item.label"
                            :href="route(item.routeName)"
                            class="flex items-center gap-3 rounded-lg border px-3 py-3 text-sm font-bold transition"
                            :class="isNavActive(item) ? 'border-tutor-gold bg-tutor-gold text-tutor-navy' : 'border-slate-200 bg-white text-tutor-navy'"
                            @click="mobileMenuOpen = false"
                        >
                            <span class="relative grid size-5 place-items-center">
                                <component :is="iconMap[item.icon]" class="size-5" />
                                <span v-if="item.icon === 'notifications' && unreadNotificationsCount" class="absolute -right-1 -top-1 size-2 rounded-full bg-red-500"></span>
                            </span>
                            {{ item.label }}
                        </Link>
                    </nav>
                    <div class="mt-3 grid gap-2 border-t border-slate-100 pt-3">
                        <button v-if="!isAdmin" type="button" class="flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-3 text-sm font-bold text-slate-700" @click="openReportModal">
                            <AlertCircle class="size-4" />
                            Signaler un problème
                        </button>
                        <Link :href="route('logout')" method="post" as="button" class="rounded-lg bg-tutor-navy px-3 py-3 text-sm font-bold text-white">
                            Se déconnecter
                        </Link>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-8 lg:px-10 lg:py-8">
                <div v-if="liveNotice" class="mb-6 flex items-start justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <div>
                        <p class="font-bold">{{ liveNotice.title }}</p>
                        <p class="mt-1">{{ liveNotice.body }}</p>
                    </div>
                    <Link v-if="liveNotice.url" :href="liveNotice.url" class="shrink-0 rounded-md bg-tutor-navy px-3 py-2 text-xs font-bold text-white">Ouvrir</Link>
                </div>
                <div v-if="$page.props.flash?.success" class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $page.props.flash.error }}
                </div>
                <slot />
            </main>
        </div>

        <div v-if="incomingCall" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/75 px-4 py-6 backdrop-blur-sm">
            <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#9a6200]">{{ incomingCall.mode === 'video' ? 'Appel video' : 'Appel audio' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-tutor-navy">{{ globalCallTitle }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ globalCallStatus }}</p>
                    </div>
                    <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 transition hover:text-red-600" @click="globalCallState === 'incoming' ? declineIncomingCall() : endGlobalCall()">
                        <X class="size-4" />
                    </button>
                </div>

                <div v-if="globalCallError" class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-700">
                    {{ globalCallError }}
                </div>

                <div v-if="incomingCall.mode === 'video' && globalCallState !== 'incoming'" class="relative aspect-video bg-slate-950">
                    <video ref="globalRemoteVideo" autoplay playsinline class="h-full w-full object-cover"></video>
                    <div v-if="globalCallState !== 'active'" class="absolute inset-0 grid place-items-center bg-slate-950 text-center text-white">
                        <div>
                            <component :is="incomingCallIcon" class="mx-auto size-10 text-[#feae2c]" />
                            <p class="mt-4 text-sm font-semibold">{{ globalCallStatus }}</p>
                        </div>
                    </div>
                    <video ref="globalLocalVideo" autoplay muted playsinline class="absolute bottom-3 right-3 h-20 w-28 rounded-lg border border-white/30 bg-slate-900 object-cover shadow-xl sm:bottom-4 sm:right-4 sm:h-28 sm:w-40"></video>
                </div>

                <div v-else class="grid min-h-[280px] place-items-center bg-[#f7f8fb] px-6 py-10 text-center">
                    <div>
                        <div class="mx-auto grid size-24 place-items-center rounded-full bg-tutor-navy text-white">
                            <component :is="incomingCallIcon" class="size-10" />
                        </div>
                        <p class="mt-5 text-lg font-bold text-tutor-navy">{{ incomingCall.sender_name || 'Votre correspondant' }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ globalCallStatus }}</p>
                        <audio ref="globalRemoteAudio" autoplay playsinline></audio>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3 border-t border-slate-200 px-5 py-4">
                    <template v-if="globalCallState === 'incoming'">
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60" :disabled="incomingCallJoining || incomingCallDeclining" @click="acceptIncomingCall">
                            <component :is="incomingCallIcon" class="size-4" />
                            {{ incomingCallJoining ? 'Connexion...' : 'Accepter' }}
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-60" :disabled="incomingCallJoining || incomingCallDeclining" @click="declineIncomingCall">
                            <PhoneOff class="size-4" />
                            {{ incomingCallDeclining ? 'Refus...' : 'Refuser' }}
                        </button>
                    </template>
                    <button v-else type="button" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700" @click="endGlobalCall()">
                        <PhoneOff class="size-4" />
                        Raccrocher
                    </button>
                </div>
            </div>
        </div>

        <div v-if="reportModalOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm">
            <form class="w-full max-w-lg rounded-lg bg-white p-6 shadow-2xl" @submit.prevent="submitReport">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#9a6200]">Signalement</p>
                        <h2 class="mt-1 text-2xl font-bold text-tutor-navy">Décrire un problème</h2>
                    </div>
                    <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500" @click="closeReportModal">
                        <X class="size-4" />
                    </button>
                </div>

                <label class="mt-6 block text-sm font-bold text-slate-700" for="report-subject">Sujet</label>
                <input id="report-subject" v-model="reportForm.subject" maxlength="255" class="tl-input mt-2 w-full px-4 py-3" required placeholder="Exemple : problème de réservation" />
                <p v-if="reportForm.errors.subject" class="mt-2 text-sm text-red-600">{{ reportForm.errors.subject }}</p>

                <label class="mt-5 block text-sm font-bold text-slate-700" for="report-description">Description</label>
                <textarea id="report-description" v-model="reportForm.description" maxlength="3000" rows="5" class="tl-input mt-2 w-full px-4 py-3" required placeholder="Expliquez clairement ce qui s’est passé."></textarea>
                <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                    <span>Minimum conseillé : 20 mots.</span>
                    <span>{{ reportForm.description.length }}/3000 caractères</span>
                </div>
                <p v-if="reportForm.errors.description" class="mt-2 text-sm text-red-600">{{ reportForm.errors.description }}</p>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700" @click="closeReportModal">Annuler</button>
                    <button type="submit" class="tl-button-primary" :disabled="reportForm.processing">Envoyer le signalement</button>
                </div>
            </form>
        </div>
    </div>
</template>
