<script setup>
import PublicFooter from '@/Components/PublicFooter.vue';
import PublicHeader from '@/Components/PublicHeader.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    tutor: Object,
    availabilities: {
        type: Array,
        default: () => [],
    },
    availableSlots: {
        type: Array,
        default: () => [],
    },
    payment: {
        type: Object,
        default: () => ({}),
    },
});

const durations = [60, 90, 120];
const slots = computed(() => props.availableSlots || []);
const firstSlotFor = (duration) => slots.value.find((slot) => slot.available_durations?.includes(Number(duration)));
const paymentError = ref('');
const kkiapayReady = ref(false);

const form = useForm({
    subject: props.tutor.subjects?.[0] || '',
    scheduled_at: firstSlotFor(60)?.scheduled_at || '',
    duration_minutes: 60,
    notes: '',
    payment_method: 'kkiapay',
    kkiapay_transaction_id: '',
});

const amount = computed(() => Math.round((props.tutor.hourly_rate || 0) * (Number(form.duration_minutes) / 60)));
const fees = computed(() => Math.round(amount.value * 0.1));
const total = computed(() => amount.value + fees.value);
const money = (value) => `${new Intl.NumberFormat('fr-FR').format(value || 0)} FCFA`;
const filteredSlots = computed(() => slots.value.filter((slot) => slot.available_durations?.includes(Number(form.duration_minutes))));
const selectedSlot = computed(() => slots.value.find((slot) => slot.scheduled_at === form.scheduled_at));
const groupedSlots = computed(() => {
    const groups = [];

    filteredSlots.value.forEach((slot) => {
        let group = groups.find((item) => item.date === slot.date);

        if (!group) {
            group = {
                date: slot.date,
                label: `${slot.weekday_label} ${slot.date_label}`,
                slots: [],
            };
            groups.push(group);
        }

        group.slots.push(slot);
    });

    return groups;
});

watch(
    () => form.duration_minutes,
    (duration) => {
        if (!selectedSlot.value?.available_durations?.includes(Number(duration))) {
            form.scheduled_at = firstSlotFor(duration)?.scheduled_at || '';
        }
    },
);

const loadKkiapayScript = () => new Promise((resolve, reject) => {
    if (window.openKkiapayWidget) {
        kkiapayReady.value = true;
        resolve();
        return;
    }

    const existingScript = document.querySelector('script[src="https://cdn.kkiapay.me/k.js"]');
    if (existingScript) {
        existingScript.addEventListener('load', () => {
            kkiapayReady.value = true;
            resolve();
        }, { once: true });
        existingScript.addEventListener('error', reject, { once: true });
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://cdn.kkiapay.me/k.js';
    script.async = true;
    script.onload = () => {
        kkiapayReady.value = true;
        resolve();
    };
    script.onerror = reject;
    document.head.appendChild(script);
});

const transactionIdFrom = (response) => response?.transactionId
    || response?.transaction_id
    || response?.data?.transactionId
    || response?.data?.transaction_id
    || response?.paymentReference;

const submit = () => {
    if (!form.scheduled_at) return;

    form.transform((data) => ({
        subject: data.subject,
        scheduled_at: data.scheduled_at,
        duration_minutes: data.duration_minutes,
        notes: data.notes,
        payment_method: data.payment_method,
        kkiapay_transaction_id: data.kkiapay_transaction_id,
    })).post(route('bookings.store', props.tutor.id));
};

const startKkiapayPayment = async () => {
    paymentError.value = '';

    if (!form.scheduled_at) {
        paymentError.value = 'Choisissez un créneau disponible.';
        return;
    }

    if (!props.payment.public_key) {
        paymentError.value = 'Le paiement Kkiapay est momentanément indisponible.';
        return;
    }

    try {
        await loadKkiapayScript();
    } catch (error) {
        paymentError.value = 'Impossible de charger Kkiapay. Vérifiez votre connexion.';
        return;
    }

    window.openKkiapayWidget({
        amount: total.value,
        key: props.payment.public_key,
        sandbox: Boolean(props.payment.sandbox),
        position: 'center',
        theme: '#022448',
        paymentMethods: ['momo', 'card'],
        data: {
            tutor_id: props.tutor.id,
            scheduled_at: form.scheduled_at,
            subject: form.subject,
            duration_minutes: form.duration_minutes,
        },
    });
};

onMounted(async () => {
    try {
        await loadKkiapayScript();
    } catch (error) {
        return;
    }

    window.addSuccessListener?.((response) => {
        const transactionId = transactionIdFrom(response);

        if (!transactionId) {
            paymentError.value = 'Transaction Kkiapay sans référence. Réessayez.';
            return;
        }

        form.kkiapay_transaction_id = transactionId;
        submit();
    });

    window.addFailedListener?.(() => {
        paymentError.value = 'Le paiement Kkiapay a échoué.';
    });
});
</script>

<template>
    <Head title="Réservation" />

    <main class="min-h-screen bg-tutor-surface">
        <PublicHeader active="tutors" />

        <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8">
            <div class="mx-auto mb-12 grid max-w-3xl grid-cols-3 items-center gap-4 text-center text-sm text-slate-500">
                <div><span class="mx-auto grid size-10 place-items-center rounded-full bg-tutor-navy font-bold text-white">1</span><p class="mt-2 font-bold text-tutor-navy">Créneau</p></div>
                <div class="h-px bg-slate-300"></div>
                <div><span class="mx-auto grid size-10 place-items-center rounded-full bg-slate-200 font-bold text-slate-600">2</span><p class="mt-2">Paiement</p></div>
            </div>

            <form class="grid gap-8 lg:grid-cols-[340px_1fr]" @submit.prevent="submit">
                <aside class="space-y-5">
                    <div class="tl-card p-8 text-center">
                        <div class="mx-auto grid size-28 place-items-center rounded-full bg-tutor-navy text-4xl font-bold text-white">{{ tutor.name.charAt(0) }}</div>
                        <h1 class="mt-5 text-2xl font-bold">{{ tutor.name }}</h1>
                        <p class="text-sm font-bold uppercase tracking-wide text-slate-500">{{ tutor.domain }}</p>
                        <p class="mt-4 text-[#9a6200]">★ {{ tutor.rating || 'N/A' }} <span class="text-slate-500">({{ tutor.reviews_count }} avis)</span></p>
                        <div class="my-6 border-t border-slate-200"></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-slate-600">Tarif horaire</span><span class="text-xl font-bold text-tutor-navy">{{ money(tutor.hourly_rate) }}</span></div>
                    </div>
                    <div class="rounded-lg bg-tutor-navy p-5 text-white">
                        <p class="font-bold">Garantie de satisfaction TutorLink</p>
                        <p class="mt-3 text-sm text-white/70">Paiement suivi et réservation confirmée uniquement après validation du tuteur.</p>
                    </div>
                </aside>

                <div class="space-y-8">
                    <section class="tl-card p-7">
                        <h2 class="text-3xl font-bold">Choisissez un créneau disponible</h2>
                        <div class="mt-6 grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="text-sm font-bold text-tutor-ink">Matière</label>
                                <select v-model="form.subject" class="tl-input mt-2 w-full px-4 py-3" required>
                                    <option v-for="subject in tutor.subjects" :key="subject" :value="subject">{{ subject }}</option>
                                </select>
                                <p v-if="form.errors.subject" class="mt-2 text-sm text-red-600">{{ form.errors.subject }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-bold uppercase tracking-wide text-slate-500">Durée</p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button v-for="duration in durations" :key="duration" type="button" class="rounded-lg border px-5 py-3 text-sm font-bold" :class="Number(form.duration_minutes) === duration ? 'border-tutor-gold bg-[#fff7e8] text-tutor-navy' : 'border-slate-200 bg-white text-slate-700'" @click="form.duration_minutes = duration">{{ duration }} min</button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <p class="text-sm font-bold uppercase tracking-wide text-slate-500">Créneaux proposés par le tuteur</p>
                            <div v-if="groupedSlots.length" class="mt-4 space-y-5">
                                <div v-for="group in groupedSlots" :key="group.date">
                                    <p class="text-sm font-bold text-tutor-navy">{{ group.label }}</p>
                                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                        <button v-for="slot in group.slots" :key="slot.scheduled_at" type="button" class="rounded-lg border px-4 py-3 text-sm font-bold transition" :class="form.scheduled_at === slot.scheduled_at ? 'border-tutor-navy bg-tutor-navy text-white shadow-tutor' : 'border-slate-200 bg-white text-slate-700 hover:border-tutor-gold'" @click="form.scheduled_at = slot.scheduled_at">
                                            {{ slot.time }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="mt-4 rounded-lg border border-dashed border-slate-300 bg-tutor-surface p-6 text-center text-sm text-slate-600">
                                Aucun créneau disponible pour cette durée.
                            </div>
                            <p v-if="form.errors.scheduled_at" class="mt-2 text-sm text-red-600">{{ form.errors.scheduled_at }}</p>
                        </div>
                    </section>

                    <section class="tl-card p-7">
                        <h2 class="text-3xl font-bold">Résumé de la réservation</h2>
                        <div class="mt-6 grid gap-5 md:grid-cols-2">
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between border-b border-slate-200 pb-3"><span class="text-slate-500">Date</span><span class="font-bold">{{ selectedSlot?.date_label || '-' }}</span></div>
                                <div class="flex justify-between border-b border-slate-200 pb-3"><span class="text-slate-500">Heure</span><span class="font-bold">{{ selectedSlot?.time || '-' }}</span></div>
                                <div class="flex justify-between border-b border-slate-200 pb-3"><span class="text-slate-500">Durée</span><span class="font-bold">{{ form.duration_minutes }} min</span></div>
                            </div>
                            <div>
                                <label class="text-sm font-bold uppercase tracking-wide text-slate-500">Besoins pour la séance</label>
                                <textarea v-model="form.notes" class="tl-input mt-2 w-full px-4 py-3" rows="5" placeholder="Décrivez les sujets que vous souhaitez aborder..."></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-6 lg:grid-cols-[1fr_300px]">
                        <div class="tl-card p-7">
                            <h2 class="text-3xl font-bold">Paiement Kkiapay</h2>
                            <div class="mt-6 rounded-lg border border-slate-200 bg-tutor-surface p-5">
                                <p class="font-bold text-tutor-navy">Mobile Money et carte bancaire</p>
                                <p class="mt-2 text-sm text-slate-600">Le paiement est vérifié avant l’envoi de la demande au tuteur.</p>
                            </div>
                            <p v-if="paymentError || form.errors.payment || form.errors.kkiapay_transaction_id" class="mt-4 text-sm font-semibold text-red-600">
                                {{ paymentError || form.errors.payment || form.errors.kkiapay_transaction_id }}
                            </p>
                        </div>

                        <div class="tl-card h-fit p-7">
                            <h3 class="font-bold text-tutor-navy">Récapitulatif</h3>
                            <div class="mt-5 space-y-3 text-sm">
                                <div class="flex justify-between"><span>Cours</span><span class="font-bold">{{ money(amount) }}</span></div>
                                <div class="flex justify-between"><span>Frais de service</span><span class="font-bold">{{ money(fees) }}</span></div>
                                <div class="border-t border-slate-200 pt-3 flex justify-between text-lg font-bold text-tutor-navy"><span>Total</span><span>{{ money(total) }}</span></div>
                            </div>
                            <button type="button" class="tl-button-primary mt-6 w-full" :disabled="form.processing || !form.scheduled_at" @click="startKkiapayPayment">
                                {{ form.processing ? 'Vérification...' : 'Payer avec Kkiapay' }}
                            </button>
                            <p class="mt-4 text-center text-xs leading-5 text-slate-500">Transaction enregistrée et associée à votre réservation.</p>
                        </div>
                    </section>
                </div>
            </form>
        </section>

        <PublicFooter />
    </main>
</template>
