<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Check, Info, LoaderCircle, Paperclip, Phone, PhoneOff, Send, Video, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    selectedConversation: Object,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const currentUserName = computed(() => page.props.auth?.user?.name || 'Moi');
const localConversations = ref([]);
const localMessages = ref([]);
const messageBody = ref('');
const messageAttachment = ref(null);
const messageErrors = ref({});
const sendingMessage = ref(false);
const fileInput = ref(null);
const messagesPanel = ref(null);

const canCall = computed(() => Boolean(props.selectedConversation?.can_call));
const activeCallConversation = computed(() => {
    const targetId = callConversationId.value || props.selectedConversation?.id;

    return localConversations.value.find((conversation) => Number(conversation.id) === Number(targetId))
        || props.selectedConversation
        || null;
});
const otherUserName = computed(() => activeCallConversation.value?.other_user?.name || 'votre correspondant');
const callAvatarLetter = computed(() => otherUserName.value?.charAt(0) || 'U');
const hasActiveCall = computed(() => callState.value !== 'idle');
const callTitle = computed(() => {
    if (callState.value === 'incoming') return `${otherUserName.value} vous appelle`;
    if (callState.value === 'outgoing') return `Appel vers ${otherUserName.value}`;
    return `En appel avec ${otherUserName.value}`;
});

let subscribedConversationIds = new Set();
let peerConnection = null;
let localStream = null;
let remoteStream = null;
let pendingRemoteCandidates = [];
let latestCallSignalId = 0;
let callSignalPoller = null;
const processedCallSignalIds = new Set();

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

const nowTime = () => new Intl.DateTimeFormat('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
}).format(new Date());

const scrollMessagesToBottom = () => {
    void nextTick(() => {
        if (messagesPanel.value) {
            messagesPanel.value.scrollTop = messagesPanel.value.scrollHeight;
        }
    });
};

const normalizeMessage = (message) => ({
    ...message,
    is_mine: Number(message.sender_id) === Number(currentUserId.value),
});

const syncMessages = () => {
    localMessages.value = (props.selectedConversation?.messages || []).map(normalizeMessage);
    scrollMessagesToBottom();
};

const updateConversationPreview = (conversationId, message) => {
    const index = localConversations.value.findIndex((conversation) => Number(conversation.id) === Number(conversationId));

    if (index === -1) return;

    const existing = localConversations.value[index];
    const preview = message.body || (message.attachment_url || message.attachment_name ? 'Document joint' : 'Message');
    const updated = {
        ...existing,
        last_message: preview,
        last_message_at: message.created_at || 'maintenant',
    };

    localConversations.value.splice(index, 1);
    localConversations.value.unshift(updated);
};

const appendLocalMessage = (message) => {
    const normalized = normalizeMessage(message);

    if (localMessages.value.some((item) => String(item.id) === String(normalized.id))) {
        return;
    }

    localMessages.value.push(normalized);
    scrollMessagesToBottom();
};

const replaceLocalMessage = (temporaryId, message) => {
    const normalized = normalizeMessage(message);
    const index = localMessages.value.findIndex((item) => String(item.id) === String(temporaryId));
    const existingIndex = localMessages.value.findIndex((item) => String(item.id) === String(normalized.id));

    if (index === -1) {
        appendLocalMessage(normalized);
        return;
    }

    if (existingIndex !== -1 && existingIndex !== index) {
        localMessages.value.splice(index, 1);
        scrollMessagesToBottom();
        return;
    }

    localMessages.value.splice(index, 1, normalized);
    scrollMessagesToBottom();
};

const markMessageAsFailed = (temporaryId) => {
    const index = localMessages.value.findIndex((item) => String(item.id) === String(temporaryId));

    if (index !== -1) {
        localMessages.value.splice(index, 1, {
            ...localMessages.value[index],
            pending: false,
            failed: true,
        });
    }
};

const handleMessageSent = (event) => {
    if (!event?.message) return;

    const message = normalizeMessage(event.message);
    const conversationId = event.conversation_id || message.conversation_id;

    if (Number(conversationId) === Number(props.selectedConversation?.id)) {
        appendLocalMessage(message);
    }

    updateConversationPreview(conversationId, message);
};

const setCallError = (message) => {
    callError.value = message;

    window.setTimeout(() => {
        if (callError.value === message) {
            callError.value = '';
        }
    }, 7000);
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
    }, { timeout: 10000 });
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

const sendCallSignalInBackground = (type, payload = {}, mode = callMode.value, conversationId = callConversationId.value || props.selectedConversation?.id) => {
    void sendCallSignal(type, payload, mode, conversationId).catch((error) => {
        console.warn('Signal d’appel non envoyé', error);
    });
};

const endCall = (conversationId = callConversationId.value || props.selectedConversation?.id, notify = true) => {
    const shouldNotify = notify && conversationId && callState.value !== 'idle';
    const mode = callMode.value;

    cleanupCall();

    if (shouldNotify) {
        sendCallSignalInBackground('call-end', {}, mode, conversationId);
    }
};

const requestLocalStream = async (mode) => {
    if (!window.RTCPeerConnection || !window.isSecureContext) {
        throw new Error('WEBRTC_UNAVAILABLE');
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        localStream = new MediaStream();
        callMode.value = 'audio';
        callStatus.value = 'Micro/camera indisponibles, appel lance en reception.';
        await attachStreams();
        return localStream;
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
            localStream = await navigator.mediaDevices.getUserMedia(constraints);

            if (mode === 'video' && constraints.video === false) {
                callMode.value = 'audio';
                callStatus.value = 'Camera indisponible, appel audio lance.';
            }

            await attachStreams();
            return localStream;
        } catch (error) {
            lastError = error;
        }
    }

    localStream = new MediaStream();
    callMode.value = 'audio';
    callStatus.value = 'Micro/camera indisponibles, appel lance en reception.';
    console.warn('Media local indisponible', lastError);
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
    localStream?.getTracks().forEach((track) => connection.addTrack(track, localStream));
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

    let mediaReady = false;

    try {
        await requestLocalStream(mode);
        mediaReady = true;

        const connection = createPeerConnection();
        addLocalTracks(connection);

        const effectiveMode = callMode.value;
        const offer = await connection.createOffer({
            offerToReceiveAudio: true,
            offerToReceiveVideo: effectiveMode === 'video',
        });

        await connection.setLocalDescription(offer);
        await sendCallSignal('call-offer', {
            description: connection.localDescription,
        }, effectiveMode);
    } catch (error) {
        cleanupCall();
        setCallError(!mediaReady || error.message === 'WEBRTC_UNAVAILABLE'
            ? mediaErrorMessage(error)
            : 'L appel n a pas pu etre transmis. Verifiez Pusher ou votre connexion.');
    }
};

const acceptCall = async () => {
    if (!incomingOffer.value) return;

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
        endCall(undefined, true);
        setCallError(mediaErrorMessage(error));
    }
};

const declineCall = () => {
    const conversationId = callConversationId.value || props.selectedConversation?.id;
    const mode = callMode.value;

    cleanupCall();

    if (conversationId) {
        sendCallSignalInBackground('call-decline', { reason: 'declined' }, mode, conversationId);
    }
};

const handleCallSignal = async (event) => {
    if (!event || Number(event.sender_id) === Number(currentUserId.value)) return;
    if (!rememberCallSignal(event)) return;

    const eventConversationId = event.conversation_id || null;

    if (event.type !== 'call-offer' && callConversationId.value && eventConversationId && Number(eventConversationId) !== Number(callConversationId.value)) {
        return;
    }

    if (event.type === 'call-offer') {
        if (callState.value !== 'idle') {
            if (callConversationId.value && Number(callConversationId.value) === Number(eventConversationId)) {
                return;
            }

            await sendCallSignal('call-decline', { reason: 'busy' }, event.mode || 'audio', eventConversationId);
            return;
        }

        if (!event.payload?.description) return;

        callMode.value = event.mode || 'audio';
        callConversationId.value = eventConversationId || props.selectedConversation?.id;
        incomingOffer.value = event.payload.description;

        const queuedCandidates = Array.isArray(event.payload?.candidates)
            ? event.payload.candidates.filter(Boolean)
            : [];

        if (queuedCandidates.length) {
            pendingRemoteCandidates.push(...queuedCandidates);
        }
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

const readPendingIncomingCall = () => {
    try {
        const raw = window.sessionStorage?.getItem('tutorlink:incoming-call');
        if (!raw) return null;

        window.sessionStorage?.removeItem('tutorlink:incoming-call');
        const event = JSON.parse(raw);

        if (!event?.conversation_id || Date.now() - Number(event.stored_at || 0) > 60000) {
            return null;
        }

        return event;
    } catch (error) {
        console.warn('Appel entrant non restaure', error);
        return null;
    }
};

const restorePendingIncomingCall = async () => {
    const event = readPendingIncomingCall();
    if (!event) return;

    await nextTick();
    await handleCallSignal(event);

    if (event.auto_accept) {
        await nextTick();
        void acceptCall();
    }
};

const initializeCallSignalCursor = async () => {
    if (!currentUserId.value) return;

    try {
        const response = await window.axios.get(route('calls.pending'), {
            params: { after: latestCallSignalId, initial: true },
            timeout: 8000,
        });

        latestCallSignalId = Math.max(latestCallSignalId, Number(response.data?.latest_id || 0));
    } catch (error) {
        console.warn('Initialisation des signaux d appel impossible', error);
    }
};

const pollPendingCallSignals = async () => {
    if (!currentUserId.value) return;

    try {
        const response = await window.axios.get(route('calls.pending'), {
            params: { after: latestCallSignalId },
            timeout: 8000,
        });

        for (const signal of response.data?.signals || []) {
            await handleCallSignal(signal);
        }

        latestCallSignalId = Math.max(latestCallSignalId, Number(response.data?.latest_id || 0));
    } catch (error) {
        console.warn('Lecture des signaux d appel impossible', error);
    }
};

const leaveConversationChannels = () => {
    if (!window.Echo) return;

    subscribedConversationIds.forEach((conversationId) => {
        window.Echo.leave(`conversations.${conversationId}`);
    });

    subscribedConversationIds = new Set();
};

const subscribeToConversations = () => {
    if (!window.Echo) return;

    const ids = new Set(
        localConversations.value
            .map((conversation) => conversation.id)
            .filter(Boolean),
    );

    if (props.selectedConversation?.id) {
        ids.add(props.selectedConversation.id);
    }

    subscribedConversationIds.forEach((conversationId) => {
        if (!ids.has(conversationId)) {
            window.Echo.leave(`conversations.${conversationId}`);
            subscribedConversationIds.delete(conversationId);
        }
    });

    ids.forEach((conversationId) => {
        if (subscribedConversationIds.has(conversationId)) return;

        window.Echo.private(`conversations.${conversationId}`)
            .listen('.message.sent', handleMessageSent)
            .listen('.call.signal', handleCallSignal);

        subscribedConversationIds.add(conversationId);
    });
};

watch(() => props.conversations, (value) => {
    localConversations.value = (value || []).map((conversation) => ({ ...conversation }));
    subscribeToConversations();
}, { immediate: true, deep: true });

watch(() => props.selectedConversation?.id, (newId, oldId) => {
    if (oldId && oldId !== newId && callState.value !== 'idle' && Number(callConversationId.value) === Number(oldId)) {
        void endCall(oldId);
    }

    syncMessages();
    subscribeToConversations();
}, { immediate: true });

watch(() => props.selectedConversation?.messages, syncMessages, { deep: true });

watch([callState, callMode], () => {
    if (callState.value !== 'idle') {
        void attachStreams();
    }
});

onMounted(() => {
    void restorePendingIncomingCall();
    void initializeCallSignalCursor().then(() => {
        void pollPendingCallSignals();
        callSignalPoller = window.setInterval(pollPendingCallSignals, 2500);
    });
});

onBeforeUnmount(() => {
    if (callSignalPoller) {
        window.clearInterval(callSignalPoller);
    }

    if (callState.value !== 'idle') {
        void endCall();
    } else {
        cleanupCall();
    }

    leaveConversationChannels();
});

const send = async () => {
    if (!props.selectedConversation) return;

    const body = messageBody.value.trim();
    const attachment = messageAttachment.value;

    if (!body && !attachment) return;

    const conversationId = props.selectedConversation.id;
    const temporaryId = `temp-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    const optimisticMessage = {
        id: temporaryId,
        conversation_id: conversationId,
        body: body || null,
        attachment_url: null,
        attachment_name: attachment?.name || null,
        sender_id: currentUserId.value,
        sender_name: currentUserName.value,
        is_mine: true,
        created_at: nowTime(),
        pending: true,
    };

    appendLocalMessage(optimisticMessage);
    updateConversationPreview(conversationId, optimisticMessage);

    const payload = new FormData();
    if (body) payload.append('body', body);
    if (attachment) payload.append('attachment', attachment);

    messageBody.value = '';
    messageAttachment.value = null;
    messageErrors.value = {};
    if (fileInput.value) fileInput.value.value = '';

    sendingMessage.value = true;

    try {
        const response = await window.axios.post(route('messages.store', conversationId), payload, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (response.data?.message) {
            replaceLocalMessage(temporaryId, response.data.message);
            updateConversationPreview(conversationId, response.data.message);
        }
    } catch (error) {
        markMessageAsFailed(temporaryId);
        messageErrors.value = error.response?.data?.errors || {
            body: ['Le message n’a pas pu être envoyé. Vérifiez votre connexion.'],
        };
    } finally {
        sendingMessage.value = false;
    }
};

const onFile = (event) => {
    messageAttachment.value = event.target.files?.[0] || null;
};
</script>

<template>
    <Head title="Messages" />

    <AuthenticatedLayout>
        <div class="grid min-h-[calc(100svh-7rem)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-tutor lg:grid-cols-[320px_1fr_300px]">
            <aside class="max-h-64 overflow-y-auto border-b border-slate-200 bg-white sm:max-h-80 lg:max-h-none lg:border-b-0 lg:border-r">
                <div class="border-b border-slate-200 p-4 sm:p-6">
                    <h1 class="text-3xl font-bold">Messages</h1>
                    <input class="tl-input mt-4 w-full px-4 py-3" placeholder="Rechercher une discussion" />
                </div>

                <div v-if="localConversations.length" class="divide-y divide-slate-100">
                    <Link
                        v-for="conversation in localConversations"
                        :key="conversation.id"
                        :href="route('messages.index', conversation.id)"
                        class="flex gap-3 px-4 py-4 transition hover:bg-tutor-surface sm:px-5"
                        :class="selectedConversation?.id === conversation.id ? 'border-l-4 border-tutor-gold bg-[#fff7e8]' : ''"
                    >
                        <div class="relative grid size-11 shrink-0 place-items-center rounded-full bg-tutor-navy font-bold text-white sm:size-12">
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

            <section class="flex min-h-[calc(100svh-22rem)] flex-col bg-white sm:min-h-[70vh] lg:min-h-[640px]">
                <template v-if="selectedConversation">
                    <header class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="grid size-12 place-items-center rounded-full bg-tutor-navy font-bold text-white">
                                {{ selectedConversation.other_user?.name?.charAt(0) || 'U' }}
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-tutor-navy">{{ selectedConversation.other_user?.name }}</h2>
                                <p class="text-sm text-slate-500">{{ selectedConversation.other_user?.domain || selectedConversation.other_user?.role }}</p>
                            </div>
                        </div>
                        <div class="flex w-full shrink-0 justify-end gap-2 text-tutor-navy md:w-auto">
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

                    <div ref="messagesPanel" class="min-h-0 flex-1 space-y-5 overflow-y-auto bg-[#fbfcfd] p-3 sm:p-6">
                        <div
                            v-for="message in localMessages"
                            :key="message.id"
                            class="flex"
                            :class="message.is_mine ? 'justify-end' : 'justify-start'"
                        >
                            <div class="max-w-[88%] break-words rounded-lg px-4 py-3 text-sm leading-6 sm:max-w-[78%]" :class="message.is_mine ? 'bg-tutor-navy text-white' : 'bg-slate-100 text-tutor-ink'">
                                <p v-if="message.body">{{ message.body }}</p>
                                <a v-if="message.attachment_url" :href="message.attachment_url" class="mt-2 block font-bold underline">Document joint</a>
                                <p v-else-if="message.attachment_name" class="mt-2 font-bold">Document joint : {{ message.attachment_name }}</p>
                                <p class="mt-2 text-right text-xs" :class="message.failed ? 'text-red-200' : (message.is_mine ? 'text-white/55' : 'text-slate-400')">{{ message.failed ? 'Non envoyé' : (message.pending ? 'Envoi...' : message.created_at) }}</p>
                            </div>
                        </div>
                        <div v-if="!localMessages.length" class="grid h-full place-items-center text-center text-slate-500">
                            <p>Aucun message dans cette conversation.</p>
                        </div>
                    </div>

                    <form class="sticky bottom-0 border-t border-slate-200 bg-white p-3 sm:p-5" @submit.prevent="send">
                        <div class="flex items-center gap-2 rounded-lg bg-slate-100 p-2 sm:gap-3">
                            <label class="grid size-11 cursor-pointer place-items-center rounded-lg text-slate-500 transition hover:bg-white hover:text-[#9a6200]">
                                <Paperclip class="size-5" />
                                <input ref="fileInput" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="onFile" />
                            </label>
                            <input v-model="messageBody" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-3 text-sm focus:ring-0" placeholder="Écrivez votre message..." />
                            <button class="grid size-11 place-items-center rounded-lg bg-[#9a6200] font-bold text-white disabled:opacity-50" :disabled="!messageBody.trim() && !messageAttachment">
                                <Send class="size-5" />
                            </button>
                        </div>
                        <p v-if="messageAttachment" class="mt-2 text-xs font-semibold text-slate-500">Document sélectionné : {{ messageAttachment.name }}</p>
                        <p v-if="messageErrors.body" class="mt-2 text-sm text-red-600">{{ Array.isArray(messageErrors.body) ? messageErrors.body[0] : messageErrors.body }}</p>
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
                                v-for="message in localMessages.filter((item) => item.attachment_url)"
                                :key="message.id"
                                :href="message.attachment_url"
                                class="block rounded-lg bg-tutor-surface px-3 py-2 font-semibold"
                            >
                                Document joint
                            </a>
                            <p v-if="!localMessages.some((item) => item.attachment_url)" class="text-slate-500">Aucun fichier partagé.</p>
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
            <div class="max-h-[92svh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-2xl">
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
                    <video ref="localVideo" autoplay muted playsinline class="absolute bottom-3 right-3 h-20 w-28 rounded-lg border border-white/30 bg-slate-900 object-cover shadow-xl sm:bottom-4 sm:right-4 sm:h-28 sm:w-40"></video>
                </div>

                <div v-else class="grid min-h-[280px] place-items-center bg-[#f7f8fb] px-6 py-10 text-center">
                    <div>
                        <div class="mx-auto grid size-24 place-items-center rounded-full bg-tutor-navy text-3xl font-bold text-white">
                            {{ callAvatarLetter }}
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
