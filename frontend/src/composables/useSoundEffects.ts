import { ref, watch } from "vue";

export type SoundName = "dice" | "move" | "kick" | "bonus" | "win" | "turn";

const STORAGE_KEY = "kickandrun_muted";

const muted = ref(loadMuted());

function loadMuted(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) === "true";
  } catch {
    return false;
  }
}

watch(muted, (val) => {
  try {
    localStorage.setItem(STORAGE_KEY, String(val));
  } catch {
    // Ignore storage errors
  }
});

function toggleMute() {
  muted.value = !muted.value;
}

let audioCtx: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
  if (typeof window === "undefined" || !window.AudioContext) return null;
  if (!audioCtx) {
    audioCtx = new AudioContext();
  }
  return audioCtx;
}

function playTone(frequency: number, duration: number, type: OscillatorType = "sine", volume = 0.3) {
  if (muted.value) return;
  const ctx = getAudioContext();
  if (!ctx) return;

  // Resume suspended context (browser autoplay policy)
  if (ctx.state === "suspended") {
    ctx.resume();
  }

  const osc = ctx.createOscillator();
  const gain = ctx.createGain();

  osc.type = type;
  osc.frequency.setValueAtTime(frequency, ctx.currentTime);
  gain.gain.setValueAtTime(volume, ctx.currentTime);
  gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);

  osc.connect(gain);
  gain.connect(ctx.destination);

  osc.start(ctx.currentTime);
  osc.stop(ctx.currentTime + duration);
}

function playNoise(duration: number, volume = 0.15) {
  if (muted.value) return;
  const ctx = getAudioContext();
  if (!ctx) return;

  if (ctx.state === "suspended") {
    ctx.resume();
  }

  const bufferSize = ctx.sampleRate * duration;
  const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
  const data = buffer.getChannelData(0);

  for (let i = 0; i < bufferSize; i++) {
    data[i] = Math.random() * 2 - 1;
  }

  const source = ctx.createBufferSource();
  source.buffer = buffer;

  const gain = ctx.createGain();
  gain.gain.setValueAtTime(volume, ctx.currentTime);
  gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);

  source.connect(gain);
  gain.connect(ctx.destination);

  source.start(ctx.currentTime);
}

const sounds: Record<SoundName, () => void> = {
  dice: () => {
    // Dice rattle: short burst of noise
    playNoise(0.15, 0.12);
    setTimeout(() => playNoise(0.1, 0.08), 80);
    setTimeout(() => playNoise(0.08, 0.06), 150);
  },
  move: () => {
    // Soft click: short high tone
    playTone(800, 0.08, "sine", 0.15);
  },
  kick: () => {
    // Impact: low thud + higher accent
    playTone(150, 0.2, "square", 0.25);
    setTimeout(() => playTone(400, 0.1, "sawtooth", 0.15), 50);
  },
  bonus: () => {
    // Positive chime: ascending notes
    playTone(523, 0.12, "sine", 0.2);
    setTimeout(() => playTone(659, 0.12, "sine", 0.2), 100);
    setTimeout(() => playTone(784, 0.2, "sine", 0.25), 200);
  },
  win: () => {
    // Fanfare: triumphant ascending sequence
    playTone(523, 0.15, "square", 0.2);
    setTimeout(() => playTone(659, 0.15, "square", 0.2), 150);
    setTimeout(() => playTone(784, 0.15, "square", 0.2), 300);
    setTimeout(() => playTone(1047, 0.4, "square", 0.3), 450);
  },
  turn: () => {
    // Subtle notification: two gentle notes
    playTone(660, 0.08, "sine", 0.1);
    setTimeout(() => playTone(880, 0.1, "sine", 0.12), 80);
  },
};

function play(name: SoundName) {
  if (muted.value) return;
  try {
    sounds[name]();
  } catch {
    // Ignore audio errors
  }
}

export function useSoundEffects() {
  return {
    play,
    muted,
    toggleMute,
  };
}
