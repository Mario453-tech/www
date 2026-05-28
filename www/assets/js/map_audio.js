/**
 * map_audio.js  Losowe odtwarzanie muzyki ta na mapie.
 *
 * Pliki MP3 (lub OGG/WAV/M4A) wrzu do: /assets/audio/
 * Lista plikw pobierana automatycznie z /assets/audio/list.php
 *
 * Gono domylna: 40% (uytkownik moe zmieni suwakiem).
 * Ustawienie gonoci zapamitywane w localStorage.
 */
(function () {
    'use strict';

    //  Konfiguracja 
    const TRACKS_ENDPOINT  = '/assets/audio/list.php';
    const DEFAULT_VOLUME   = 0.40;
    const STORAGE_KEY      = 'wg_map_volume';
    const FADE_DURATION_MS = 1500;   // czas fade-in/out w ms
    const FADE_STEPS       = 40;     // krokw animacji fade

    //  Stan 
    let TRACKS      = [];
    let audio       = null;
    let currentIdx  = -1;
    let isMuted     = false;
    let targetVol   = parseFloat(localStorage.getItem(STORAGE_KEY) ?? DEFAULT_VOLUME);
    if (isNaN(targetVol) || targetVol < 0 || targetVol > 1) targetVol = DEFAULT_VOLUME;

    //  Losowanie (bez powtrze kolejnych) 
    function pickNext() {
        if (TRACKS.length === 1) return 0;
        let idx;
        do { idx = Math.floor(Math.random() * TRACKS.length); } while (idx === currentIdx);
        return idx;
    }

    //  Fade 
    function fadeTo(target, doneCallback) {
        if (!audio) return;
        const start    = audio.volume;
        const delta    = target - start;
        const interval = FADE_DURATION_MS / FADE_STEPS;
        let   step     = 0;
        const timer = setInterval(() => {
            step++;
            audio.volume = Math.min(1, Math.max(0, start + delta * (step / FADE_STEPS)));
            if (step >= FADE_STEPS) {
                clearInterval(timer);
                if (doneCallback) doneCallback();
            }
        }, interval);
    }

    //  Odtworzenie kolejnego utworu 
    function playNext() {
        if (!TRACKS.length) return;
        currentIdx      = pickNext();
        audio           = new Audio(TRACKS[currentIdx]);
        audio.volume    = 0;
        audio.preload   = 'auto';
        audio.onended   = () => { fadeTo(0, playNext); };
        audio.onerror   = () => { setTimeout(playNext, 3000); };
        audio.play().then(() => {
            if (!isMuted) fadeTo(targetVol);
        }).catch(() => {
            // Autoplay zablokowany  poczekaj na pierwsz interakcj
            document.addEventListener('click', function tryPlay() {
                audio.play().then(() => { if (!isMuted) fadeTo(targetVol); });
                document.removeEventListener('click', tryPlay);
            }, { once: true });
        });
    }

    //  Wyciszenie / odgonienie 
    function toggleMute() {
        isMuted = !isMuted;
        if (audio) {
            isMuted ? fadeTo(0) : fadeTo(targetVol);
        }
        const btn = document.getElementById('map-audio-mute');
        if (btn) btn.textContent = isMuted ? '' : '';
    }

    //  Budowa widgetu 
    function buildWidget() {
        const wrap = document.createElement('div');
        wrap.id    = 'map-audio-widget';
        wrap.innerHTML = `
            <button id="map-audio-mute" title="Wycisz / Odgonij"></button>
            <input  id="map-audio-vol"  type="range" min="0" max="1" step="0.01"
                    value="${targetVol}" title="Gono muzyki">
        `;
        document.body.appendChild(wrap);

        document.getElementById('map-audio-mute').addEventListener('click', toggleMute);
        document.getElementById('map-audio-vol').addEventListener('input', function () {
            targetVol = parseFloat(this.value);
            localStorage.setItem(STORAGE_KEY, targetVol);
            if (!isMuted && audio) audio.volume = targetVol;
        });
    }

    //  Style widgetu 
    function injectStyles() {
        const s = document.createElement('style');
        s.textContent = `
            #map-audio-widget {
                position: fixed;
                bottom: 18px;
                right: 18px;
                z-index: 9000;
                display: flex;
                align-items: center;
                gap: 8px;
                background: rgba(10, 14, 26, 0.82);
                border: 1px solid rgba(255,255,255,0.10);
                border-radius: 24px;
                padding: 6px 12px 6px 8px;
                backdrop-filter: blur(6px);
                box-shadow: 0 2px 12px rgba(0,0,0,0.5);
            }
            #map-audio-mute {
                background: none;
                border: none;
                font-size: 1.2rem;
                cursor: pointer;
                line-height: 1;
                padding: 0;
            }
            #map-audio-vol {
                width: 80px;
                accent-color: var(--accent, #4a9eff);
                cursor: pointer;
            }
        `;
        document.head.appendChild(s);
    }

    //  Pobierz list utworw z serwera, potem startuj 
    function init() {
        injectStyles();
        buildWidget();

        fetch(TRACKS_ENDPOINT)
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(list => {
                if (!Array.isArray(list) || !list.length) {
                    console.warn('[map_audio] Brak plikw audio w /assets/audio/');
                    return;
                }
                // Przetasuj od razu, eby kada sesja startowaa losowo
                TRACKS = list.sort(() => Math.random() - 0.5);
                playNext();
            })
            .catch(err => {
                console.warn('[map_audio] Nie mona pobra listy utworw:', err);
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
