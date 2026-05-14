<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Info, LoaderCircle, Paperclip, Phone, PhoneOff, Send, Video, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    selectedConversation: Object,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const canCall = computed(() => props.selectedConversation?.booking?.status === 'acceptee');
const otherUserName = computed(() => props.selectedConversation?.other_user?.name || 'votre correspondant');
const hasActiveCall = computed(() => callState.value !== 'idle');
const callTitle = computed(() => {
    if (callState.value === 'incoming') return `${otherUserName.value} vous appelle`;
    if (callState.value === 'outgoing') return `Appel vers ${otherUserName.value}`;
    return `En appel avec ${otherUserName.value}`;
});

let activeConversationChannel = null;
let peerConnection = null;
let localStream = null;
let remoteStream = null;
let pendingRemoteCandidates = [];

const callState = ref('idle');
const callMode = ref('audio');
const callStatus = ref('');
const callError = ref('');
const incomingOffer = ref(null);
const callConversationId = ref(null);
const localVideo = ref(null);
const remoteVideo = ref(null);
const remoteAudio = ref(null);

const iceServers = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
];

const form = useForm({
    body: '',
    attachment: null,
});

const setCallError = (message) => {
    callError.value = message;

    window.setTimeout(() => {
        if (callError.value === message) {
            callError.value = '';
        }
    }, 7000);
};

const attachStreams = async () => {
    await nextTick();

    if (localVideo.value) {
        localVideo.value.srcObject = localStream;
    }

    if (remoteVideo.value) {
        remoteVideo.value.srcObject = remoteStream;
    }

    if (remoteAudio.value) {
        remoteAudio.value.srcObject = remoteStream;
    }
};

const sendCallSignal = async (type, payload = {}, mode = callMode.value, conversationId = callConversationId.value || props.selectedConversation?.id) => {
    if (!conversationId) return;

    await window.axios.post(route('calls.signal', conversationId), {
        type,
        mode,
        payload,
    });
};

const cleanupCall = () => {
    if (peerConnection) {
        peerConnection.onicecandidate = null;
        peerConnection.ontrack = null;
        peerConnection.onconnectionstatechange = null;
        peerConnection.close();
    }

    if (localStream) {
        localStream.getTracks().forEach((track) => track.stop());
    }

    peerConnection = null;
    localStream = null;
    remoteStream = null;
    pendingRemoteCandidates = [];
    incomingOffer.value = null;
    callConversationId.value = null;
    callState.value = 'idle';
    callMode.value = 'audio';
    callStatus.value = '';

    void attachStreams();
};

const endCall = async (conversationId = callConversationId.value || props.selectedConversation?.id, notify = true) => {
    const shouldNotify = notify && conversationId && callState.value !== 'idle';

    try {
        if (shouldNotify) {
            await sendCallSignal('call-end', {}, callMode.value, conversationId);
        }
    } finally {
        cleanupCall();
    }
};

const requestLocalStream = async (mode) => {
    if (!navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection) {
        throw new Error('WEBRTC_UNAVAILABLE');
    }

    localStream = await navigator.mediaDevices.getUserMedia({
        audio: {
            echoCancellation: true,
            noiseSuppression: true,
        },
        video: mode === 'video'
            ? {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'user',
            }
            : false,
    });

    await attachStreams();

    return localStream;
};

const createPeerConnection = () => {
    if (peerConnection) return peerConnection;

    peerConnection = new RTCPeerConnection({ iceServers });

    peerConnection.onicecandidate = (event) => {
        if (event.candidate) {
            void sendCallSignal('ice-candidate', {
                candidate: event.candidate.toJSON(),
            });
        }
    };

    peerConnection.ontrack = (event) => {
        remoteStream = event.streams?.[0] || remoteStream || new MediaStream();

        if (!event.streams?.length) {
            remoteStream.addTrack(event.track);
        }

        callState.value = 'active';
        callStatus.value = 'Appel en cours';
        void attachStreams();
    };

    peerConnection.onconnectionstatechange = () => {
        if (!peerConnection) return;

        if (peerConnection.connectionState === 'connected') {
            callState.value = 'active';
            callStatus.value = 'Appel en cours';
        }

        if (['failed', 'disconnected'].includes(peerConnection.connectionState)) {
            callStatus.value = 'Connexion interrompue';
        }
    };

    return peerConnection;
};

const addLocalTracks = (connection) => {
    localStream.getTracks().forEach((track) => connection.addTrack(track, localStream));
};

const flushRemoteCandidates = async () => {
    if (!peerConnection || !peerConnection.remoteDescription) return;

    const candidates = [...pendingRemoteCandidates];
    pendingRemoteCandidates = [];

    for (const candidate of candidates) {
        try {
            await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
        } catch (error) {
            console.warn('Candidat ICE ignoré', error);
        }
    }
};

const addRemoteCandidate = async (candidate) => {
    if (!candidate) return;

    if (!peerConnection || !peerConnection.remoteDescription) {
        pendingRemoteCandidates.push(candidate);
        return;
    }

    try {
        await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
    } catch (error) {
        console.warn('Candidat ICE ignoré', error);
    }
};

const startCall = async (mode) => {
    if (!props.selectedConversation || callState.value !== 'idle') return;

    if (!canCall.value) {
        setCallError('Les appels sont disponibles uniquement après acceptation de la réservation.');
        return;
    }

    callError.value = '';
    callMode.value = mode;
    callConversationId.value = props.selectedConversation.id;
    callState.value = 'outgoing';
    callStatus.value = 'En attente de réponse...';

    try {
        await requestLocalStream(mode);
        const connection = createPeerConnection();
        addLocalTracks(connection);

        const offer = await connection.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: mode === 'video',
        });

        await connection.setLocalDescription(offer);
        await sendCallSignal('call-offer', {
            description: connection.localDescription,
        }, mode);
    } catch (error) {
        cleanupCall();
        setCallError(error.message === 'WEBRTC_UNAVAILABLE'
            ? 'Le navigateur bloque le micro ou la caméra. Utilisez localhost, 127.0.0.1 ou HTTPS.'
            : 'Impossible de démarrer l’appel. Vérifiez l’autorisation du micro ou de la caméra.');
    }
};

const acceptCall = async () => {
    if (!incomingOffer.value || !props.selectedConversation) return;

    callError.value = '';
    callStatus.value = 'Connexion de l’appel...';

    try {
        await requestLocalStream(callMode.value);
        const connection = createPeerConnection();
        addLocalTracks(connection);

        await connection.setRemoteDescription(new RTCSessionDescription(incomingOffer.value));
        await flushRemoteCandidates();

        const answer = await connection.createAnswer();
        await connection.setLocalDescription(answer);

        await sendCallSignal('call-answer', {
            description: connection.localDescription,
        });

        callState.value = 'active';
        callStatus.value = 'Appel en cours';
    } catch (error) {
        await endCall(undefined, true);
        setCallError(error.message === 'WEBRTC_UNAVAILABLE'
            ? 'Le navigateur bloque le micro ou la caméra. Utilisez localhost, 127.0.0.1 ou HTTPS.'
            : 'Impossible d’accepter l’appel. Vérifiez l’autorisation du micro ou de la caméra.');
    }
};

const declineCall = async () => {
    const conversationId = callConversationId.value || props.selectedConversation?.id;

    try {
        await sendCallSignal('call-decline', { reason: 'declined' }, callMode.value, conversationId);
    } finally {
        cleanupCall();
    }
};

const handleCallSignal = async (event) => {
    if (!event || event.sender_id === currentUserId.value) return;

    if (event.type === 'call-offer') {
        if (callState.value !== 'idle') {
            await sendCallSignal('call-decline', { reason: 'busy' }, event.mode || 'audio', event.conversation_id);
            return;
        }

        callMode.value = event.mode || 'audio';
        callConversationId.value = event.conversation_id || props.selectedConversation?.id;
        incomingOffer.value = event.payload?.description || null;
        callState.value = 'incoming';
        callStatus.value = event.mode === 'video' ? 'Appel vidéo entrant' : 'Appel audio entrant';
        return;
    }

    if (event.type === 'call-answer') {
        if (!peerConnection || !event.payload?.description) return;

        await peerConnection.setRemoteDescription(new RTCSessionDescription(event.payload.description));
        await flushRemoteCandidates();
        callState.value = 'active';
        callStatus.value = 'Appel en cours';
        return;
    }

    if (event.type === 'ice-candidate') {
        await addRemoteCandidate(event.payload?.candidate);
        return;
    }

    if (event.type === 'call-decline') {
        const message = event.payload?.reason === 'busy'
            ? 'Votre correspondant est déjà en appel.'
            : 'L’appel a été refusé.';

        cleanupCall();
        setCallError(message);
        return;
    }

    if (event.type === 'call-end') {
        cleanupCall();
        setCallError('L’appel est terminé.');
    }
};

const leaveConversationChannel = () => {
    if (window.Echo && activeConversationChannel) {
        window.Echo.leave(`conversations.${activeConversationChannel}`);
    }

    activeConversationChannel = null;
};

const subscribeToConversation = () => {
    leaveConversationChannel();

    if (!window.Echo || !props.selectedConversation?.id) {
        return;
    }

    activeConversationChannel = props.selectedConversation.id;

    window.Echo.private(`conversations.${activeConversationChannel}`)
        .listen('.message.sent', () => {
            router.reload({
                only: ['conversations', 'selectedConversation'],
                preserveScroll: true,
            });
        })
        .listen('.call.signal', handleCallSignal);
};

watch(() => props.selectedConversation?.id, (newId, oldId) => {
    if (oldId && oldId !== newId && callState.value !== 'idle') {
        void endCall(oldId);
    }

    subscribeToConversation();
}, { immediate: true });

watch([callState, callMode], () => {
    if (callState.value !== 'idle') {
        void attachStreams();
    }
});

onBeforeUnmount(() => {
    if (callState.value !== 'idle') {
        void endCall();
    } else {
        cleanupCall();
    }

    leaveConversationChannel();
});

const send = () => {
    if (!props.selectedConversation) return;

    form.post(route('messages.store', props.selectedConversation.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body', 'attachment');
        },
    });
};

const onFile = (event) => {
    form.attachment = event.target.files?.[0] || null;
};
</script>

<template>
    <Head title="Messages" />

    <AuthenticatedLayout>
        <div class="grid min-h-[calc(100vh-5rem)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-tutor lg:grid-cols-[320px_1fr_300px]">
            <aside class="border-r border-slate-200 bg-white">
                <div class="border-b border-slate-200 p-6">
                    <h1 class="text-3xl font-bold">Messages</h1>
                    <input class="tl-input mt-4 w-full px-4 py-3" placeholder="Rechercher une discussion" />
                </div>

                <div v-if="conversations.length" class="divide-y divide-slate-100">
                    <Link
                        v-for="conversation in conversations"
                        :key="conversation.id"
                        :href="route('messages.index', conversation.id)"
                        class="flex gap-3 px-5 py-4 transition hover:bg-tutor-surface"
                        :class="selectedConversation?.id === conversation.id ? 'border-l-4 border-tutor-gold bg-[#fff7e8]' : ''"
                    >
                        <div class="relative grid size-12 place-items-center rounded-full bg-tutor-navy font-bold text-white">
                            {{ conversation.other_user?.name?.charAt(0) || 'U' }}
                            <span class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-white bg-emerald-500"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate font-bold text-tutor-navy">{{ conversation.other_user?.name }}</p>
                                <span class="shrink-0 text-xs text-slate-400">{{ conversation.last_message_at }}</span>
                            </div>
                            <p class="truncate text-sm text-slate-600">{{ conversation.last_message || 'Aucun message' }}</p>
                        </div>
                    </Link>
                </div>
                <div v-else class="p-8 text-center text-sm text-slate-600">
                    Aucune conversation. La messagerie s’active après acceptation d’une réservation.
                </div>
            </aside>

            <section class="flex min-h-[640px] flex-col bg-white">
                <template v-if="selectedConversation">
                    <header class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="grid size-12 place-items-center rounded-full bg-tutor-navy font-bold text-white">
                                {{ selectedConversation.other_user?.name?.charAt(0) || 'U' }}
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-tutor-navy">{{ selectedConversation.other_user?.name }}</h2>
                                <p class="text-sm text-slate-500">{{ selectedConversation.other_user?.domain || selectedConversation.other_user?.role }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2 text-tutor-navy">
                            <button
                                type="button"
                                title="Appel audio"
                                class="grid size-10 place-items-center rounded-lg border border-slate-200 transition hover:border-[#9a6200] hover:text-[#9a6200] disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!canCall || hasActiveCall"
                                @click="startCall('audio')"
                            >
                                <Phone class="size-4" />
                            </button>
                            <button
                                type="button"
                                title="Appel vidéo"
                                class="grid size-10 place-items-center rounded-lg border border-slate-200 transition hover:border-[#9a6200] hover:text-[#9a6200] disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!canCall || hasActiveCall"
                                @click="startCall('video')"
                            >
                                <Video class="size-4" />
                            </button>
                            <button type="button" title="Informations" class="grid size-10 place-items-center rounded-lg border border-slate-200 transition hover:border-[#9a6200] hover:text-[#9a6200]">
                                <Info class="size-4" />
                            </button>
                        </div>
                    </header>

                    <div v-if="callError" class="border-b border-amber-200 bg-amber-50 px-6 py-3 text-sm font-semibold text-amber-800">
                        {{ callError }}
                    </div>

                    <div class="flex-1 space-y-5 overflow-y-auto bg-[#fbfcfd] p-6">
                        <div
                            v-for="message in selectedConversation.messages"
                            :key="message.id"
                            class="flex"
                            :class="message.is_mine ? 'justify-end' : 'justify-start'"
                        >
                            <div class="max-w-[78%] rounded-lg px-4 py-3 text-sm leading-6" :class="message.is_mine ? 'bg-tutor-navy text-white' : 'bg-slate-100 text-tutor-ink'">
                                <p v-if="message.body">{{ message.body }}</p>
                                <a v-if="message.attachment_url" :href="message.attachment_url" class="mt-2 block font-bold underline">Document joint</a>
                                <p class="mt-2 text-right text-xs" :class="message.is_mine ? 'text-white/55' : 'text-slate-400'">{{ message.created_at }}</p>
                            </div>
                        </div>
                        <div v-if="!selectedConversation.messages.length" class="grid h-full place-items-center text-center text-slate-500">
                            <p>Aucun message dans cette conversation.</p>
                        </div>
                    </div>

                    <form class="border-t border-slate-200 bg-white p-5" @submit.prevent="send">
                        <div class="flex items-center gap-3 rounded-lg bg-slate-100 p-2">
                            <label class="grid size-11 cursor-pointer place-items-center rounded-lg text-slate-500 transition hover:bg-white hover:text-[#9a6200]">
                                <Paperclip class="size-5" />
                                <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="onFile" />
                            </label>
                            <input v-model="form.body" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-3 text-sm focus:ring-0" placeholder="Écrivez votre message..." />
                            <button class="grid size-11 place-items-center rounded-lg bg-[#9a6200] font-bold text-white disabled:opacity-50" :disabled="form.processing">
                                <Send class="size-5" />
                            </button>
                        </div>
                        <p v-if="form.attachment" class="mt-2 text-xs font-semibold text-slate-500">Document sélectionné : {{ form.attachment.name }}</p>
                        <p v-if="form.errors.body" class="mt-2 text-sm text-red-600">{{ form.errors.body }}</p>
                    </form>
                </template>

                <div v-else class="grid flex-1 place-items-center p-10 text-center">
                    <div>
                        <h2 class="text-3xl font-bold">Aucune conversation</h2>
                        <p class="mt-3 max-w-md text-slate-600">Les échanges sont disponibles uniquement après validation d’une réservation.</p>
                        <Link :href="route('tutors.index')" class="tl-button-primary mt-6">Trouver un tuteur</Link>
                    </div>
                </div>
            </section>

            <aside class="hidden border-l border-slate-200 bg-white p-6 lg:block">
                <template v-if="selectedConversation">
                    <div class="text-center">
                        <div class="mx-auto grid size-24 place-items-center rounded-full bg-tutor-navy text-3xl font-bold text-white">
                            {{ selectedConversation.other_user?.name?.charAt(0) || 'U' }}
                        </div>
                        <h2 class="mt-5 text-2xl font-bold">{{ selectedConversation.other_user?.name }}</h2>
                        <p class="text-sm text-[#9a6200]">{{ selectedConversation.other_user?.domain || selectedConversation.other_user?.role }}</p>
                    </div>

                    <div v-if="selectedConversation.booking" class="mt-8 rounded-lg bg-tutor-navy p-5 text-white">
                        <p class="text-xs font-bold uppercase tracking-wide text-white/60">Prochain cours</p>
                        <h3 class="mt-3 text-xl font-bold text-white">{{ selectedConversation.booking.scheduled_label }}</h3>
                        <p class="mt-1 text-sm text-white/70">{{ selectedConversation.booking.subject }}</p>
                    </div>

                    <div class="mt-8">
                        <h3 class="font-bold text-tutor-navy">Fichiers partagés</h3>
                        <div class="mt-4 space-y-3 text-sm text-slate-600">
                            <a
                                v-for="message in selectedConversation.messages.filter((item) => item.attachment_url)"
                                :key="message.id"
                                :href="message.attachment_url"
                                class="block rounded-lg bg-tutor-surface px-3 py-2 font-semibold"
                            >
                                Document joint
                            </a>
                            <p v-if="!selectedConversation.messages.some((item) => item.attachment_url)" class="text-slate-500">Aucun fichier partagé.</p>
                        </div>
                    </div>

                    <div class="mt-8 space-y-4 text-sm font-bold">
                        <button class="block text-slate-600">Notifications muettes</button>
                        <button class="block text-red-600">Signaler un problème</button>
                    </div>
                </template>
            </aside>
        </div>

        <div v-if="hasActiveCall" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/75 px-4 py-6 backdrop-blur-sm">
            <div class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#9a6200]">{{ callMode === 'video' ? 'Appel vidéo' : 'Appel audio' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-tutor-navy">{{ callTitle }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ callStatus }}</p>
                    </div>
                    <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 transition hover:text-red-600" @click="endCall()">
                        <X class="size-4" />
                    </button>
                </div>

                <div v-if="callMode === 'video'" class="relative aspect-video bg-slate-950">
                    <video ref="remoteVideo" autoplay playsinline class="h-full w-full object-cover"></video>
                    <div v-if="callState !== 'active'" class="absolute inset-0 grid place-items-center bg-slate-950 text-center text-white">
                        <div>
                            <LoaderCircle class="mx-auto size-8 animate-spin text-[#feae2c]" />
                            <p class="mt-4 text-sm font-semibold">{{ callStatus }}</p>
                        </div>
                    </div>
                    <video ref="localVideo" autoplay muted playsinline class="absolute bottom-4 right-4 h-28 w-40 rounded-lg border border-white/30 bg-slate-900 object-cover shadow-xl"></video>
                </div>

                <div v-else class="grid min-h-[280px] place-items-center bg-[#f7f8fb] px-6 py-10 text-center">
                    <div>
                        <div class="mx-auto grid size-24 place-items-center rounded-full bg-tutor-navy text-3xl font-bold text-white">
                            {{ selectedConversation?.other_user?.name?.charAt(0) || 'U' }}
                        </div>
                        <p class="mt-5 text-lg font-bold text-tutor-navy">{{ otherUserName }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ callStatus }}</p>
                        <audio ref="remoteAudio" autoplay playsinline></audio>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3 border-t border-slate-200 px-5 py-4">
                    <template v-if="callState === 'incoming'">
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-700" @click="acceptCall">
                            <Check class="size-4" />
                            Accepter
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700" @click="declineCall">
                            <PhoneOff class="size-4" />
                            Refuser
                        </button>
                    </template>
                    <button v-else type="button" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-700" @click="endCall()">
                        <PhoneOff class="size-4" />
                        Raccrocher
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>