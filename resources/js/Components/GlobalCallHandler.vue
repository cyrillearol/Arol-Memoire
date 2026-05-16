<script setup>
import { Check, LoaderCircle, PhoneOff, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || null);
const hasActiveCall = computed(() => callState.value !== 'idle');
const callTitle = computed(() => {
    if (callState.value === 'incoming') return `${callerName.value} vous appelle`;
    return `En appel avec ${callerName.value}`;
});
const callAvatarLetter = computed(() => callerName.value?.charAt(0) || 'U');

let userChannel = null;
let peerConnection = null;
let localStream = null;
let remoteStream = null;
let pendingRemoteCandidates = [];

const callState = ref('idle');
const callMode = ref('audio');
const callStatus = ref('');
const callError = ref('');
const callerName = ref('votre correspondant');
const incomingOffer = ref(null);
const callConversationId = ref(null);
const localVideo = ref(null);
const remoteVideo = ref(null);
const remoteAudio = ref(null);

const iceServers = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
];

const isMessagesPage = () => {
    try {
        return route().current('messages.index');
    } catch (error) {
        return window.location.pathname.startsWith('/messages');
    }
};

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

const sendCallSignal = async (type, payload = {}, mode = callMode.value, conversationId = callConversationId.value) => {
    if (!conversationId) return;

    await window.axios.post(route('calls.signal', conversationId), {
        type,
        mode,
        payload,
    }, { timeout: 10000 });
};

const sendCallSignalInBackground = (type, payload = {}, mode = callMode.value, conversationId = callConversationId.value) => {
    void sendCallSignal(type, payload, mode, conversationId).catch((error) => {
        console.warn('Signal d’appel non envoyé', error);
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
    callerName.value = 'votre correspondant';
    callState.value = 'idle';
    callMode.value = 'audio';
    callStatus.value = '';

    void attachStreams();
};

const endCall = (conversationId = callConversationId.value, notify = true) => {
    const shouldNotify = notify && conversationId && callState.value !== 'idle';
    const mode = callMode.value;

    cleanupCall();

    if (shouldNotify) {
        sendCallSignalInBackground('call-end', {}, mode, conversationId);
    }
};

const requestLocalStream = async (mode) => {
    if (!navigator.mediaDevices?.getUserMedia || !window.RTCPeerConnection || !window.isSecureContext) {
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
            sendCallSignalInBackground('ice-candidate', {
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

const acceptCall = async () => {
    if (!incomingOffer.value || !callConversationId.value) return;

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
        const conversationId = callConversationId.value;
        const mode = callMode.value;

        cleanupCall();
        sendCallSignalInBackground('call-decline', { reason: 'media-error' }, mode, conversationId);
        setCallError(error.message === 'WEBRTC_UNAVAILABLE'
            ? 'Le navigateur bloque le micro ou la caméra. Utilisez HTTPS et vérifiez les autorisations du site.'
            : 'Impossible d’accepter l’appel. Vérifiez l’autorisation du micro ou de la caméra.');
    }
};

const declineCall = () => {
    const conversationId = callConversationId.value;
    const mode = callMode.value;

    cleanupCall();

    if (conversationId) {
        sendCallSignalInBackground('call-decline', { reason: 'declined' }, mode, conversationId);
    }
};

const handleCallSignal = async (event) => {
    if (!event || Number(event.sender_id) === Number(user.value?.id)) return;

    const eventConversationId = event.conversation_id || null;

    if (isMessagesPage() && callState.value === 'idle') {
        return;
    }

    if (event.type !== 'call-offer' && callConversationId.value && eventConversationId && Number(eventConversationId) !== Number(callConversationId.value)) {
        return;
    }

    if (event.type === 'call-offer') {
        if (callState.value !== 'idle') {
            await sendCallSignal('call-decline', { reason: 'busy' }, event.mode || 'audio', eventConversationId);
            return;
        }

        if (!event.payload?.description || !eventConversationId) return;

        callerName.value = event.sender_name || 'votre correspondant';
        callMode.value = event.mode || 'audio';
        callConversationId.value = eventConversationId;
        incomingOffer.value = event.payload.description;
        callState.value = 'incoming';
        callStatus.value = event.mode === 'video' ? 'Appel vidéo entrant' : 'Appel audio entrant';
        return;
    }

    if (event.type === 'ice-candidate') {
        await addRemoteCandidate(event.payload?.candidate);
        return;
    }

    if (event.type === 'call-end') {
        cleanupCall();
        setCallError('L’appel est terminé.');
        return;
    }

    if (event.type === 'call-decline') {
        cleanupCall();
        setCallError('L’appel a été refusé.');
    }
};

onMounted(() => {
    if (!window.Echo || !user.value?.id) {
        return;
    }

    userChannel = window.Echo.private(`users.${user.value.id}`);
    userChannel.listen('.call.signal', handleCallSignal);
});

onBeforeUnmount(() => {
    if (callState.value !== 'idle') {
        void endCall();
    } else {
        cleanupCall();
    }

    userChannel?.stopListening?.('.call.signal', handleCallSignal);
    userChannel = null;
});
</script>

<template>
    <div v-if="callError" class="fixed bottom-5 right-5 z-[70] max-w-sm rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 shadow-2xl">
        {{ callError }}
    </div>

    <div v-if="hasActiveCall" class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/75 px-4 py-6 backdrop-blur-sm">
        <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-2xl">
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
                    <p class="mt-5 text-lg font-bold text-tutor-navy">{{ callerName }}</p>
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
</template>