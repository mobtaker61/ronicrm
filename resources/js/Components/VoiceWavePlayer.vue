<template>
    <div
        class="flex items-center gap-2 rounded-2xl px-2 py-2 max-w-[min(100%,320px)]"
        :class="direction === 'outgoing' ? 'bg-blue-500/25' : 'bg-gray-100'"
    >
        <button
            type="button"
            class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center text-white transition-colors shadow-sm"
            :class="direction === 'outgoing' ? 'bg-blue-500 hover:bg-blue-600' : 'bg-green-600 hover:bg-green-700'"
            :aria-label="isPlaying ? 'Pause' : 'Play'"
            @click="togglePlay"
        >
            <svg v-if="!isPlaying" class="w-5 h-5 ltr:translate-x-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
            <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" /></svg>
        </button>

        <div class="flex-1 min-w-[140px] flex flex-col gap-1">
            <!-- dir=ltr تا timeline و seek مثل واتساپ از چپ به راست باشد -->
            <div
                ref="waveRef"
                dir="ltr"
                class="relative flex h-9 cursor-pointer items-end justify-between gap-px rounded px-0.5 select-none"
                role="slider"
                :aria-valuenow="Math.round(playedRatio * 100)"
                aria-valuemin="0"
                aria-valuemax="100"
                tabindex="0"
                @click="onSeekClick"
                @keydown.left.prevent="seekBy(-5)"
                @keydown.right.prevent="seekBy(5)"
            >
                <div
                    v-for="(peak, i) in peaks"
                    :key="i"
                    class="min-w-[2px] flex-1 rounded-sm transition-colors duration-150"
                    :class="barClass(i)"
                    :style="{ height: `${Math.max(3, peak * 28)}px` }"
                />
                <div
                    v-if="duration > 0"
                    class="pointer-events-none absolute top-0 bottom-0 w-0.5 rounded-full bg-sky-500 shadow"
                    :style="{ left: `${playedRatio * 100}%`, transform: 'translateX(-50%)' }"
                />
            </div>
            <div
                class="flex justify-between text-[10px] tabular-nums leading-none"
                :class="direction === 'outgoing' ? 'text-blue-100/90' : 'text-gray-500'"
            >
                <span>{{ formatClock(currentTime) }}</span>
                <span>{{ formatClock(duration) }}</span>
            </div>
        </div>

        <!-- صدا را display:none نکنید؛ در Safari/برخی مرورگرها پخش کار نمی‌کند -->
        <audio
            ref="audioRef"
            :src="cleanSrc"
            class="fixed left-[-9999px] h-px w-px opacity-0"
            preload="metadata"
            playsinline
            @timeupdate="onTimeUpdate"
            @play="isPlaying = true"
            @pause="isPlaying = false"
            @ended="onEnded"
            @loadedmetadata="onLoadedMeta"
            @error="onAudioError"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    /** URL پس از cleanMediaUrl */
    src: {
        type: String,
        required: true,
    },
    /** شناسه یکتا برای هماهنگی با سایر پیام‌ها */
    audioId: {
        type: [String, Number],
        required: true,
    },
    /** فقط یکی در هر زمان */
    activeAudioId: {
        type: [String, Number, null],
        default: null,
    },
    direction: {
        type: String,
        default: 'incoming',
    },
});

const emit = defineEmits(['update:activeAudioId']);

const BAR_COUNT = 48;
const peaks = ref(Array.from({ length: BAR_COUNT }, () => 0.35));
const audioRef = ref(null);
const waveRef = ref(null);
const duration = ref(0);
const currentTime = ref(0);
const isPlaying = ref(false);
const loadError = ref(false);

const cleanSrc = computed(() => props.src || '');

const playedRatio = computed(() => (duration.value > 0 ? currentTime.value / duration.value : 0));

function barClass(i) {
    const t = (i + 0.5) / BAR_COUNT;
    const played = playedRatio.value;
    const on = props.direction === 'outgoing';
    if (t <= played) {
        return on ? 'bg-blue-300/90' : 'bg-sky-500';
    }
    return on ? 'bg-blue-200/40' : 'bg-gray-300';
}

function formatClock(sec) {
    if (!Number.isFinite(sec) || sec < 0) return '0:00';
    const s = Math.floor(sec);
    const m = Math.floor(s / 60);
    const r = s % 60;
    return `${m}:${r.toString().padStart(2, '0')}`;
}

function onTimeUpdate() {
    const a = audioRef.value;
    if (a) {
        currentTime.value = a.currentTime;
    }
}

function onLoadedMeta() {
    const a = audioRef.value;
    if (a && Number.isFinite(a.duration)) {
        duration.value = a.duration;
    }
}

function onEnded() {
    currentTime.value = 0;
    if (String(props.activeAudioId) === String(props.audioId)) {
        emit('update:activeAudioId', null);
    }
}

function onAudioError() {
    loadError.value = true;
    isPlaying.value = false;
}

watch(
    () => props.activeAudioId,
    (id) => {
        const a = audioRef.value;
        if (!a) return;
        if (String(id) !== String(props.audioId) && !a.paused) {
            a.pause();
        }
    },
);

async function togglePlay() {
    const a = audioRef.value;
    if (!a || loadError.value) return;

    if (!a.paused) {
        a.pause();
        emit('update:activeAudioId', null);
        return;
    }

    emit('update:activeAudioId', props.audioId);

    try {
        await a.play();
    } catch (e) {
        console.warn('VoiceWavePlayer: play failed', e);
        emit('update:activeAudioId', null);
    }
}

function onSeekClick(e) {
    const a = audioRef.value;
    const box = waveRef.value;
    if (!a || !box || !duration.value) return;

    const rect = box.getBoundingClientRect();
    const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
    a.currentTime = ratio * duration.value;
    currentTime.value = a.currentTime;
}

function seekBy(deltaSec) {
    const a = audioRef.value;
    if (!a || !duration.value) return;
    a.currentTime = Math.min(duration.value, Math.max(0, a.currentTime + deltaSec));
}

/** تولید موج پایدار از روی id (fallback) */
function syntheticPeaks(seed) {
    const s = String(seed);
    let h = 0;
    for (let i = 0; i < s.length; i++) {
        h = (Math.imul(31, h) + s.charCodeAt(i)) | 0;
    }
    const out = [];
    for (let i = 0; i < BAR_COUNT; i++) {
        h = (h * 1103515245 + 12345) & 0x7fffffff;
        const n = (h % 1000) / 1000;
        out.push(0.2 + n * 0.75);
    }
    return out;
}

async function loadWaveformPeaks(url) {
    if (!url) return;
    try {
        const res = await fetch(url, { mode: 'cors', credentials: 'omit' });
        if (!res.ok) {
            throw new Error('fetch not ok');
        }
        const arrayBuffer = await res.arrayBuffer();
        const ctx = new AudioContext();
        const copy = arrayBuffer.slice(0);
        const audioBuffer = await ctx.decodeAudioData(copy);
        await ctx.close();

        const channel = audioBuffer.getChannelData(0);
        const len = channel.length;
        const step = Math.floor(len / BAR_COUNT);
        const next = [];
        let max = 0.0001;
        for (let i = 0; i < BAR_COUNT; i++) {
            let sum = 0;
            const start = i * step;
            const end = Math.min(start + step, len);
            for (let j = start; j < end; j++) {
                sum += Math.abs(channel[j]);
            }
            const avg = sum / (end - start || 1);
            next.push(avg);
            if (avg > max) max = avg;
        }
        peaks.value = next.map((p) => Math.min(1, (p / max) * 0.95 + 0.05));
    } catch {
        peaks.value = syntheticPeaks(props.audioId);
    }
}

onMounted(() => {
    loadWaveformPeaks(cleanSrc.value);
});

watch(cleanSrc, (u) => {
    if (u) {
        loadWaveformPeaks(u);
    }
});

onUnmounted(() => {
    const a = audioRef.value;
    if (a && !a.paused) {
        a.pause();
    }
    if (String(props.activeAudioId) === String(props.audioId)) {
        emit('update:activeAudioId', null);
    }
});
</script>
