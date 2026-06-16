<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const player = axios.create({ baseURL: '/api/player', headers: { Accept: 'application/json' } });

const token = ref(localStorage.getItem('player_token') || null);
const pairingCode = ref('');
const registering = ref(false);
const registerError = ref('');

const config = ref(null);
const lastRevision = ref(null);
const online = ref(true);
const live = ref(false);
const controlsVisible = ref(false);
const nowTs = ref(Date.now());
const tickerDone = ref(false);
const clockOffset = ref(0);

/*
 * Double-buffered playback: two media slots (A/B). The active slot is on
 * screen; the other silently preloads the NEXT item so transitions never show
 * a buffering jolt. Slots crossfade (opacity). Video position is kept in sync
 * with the shared clock by gently nudging playbackRate (±a few %) instead of
 * seeking — so drift dissolves invisibly. A hard seek happens only on a large
 * gap (tab slept). Result: smooth, no stutter, screens stay in lockstep.
 */
const slots = reactive([
    { item: null, ready: false, visible: false },
    { item: null, ready: false, visible: false },
]);
const activeSlot = ref(0);
const videoRefs = [null, null];
const setVideoRef = (i, el) => { videoRefs[i] = el; };

let tickHandle = null;
let beatHandle = null;
let fallbackHandle = null;
let fullHandle = null;
let timeSyncHandle = null;
let controlsTimer = null;
let echo = null;

const TICK_MS = 200;        // scheduler resolution
const DEAD_ZONE = 0.06;     // s — drift below this needs no correction
const HARD_DRIFT = 2.5;     // s — above this, fade-masked resync instead of a visible seek
const MAX_RATE_ADJ = 0.06;  // max ±6% speed change (imperceptible; aggressive values overshoot)
const RATE_K = 0.4;         // drift→rate gain

// Rendezvous resync: converge to the shared-clock position BY PLAYING toward it
// (rate up/down) so all screens arrive together at the deadline. No seek/reset.
const RESYNC_MIN_LEAD = 600;  // ms — minimum convergence window
const COLD_START_WINDOW = 8000; // ms — window a freshly-opened screen uses to converge
const CONVERGE_MAX = 1.8;     // max play speed while converging (still watchable)
const CONVERGE_MIN = 0.4;     // min play speed while converging

function authHeader() {
    return { headers: { Authorization: `Bearer ${token.value}` } };
}

const items = computed(() => config.value?.playlist?.items ?? []);
const activeItem = computed(() => slots[activeSlot.value].item);
const ticker = computed(() => (config.value?.ticker?.enabled ? config.value.ticker : null));
const emergency = computed(() => (config.value?.emergency?.active ? config.value.emergency : null));

const emergencyVisible = computed(() => {
    const e = emergency.value;
    if (!e) return false;
    const t = nowTs.value;
    if (e.scheduled_start_ms && t < e.scheduled_start_ms) return false;
    if (e.ends_at_ms && t >= e.ends_at_ms) return false;
    return true;
});

const tickerVisible = computed(() => {
    const tk = ticker.value;
    if (!tk || tickerDone.value) return false;
    const t = nowTs.value;
    if (tk.duration_minutes && tk.started_at_ms && t > tk.started_at_ms + tk.duration_minutes * 60000) return false;
    const e = emergency.value;
    if (emergencyVisible.value && e?.display_style === 'banner' && e?.position === tk.position) return false;
    return true;
});

function onTickerAnimEnd() {
    if (ticker.value?.repeat_count) tickerDone.value = true;
}

watch(
    () => ticker.value && ticker.value.text + '|' + (ticker.value.repeat_count ?? 'inf') + '|' + (ticker.value.started_at_ms ?? ''),
    () => { tickerDone.value = false; }
);

// Reset slots when the playlist itself changes so the scheduler reinitialises.
watch(
    () => (config.value?.playlist ? config.value.playlist.id + ':' + config.value.playlist.version : null),
    () => {
        cancelResync(); // an in-flight resync references the old schedule indices
        slots[0] = { item: null, ready: false, visible: false };
        slots[1] = { item: null, ready: false, visible: false };
        activeSlot.value = 0;
    }
);

function syncedNow() {
    return Date.now() + clockOffset.value;
}

/* ---------------- clock sync (NTP-style) ---------------- */
async function syncTime() {
    try {
        const t0 = Date.now();
        const { data } = await player.get('/time');
        const t1 = Date.now();
        clockOffset.value = data.now + (t1 - t0) / 2 - t1;
        online.value = true;
    } catch (e) { /* keep last offset */ }
}

/* ---------------- clock-driven schedule ---------------- */
function buildSchedule() {
    const its = items.value;
    const offs = [];
    let cum = 0;
    for (const it of its) {
        offs.push(cum);
        cum += Math.max(1, Number(it.duration) || 10);
    }
    return { offs, cycle: cum };
}

// Pure: what item+offset is correct at an explicit instant (server ms).
function computeNowAt(ms) {
    const its = items.value;
    if (!its.length) return { index: -1, offset: 0 };
    const { offs, cycle } = buildSchedule();
    if (cycle <= 0) return { index: 0, offset: 0 };
    let pos = (ms / 1000) % cycle;
    if (pos < 0) pos += cycle;
    let idx = 0;
    for (let i = its.length - 1; i >= 0; i--) {
        if (pos >= offs[i]) { idx = i; break; }
    }
    return { index: idx, offset: pos - offs[idx] };
}
function computeNow() { return computeNowAt(syncedNow()); }

/* ---------------- rendezvous resync (converge by playing, no seek) ----------------
 * A resync sets a deadline `at`. Until then, the video plays at a computed rate
 * so it ARRIVES at the shared-clock position exactly at `at` — all screens aim
 * at the SAME clock position at the SAME instant, so they meet there. No seek,
 * no crossfade reset: the picture just plays a little faster/slower into
 * alignment, then holds at rate 1. This is the user's "everyone reaches X at
 * moment X" idea. */
let resyncDeadline = null;

function cancelResync() {
    resyncDeadline = null;
}

function scheduleResync(atMs) {
    if (!items.value.length) return;
    const now = syncedNow();
    const at = Math.max(atMs, now + RESYNC_MIN_LEAD);
    // Don't shorten an in-flight rendezvous; take the later deadline.
    resyncDeadline = resyncDeadline ? Math.max(resyncDeadline, at) : at;
}

/* ---------------- video position sync ---------------- */
function syncVideoRate(v, target) {
    if (!v || v.readyState < 2 || v.seeking) return;
    const dur = v.duration && isFinite(v.duration) ? v.duration : null;
    const tgt = dur ? Math.min(target, dur - 0.05) : target;
    const now = syncedNow();

    // Rendezvous mode: play toward the clock position so we land on it at the
    // deadline. rate = 1 + (gap / window). Behind → faster, ahead → slower.
    if (resyncDeadline) {
        if (now >= resyncDeadline) {
            resyncDeadline = null;
        } else {
            const window = (resyncDeadline - now) / 1000;
            let rate = 1 + (tgt - v.currentTime) / window;
            rate = Math.min(CONVERGE_MAX, Math.max(CONVERGE_MIN, rate));
            if (v.playbackRate !== rate) v.playbackRate = rate;
            if (v.paused) v.play().catch(() => {});
            return;
        }
    }

    // Steady-state: gentle playbackRate nudge for small residual drift.
    const drift = v.currentTime - tgt;
    const a = Math.abs(drift);
    if (a < DEAD_ZONE) {
        if (v.playbackRate !== 1) v.playbackRate = 1;
    } else {
        const adj = Math.min(MAX_RATE_ADJ, a * RATE_K);
        v.playbackRate = drift > 0 ? 1 - adj : 1 + adj;
    }
    if (v.paused) v.play().catch(() => {});
}

function pauseSlot(i) {
    const v = videoRefs[i];
    if (v) try { v.pause(); } catch (e) { /* ignore */ }
}

function onSlotReady(i) {
    slots[i].ready = true;
    const v = videoRefs[i];
    if (v && slots[i].visible) v.play().catch(() => {});
    // Cold-start: a freshly-shown video starts from 0; converge it to the shared
    // clock position by playing (rate) over COLD_START_WINDOW — slides into sync
    // without a visible reset.
    if (i === activeSlot.value && slots[i].item?.type === 'video' && computeNow().offset > 0.5) {
        scheduleResync(syncedNow() + COLD_START_WINDOW);
    }
}

/* The scheduler tick: pick the item for "now" from the shared clock, manage
   the A/B buffers, crossfade, and nudge the active video's rate. */
function tick() {
    nowTs.value = syncedNow();

    const { index, offset } = computeNow();
    if (index < 0) return;

    const n = items.value.length;
    const target = items.value[index];
    const act = activeSlot.value;
    const other = 1 - act;
    const cur = slots[act];

    if (!cur.item || cur.item.id !== target.id) {
        if (!cur.item) {
            // Cold start — show the first item immediately, then converge to the
            // shared-clock position by playing (rate), so it slides into sync
            // without a visible reset. onSlotReady kicks off the convergence.
            slots[act] = { item: target, ready: false, visible: true };
        } else if (slots[other].item && slots[other].item.id === target.id && slots[other].ready) {
            // Preloaded and ready → crossfade swap (boundary offset ≈ 0, no seek needed).
            slots[other].visible = true;
            cur.visible = false;
            pauseSlot(act);
            activeSlot.value = other;
            const nv = videoRefs[other];
            if (nv) nv.play().catch(() => {});
        } else if (!slots[other].item || slots[other].item.id !== target.id) {
            // Buffer miss — start loading the target into the other slot.
            slots[other] = { item: target, ready: false, visible: false };
        }
        // else: other slot is still loading target — wait.
        return;
    }

    // Active slot already shows the right item.
    if (target.type === 'video') syncVideoRate(videoRefs[act], offset);

    // Preload the NEXT item into the idle slot.
    if (n > 1) {
        const nextItem = items.value[(index + 1) % n];
        if (!slots[other].item || slots[other].item.id !== nextItem.id) {
            slots[other] = { item: nextItem, ready: false, visible: false };
        }
    }
}

function onMediaError() { /* clock advances at the next boundary anyway */ }

/* ---------------- config / revision ---------------- */
async function loadConfig() {
    try {
        const { data } = await player.get('/config', authHeader());
        config.value = data;
        online.value = true;
    } catch (e) {
        if (e?.response?.status === 401) {
            teardown();
            localStorage.removeItem('player_token');
            token.value = null;
        } else {
            online.value = false;
        }
    }
}

async function pollState() {
    try {
        const { data } = await player.get('/state', authHeader());
        online.value = true;
        if (data.revision !== lastRevision.value) {
            lastRevision.value = data.revision;
            await loadConfig();
        }
    } catch (e) {
        if (e?.response?.status === 401) {
            teardown();
            localStorage.removeItem('player_token');
            token.value = null;
        } else {
            online.value = false;
        }
    }
}

async function heartbeat() {
    try {
        await player.post('/heartbeat', {
            status: emergencyVisible.value ? 'playing' : activeItem.value ? 'playing' : 'idle',
            current_playlist_id: config.value?.playlist?.id ?? null,
            current_media_id: activeItem.value?.media_id ?? activeItem.value?.id ?? null,
        }, authHeader());
    } catch (e) { /* offline: keep playing from cache */ }
}

/* ---------------- realtime (Reverb websocket) ---------------- */
function setupEcho() {
    if (echo) return;
    try {
        window.Pusher = Pusher;
        echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        echo.channel('signage').listen('.content.changed', () => pollState());
        echo.channel('signage').listen('.resync', (e) => {
            if (e && typeof e.at === 'number') scheduleResync(e.at);
        });
        const conn = echo.connector?.pusher?.connection;
        if (conn) {
            conn.bind('connected', () => { live.value = true; });
            conn.bind('disconnected', () => { live.value = false; });
            conn.bind('unavailable', () => { live.value = false; });
        }
    } catch (e) {
        live.value = false;
    }
}

/* ---------------- lifecycle ---------------- */
async function init() {
    await syncTime();
    await loadConfig();
    try { lastRevision.value = (await player.get('/state', authHeader())).data.revision; } catch (e) {}
    setupEcho();

    tickHandle = setInterval(tick, TICK_MS);
    beatHandle = setInterval(heartbeat, 20000);
    fallbackHandle = setInterval(pollState, 15000);
    fullHandle = setInterval(loadConfig, 60000);
    timeSyncHandle = setInterval(syncTime, 300000);
    heartbeat();
}

function teardown() {
    [tickHandle, beatHandle, fallbackHandle, fullHandle, timeSyncHandle].forEach((h) => h && clearInterval(h));
    tickHandle = beatHandle = fallbackHandle = fullHandle = timeSyncHandle = null;
    cancelResync();
    if (echo) { try { echo.disconnect(); } catch (e) {} echo = null; }
    live.value = false;
}

async function register() {
    registerError.value = '';
    registering.value = true;
    try {
        const { data } = await player.post('/register', {
            pairing_code: pairingCode.value.trim().toUpperCase(),
            platform: 'browser',
            app_version: 'web-1.0.0',
            screen_resolution: `${window.screen.width}x${window.screen.height}`,
        });
        token.value = data.token;
        localStorage.setItem('player_token', data.token);
        await init();
    } catch (e) {
        registerError.value = e?.response?.data?.message || 'Не удалось привязать устройство.';
    } finally {
        registering.value = false;
    }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
    else document.exitFullscreen?.();
}

function showControls() {
    controlsVisible.value = true;
    if (controlsTimer) clearTimeout(controlsTimer);
    controlsTimer = setTimeout(() => { controlsVisible.value = false; }, 3500);
}

function unpair() {
    if (!window.confirm('Отвязать это устройство от системы?')) return;
    teardown();
    localStorage.removeItem('player_token');
    token.value = null;
    config.value = null;
    lastRevision.value = null;
}

onMounted(() => { if (token.value) init(); });
onUnmounted(teardown);
</script>

<template>
    <div class="w-screen h-screen bg-black text-white overflow-hidden relative font-sans" @mousemove="showControls">
        <!-- Registration -->
        <div v-if="!token" class="absolute inset-0 flex items-center justify-center">
            <div class="bg-slate-900 border border-slate-700 rounded-2xl p-10 w-[420px] text-center">
                <div class="text-2xl font-semibold mb-2">Привязка устройства</div>
                <p class="text-slate-400 text-sm mb-6">Введите код привязки из панели администратора.</p>
                <input
                    v-model="pairingCode"
                    placeholder="A7K9-22"
                    class="w-full text-center text-2xl tracking-widest uppercase bg-slate-800 rounded-lg px-4 py-3 mb-4 outline-none focus:ring-2 ring-indigo-500"
                    @keyup.enter="register"
                />
                <button
                    @click="register"
                    :disabled="registering || !pairingCode"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg py-3 font-medium"
                >
                    {{ registering ? 'Привязка…' : 'Привязать' }}
                </button>
                <p v-if="registerError" class="text-red-400 text-sm mt-3">{{ registerError }}</p>
            </div>
        </div>

        <!-- Playback -->
        <template v-else>
            <Transition name="fade-down">
                <div v-if="!online" class="absolute top-0 left-0 right-0 z-30 bg-amber-500/90 text-black text-center text-sm py-1.5 font-medium">
                    Нет связи с сервером — показываю из кэша
                </div>
            </Transition>

            <Transition name="fade">
                <div v-if="controlsVisible" class="absolute top-4 right-4 z-40 flex items-center gap-3 bg-slate-900/80 backdrop-blur rounded-full px-4 py-2 text-sm">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :class="!online ? 'bg-amber-400' : live ? 'bg-green-400' : 'bg-slate-400'" />
                        {{ config?.device?.name || 'Устройство' }}
                        <span class="text-xs text-slate-400">{{ live ? 'live' : 'опрос' }}</span>
                    </span>
                    <button class="text-slate-300 hover:text-white" @click="toggleFullscreen" title="Полный экран">⛶</button>
                    <button class="text-slate-400 hover:text-red-400 text-xs" @click="unpair" title="Отвязать">Отвязать</button>
                </div>
            </Transition>

            <!-- Fallback when no playlist -->
            <div v-if="!activeItem && !emergencyVisible" class="absolute inset-0 flex flex-col items-center justify-center text-slate-500">
                <div class="text-4xl font-semibold mb-2">Clinic Signage</div>
                <div class="text-sm">Ожидание контента…</div>
            </div>

            <!-- Media: two crossfading slots (A/B double buffer) -->
            <div
                v-for="(slot, i) in slots"
                :key="i"
                class="absolute inset-0 flex items-center justify-center bg-black transition-opacity duration-500 ease-in-out"
                :style="{ opacity: slot.visible ? 1 : 0 }"
            >
                <template v-if="slot.item">
                    <video
                        v-if="slot.item.type === 'video'"
                        :ref="(el) => setVideoRef(i, el)"
                        :key="slot.item.id"
                        :src="slot.item.url"
                        class="w-full h-full object-contain"
                        muted
                        playsinline
                        loop
                        preload="auto"
                        @loadeddata="onSlotReady(i)"
                        @canplaythrough="onSlotReady(i)"
                        @error="onMediaError"
                    />
                    <img
                        v-else
                        :key="slot.item.id"
                        :src="slot.item.url"
                        class="w-full h-full object-contain"
                        @load="onSlotReady(i)"
                        @error="onMediaError"
                    />
                </template>
            </div>

            <!-- Ticker -->
            <div
                v-if="tickerVisible"
                class="absolute left-0 right-0 overflow-hidden whitespace-nowrap py-2 z-10"
                :class="ticker.position === 'top' ? 'top-0' : 'bottom-0'"
                :style="{ background: ticker.background_color, opacity: ticker.opacity, color: ticker.text_color, fontSize: (ticker.font_size || 28) + 'px' }"
            >
                <div
                    class="inline-block ticker-move"
                    :key="ticker.text + '|' + (ticker.repeat_count ?? 'inf')"
                    :style="{ animationDuration: Math.max(8, 1200 / (ticker.speed || 60)) + 's', animationIterationCount: ticker.repeat_count || 'infinite' }"
                    @animationend="onTickerAnimEnd"
                >
                    {{ ticker.text }}
                </div>
            </div>

            <!-- Emergency: fullscreen -->
            <div
                v-if="emergencyVisible && (emergency.display_style || 'fullscreen') === 'fullscreen'"
                class="absolute inset-0 flex items-center justify-center text-center p-12 z-20"
                :class="{ 'emergency-blink': emergency.blink }"
                :style="{ background: emergency.background_color, color: emergency.text_color }"
            >
                <div>
                    <div class="text-3xl font-bold mb-4">⚠ ВНИМАНИЕ</div>
                    <div class="font-semibold leading-tight" :style="{ fontSize: (emergency.font_size || 48) + 'px' }">{{ emergency.text }}</div>
                </div>
            </div>

            <!-- Emergency: banner -->
            <div
                v-else-if="emergencyVisible && emergency.display_style === 'banner'"
                class="absolute left-0 right-0 z-20 flex items-center justify-center gap-3 text-center px-6 py-3"
                :class="[emergency.position === 'top' ? 'top-0' : 'bottom-0', { 'emergency-blink': emergency.blink }]"
                :style="{ background: emergency.background_color, color: emergency.text_color, fontSize: (emergency.font_size || 48) + 'px' }"
            >
                <span class="font-bold">⚠</span>
                <span class="font-semibold">{{ emergency.text }}</span>
            </div>
        </template>
    </div>
</template>

<style scoped>
.ticker-move {
    padding-left: 100%;
    animation-name: ticker-scroll;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
}
@keyframes ticker-scroll {
    from { transform: translateX(0); }
    to { transform: translateX(-100%); }
}
.emergency-blink { animation: emergency-pulse 1s ease-in-out infinite; }
@keyframes emergency-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.fade-down-enter-active, .fade-down-leave-active { transition: all 0.3s ease; }
.fade-down-enter-from, .fade-down-leave-to { opacity: 0; transform: translateY(-100%); }
</style>
