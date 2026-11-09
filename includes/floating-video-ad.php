<?php
/**
 * ELECTRINC - Floating Portrait Video Advertisement Component
 * 
 * Features:
 * - Floating Position: Bottom-right corner of the website
 * - Size: ~165px × 290px (9:16 portrait format)
 * - Click to Expand: Clicking the video expands it smoothly into a large, centered modal/lightbox
 * - Audio: Muted autoplay initially; automatically un-mutes upon expand or via user toggle
 * - Continuous Sequence: Autoplays all provided video ads in sequence, looping indefinitely
 * - Interactive Controls: Expand/Minimize, Close (X), Play/Pause, Mute/Unmute, Prev/Next, Scrub Bar
 * - Responsive: Desktop, tablet, and mobile safe (dock-clearing in floating mode)
 */

// Gather all video advertisement files
$floatingAdVideos = [
    'assets/videos/hero-video-1.mp4',
    'assets/videos/hero-video-2.mp4'
];

$videosDirectory = __DIR__ . '/../assets/videos';
if (is_dir($videosDirectory)) {
    $discoveredVideos = [];
    $files = scandir($videosDirectory);
    foreach ($files as $file) {
        if (preg_match('/^hero-video-\d+\.mp4$/i', $file)) {
            $discoveredVideos[] = 'assets/videos/' . $file;
        }
    }
    if (!empty($discoveredVideos)) {
        sort($discoveredVideos, SORT_NATURAL);
        $floatingAdVideos = $discoveredVideos;
    }
}
?>

<!-- ============ FLOATING PORTRAIT VIDEO ADVERTISEMENT ============ -->
<style>
    /* Dark Blurred Backdrop for Expanded State */
    #electrincAdBackdrop {
        position: fixed;
        inset: 0;
        background: rgba(10, 31, 22, 0.82);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        z-index: 99990;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.35s ease;
    }

    #electrincAdBackdrop.ad-backdrop-active {
        opacity: 1;
        pointer-events: auto;
    }

    /* Main Floating Container (Small Floating State by Default) */
    #electrincFloatingAd {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 165px;
        height: 290px;
        z-index: 45;
        border-radius: 14px;
        overflow: hidden;
        background: #000000;
        border: 1.5px solid rgba(255, 255, 255, 0.45);
        box-shadow: 0 16px 36px -6px rgba(0, 0, 0, 0.55), 0 6px 16px -2px rgba(0, 0, 0, 0.35);
        transition: width 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                    height 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                    bottom 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                    right 0.4s cubic-bezier(0.16, 1, 0.3, 1),
                    border-radius 0.3s ease,
                    box-shadow 0.35s ease,
                    opacity 0.3s ease,
                    transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        user-select: none;
        -webkit-user-select: none;
    }

    /* Hover effect in floating mode */
    #electrincFloatingAd:not(.ad-expanded):hover {
        transform: translateY(-4px) scale(1.025);
        box-shadow: 0 22px 45px -8px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.8);
        border-color: rgba(255, 255, 255, 0.85);
    }

    /* Expanded View (Centered Large Lightbox on Screen) */
    #electrincFloatingAd.ad-expanded {
        z-index: 99995 !important;
        width: min(390px, 92vw) !important;
        height: min(690px, 86vh) !important;
        right: calc(50vw - min(390px, 92vw) / 2) !important;
        bottom: calc(50vh - min(690px, 86vh) / 2) !important;
        border-radius: 22px !important;
        border: 1.5px solid rgba(255, 255, 255, 0.3) !important;
        box-shadow: 0 25px 70px -15px rgba(0, 0, 0, 0.95), 0 0 0 1px rgba(255, 255, 255, 0.15) !important;
        transform: none !important;
    }

    /* Completely Hidden */
    #electrincFloatingAd.ad-hidden {
        opacity: 0 !important;
        pointer-events: none !important;
        transform: scale(0.8) translateY(20px) !important;
        display: none !important;
    }

    /* Dynamic UI control visibility */
    .ad-expanded-only {
        display: none !important;
    }
    #electrincFloatingAd.ad-expanded .ad-expanded-only {
        display: flex !important;
    }
    #electrincFloatingAd.ad-expanded .ad-floating-only {
        display: none !important;
    }

    /* Floating Tooltip hint */
    #electrincFloatingAd:not(.ad-expanded):hover #electrincAdExpandPill {
        opacity: 1;
        transform: translateY(0);
    }

    /* Tablet adjustment (floating mode only) */
    @media (max-width: 1023px) and (min-width: 641px) {
        #electrincFloatingAd:not(.ad-expanded) {
            width: 145px;
            height: 255px;
            bottom: 76px;
            right: 16px;
        }
    }

    /* Mobile adjustment (dock safe, floating mode only) */
    @media (max-width: 640px) {
        #electrincFloatingAd:not(.ad-expanded) {
            width: 125px;
            height: 220px;
            bottom: 74px;
            right: 12px;
            border-radius: 12px;
        }
    }

    /* Shift back-to-top button left on desktop so it never overlaps */
    @media (min-width: 640px) {
        body:has(#electrincFloatingAd:not(.ad-hidden):not(.ad-expanded)) #backToTopBtn {
            right: 195px !important;
        }
    }
</style>

<!-- Fullscreen Backdrop (Clicking outside shrinks the video) -->
<div id="electrincAdBackdrop" title="Click outside to minimize" aria-hidden="true"></div>

<aside id="electrincFloatingAd" aria-label="Featured Advertisement" role="complementary">
    <!-- Main Video Element -->
    <video
        id="electrincAdVideo"
        class="w-full h-full object-cover block cursor-pointer bg-black"
        playsinline
        webkit-playsinline
        muted
        autoplay
        preload="auto"
        title="Click to Expand or Play / Pause"
    ></video>

    <!-- Floating Mode Hover Expand Hint Pill -->
    <div id="electrincAdExpandPill" class="ad-floating-only absolute inset-x-0 bottom-12 flex items-center justify-center pointer-events-none opacity-0 translate-y-1 transition-all duration-200 z-20">
        <span class="bg-black/85 backdrop-blur-md text-white text-[10px] font-bold px-2.5 py-1 rounded-full border border-white/20 shadow-lg flex items-center gap-1.5">
            <svg class="w-3 h-3 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 3 21 3 21 9"></polyline>
                <polyline points="9 21 3 21 3 15"></polyline>
                <line x1="21" y1="3" x2="14" y2="10"></line>
                <line x1="3" y1="21" x2="10" y2="14"></line>
            </svg>
            Click to Expand
        </span>
    </div>

    <!-- Top Controls Overlay -->
    <div class="absolute top-0 inset-x-0 p-2.5 sm:p-3 flex items-center justify-between pointer-events-none bg-gradient-to-b from-black/85 via-black/40 to-transparent z-20">
        <!-- Badge & Counter -->
        <div class="flex items-center gap-1.5 pointer-events-none">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-600/90 text-white text-[9px] font-black uppercase tracking-wider shadow">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                Spotlight
            </span>
            <span id="electrincAdCounter" class="px-1.5 py-0.5 rounded-full bg-black/60 text-white/90 text-[9px] font-bold backdrop-blur-sm border border-white/10">
                1/<?= count($floatingAdVideos) ?>
            </span>
            <!-- Brand name visible in expanded mode -->
            <span class="ad-expanded-only text-xs font-bold text-white/90 drop-shadow ml-1">
                ELECTRINC EV
            </span>
        </div>

        <!-- Action Buttons (Expand, Minimize, Close) -->
        <div class="flex items-center gap-1.5 pointer-events-auto">
            <!-- Expand Button (Floating mode only) -->
            <button
                type="button"
                id="electrincAdExpandBtn"
                class="ad-floating-only w-6 h-6 rounded-full bg-black/60 hover:bg-black/90 active:scale-90 text-white flex items-center justify-center transition shadow backdrop-blur-md border border-white/20 hover:border-white/50"
                title="Expand to Large View"
                aria-label="Expand to Large View"
            >
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <polyline points="9 21 3 21 3 15"></polyline>
                    <line x1="21" y1="3" x2="14" y2="10"></line>
                    <line x1="3" y1="21" x2="10" y2="14"></line>
                </svg>
            </button>

            <!-- Minimize Button (Expanded mode only) -->
            <button
                type="button"
                id="electrincAdMinimizeBtn"
                class="ad-expanded-only w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-black/60 hover:bg-black/90 active:scale-90 text-white items-center justify-center transition shadow backdrop-blur-md border border-white/20 hover:border-white/50"
                title="Minimize to Corner"
                aria-label="Minimize to Corner"
            >
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="4 14 10 14 10 20"></polyline>
                    <polyline points="20 10 14 10 14 4"></polyline>
                    <line x1="14" y1="10" x2="21" y2="3"></line>
                    <line x1="3" y1="21" x2="10" y2="14"></line>
                </svg>
            </button>

            <!-- Close Button (Always Available) -->
            <button
                type="button"
                id="electrincAdCloseBtn"
                class="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-black/60 hover:bg-black/90 active:scale-90 text-white flex items-center justify-center transition shadow backdrop-blur-md border border-white/20 hover:border-white/50"
                title="Close Advertisement"
                aria-label="Close Advertisement"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Previous / Next Video Arrows (Expanded Mode Only) -->
    <button
        type="button"
        id="electrincAdPrevBtn"
        class="ad-expanded-only pointer-events-auto absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/60 hover:bg-black/90 text-white items-center justify-center backdrop-blur-md border border-white/20 shadow-lg transition active:scale-90 z-20"
        title="Previous Video"
        aria-label="Previous Video"
    >
        <svg class="w-4 h-4 mr-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
    <button
        type="button"
        id="electrincAdNextBtn"
        class="ad-expanded-only pointer-events-auto absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/60 hover:bg-black/90 text-white items-center justify-center backdrop-blur-md border border-white/20 shadow-lg transition active:scale-90 z-20"
        title="Next Video"
        aria-label="Next Video"
    >
        <svg class="w-4 h-4 ml-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>

    <!-- Center Play/Pause Flash Indicator -->
    <div id="electrincAdCenterIndicator" class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 transition-opacity duration-200 z-20">
        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-black/70 backdrop-blur-md text-white flex items-center justify-center shadow-2xl border border-white/25">
            <svg id="electrincAdCenterIcon" class="w-6 h-6 sm:w-7 sm:h-7" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
        </div>
    </div>

    <!-- Bottom Controls Overlay -->
    <div class="absolute bottom-0 inset-x-0 p-2.5 sm:p-3 pb-3 sm:pb-3.5 flex items-center justify-between pointer-events-none bg-gradient-to-t from-black/90 via-black/45 to-transparent z-20">
        <!-- Play / Pause Toggle -->
        <button
            type="button"
            id="electrincAdPlayBtn"
            class="pointer-events-auto w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-black/60 hover:bg-black/90 active:scale-90 text-white flex items-center justify-center transition shadow backdrop-blur-md border border-white/20 hover:border-white/50"
            title="Play / Pause"
            aria-label="Play / Pause"
        >
            <!-- Pause Icon (shown when playing) -->
            <svg id="electrincAdPauseSvg" class="w-3.5 h-3.5 sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="currentColor">
                <rect x="6" y="4" width="4" height="16" rx="1"></rect>
                <rect x="14" y="4" width="4" height="16" rx="1"></rect>
            </svg>
            <!-- Play Icon (shown when paused) -->
            <svg id="electrincAdPlaySvg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 hidden ml-0.5" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
        </button>

        <!-- Tap to unmute hint in expanded mode if muted -->
        <span id="electrincAdMuteHint" class="ad-expanded-only text-[11px] font-semibold text-white/80 pointer-events-none drop-shadow"></span>

        <!-- Mute / Unmute Toggle -->
        <button
            type="button"
            id="electrincAdMuteBtn"
            class="pointer-events-auto w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-black/60 hover:bg-black/90 active:scale-90 text-white flex items-center justify-center transition shadow backdrop-blur-md border border-white/20 hover:border-white/50"
            title="Mute / Unmute"
            aria-label="Mute / Unmute"
        >
            <!-- Muted Icon (slash) -->
            <svg id="electrincAdMutedSvg" class="w-3.5 h-3.5 sm:w-4 sm:h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                <line x1="23" y1="9" x2="17" y2="15"></line>
                <line x1="17" y1="9" x2="23" y2="15"></line>
            </svg>
            <!-- Unmuted Icon (sound waves) -->
            <svg id="electrincAdUnmutedSvg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                <path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path>
            </svg>
        </button>
    </div>

    <!-- Bottom Interactive Progress Track (Clickable to scrub) -->
    <div id="electrincAdProgressTrack" class="absolute bottom-0 inset-x-0 h-[3px] bg-white/25 cursor-pointer z-30" title="Seek video">
        <div id="electrincAdProgressBar" class="h-full bg-emerald-500 transition-all duration-150 ease-linear" style="width: 0%;"></div>
    </div>
</aside>

<script>
(function() {
    'use strict';

    const playlist = <?= json_encode($floatingAdVideos) ?>;
    if (!playlist || playlist.length === 0) return;

    const adContainer       = document.getElementById('electrincFloatingAd');
    const backdrop          = document.getElementById('electrincAdBackdrop');
    const video             = document.getElementById('electrincAdVideo');
    const expandBtn         = document.getElementById('electrincAdExpandBtn');
    const minimizeBtn       = document.getElementById('electrincAdMinimizeBtn');
    const closeBtn          = document.getElementById('electrincAdCloseBtn');
    const prevBtn           = document.getElementById('electrincAdPrevBtn');
    const nextBtn           = document.getElementById('electrincAdNextBtn');
    const playBtn           = document.getElementById('electrincAdPlayBtn');
    const playSvg           = document.getElementById('electrincAdPlaySvg');
    const pauseSvg          = document.getElementById('electrincAdPauseSvg');
    const muteBtn           = document.getElementById('electrincAdMuteBtn');
    const mutedSvg          = document.getElementById('electrincAdMutedSvg');
    const unmutedSvg        = document.getElementById('electrincAdUnmutedSvg');
    const muteHint          = document.getElementById('electrincAdMuteHint');
    const counter           = document.getElementById('electrincAdCounter');
    const progressTrack     = document.getElementById('electrincAdProgressTrack');
    const progressBar       = document.getElementById('electrincAdProgressBar');
    const centerIndicator   = document.getElementById('electrincAdCenterIndicator');
    const centerIcon        = document.getElementById('electrincAdCenterIcon');

    let currentIndex = 0;
    let isExpanded   = false;
    let centerTimer  = null;

    // Shift back to top button on legacy browsers that don't support :has()
    function syncBackToTop(showAd) {
        const btt = document.getElementById('backToTopBtn');
        if (btt && window.innerWidth >= 640) {
            btt.style.right = (showAd && !isExpanded) ? '195px' : '';
        }
    }

    function flashCenterIcon(isPlay) {
        if (!centerIndicator || !centerIcon) return;
        if (isPlay) {
            centerIcon.innerHTML = '<polygon points="5 3 19 12 5 21 5 3"></polygon>';
        } else {
            centerIcon.innerHTML = '<rect x="6" y="4" width="4" height="16" rx="1"></rect><rect x="14" y="4" width="4" height="16" rx="1"></rect>';
        }
        centerIndicator.style.opacity = '1';
        clearTimeout(centerTimer);
        centerTimer = setTimeout(() => {
            centerIndicator.style.opacity = '0';
        }, 400);
    }

    function updatePlayStateUI(isPlaying) {
        if (!playSvg || !pauseSvg) return;
        if (isPlaying) {
            playSvg.classList.add('hidden');
            pauseSvg.classList.remove('hidden');
        } else {
            pauseSvg.classList.add('hidden');
            playSvg.classList.remove('hidden');
        }
    }

    function updateMuteStateUI(isMuted) {
        if (!mutedSvg || !unmutedSvg) return;
        if (isMuted) {
            mutedSvg.classList.remove('hidden');
            unmutedSvg.classList.add('hidden');
            if (muteHint) muteHint.textContent = 'Muted';
        } else {
            mutedSvg.classList.add('hidden');
            unmutedSvg.classList.remove('hidden');
            if (muteHint) muteHint.textContent = '';
        }
    }

    function loadAndPlay(index) {
        if (index < 0 || index >= playlist.length) index = 0;
        currentIndex = index;

        if (counter) {
            counter.textContent = `${currentIndex + 1}/${playlist.length}`;
        }
        if (progressBar) {
            progressBar.style.width = '0%';
        }

        video.src = playlist[currentIndex];
        video.load();

        const playPromise = video.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                updatePlayStateUI(true);
            }).catch(() => {
                // Autoplay policy fallback: mute and retry
                video.muted = true;
                updateMuteStateUI(true);
                video.play().then(() => updatePlayStateUI(true)).catch(() => updatePlayStateUI(false));
            });
        }
    }

    function togglePlay() {
        if (video.paused) {
            video.play().then(() => {
                updatePlayStateUI(true);
                flashCenterIcon(true);
            }).catch(() => {});
        } else {
            video.pause();
            updatePlayStateUI(false);
            flashCenterIcon(false);
        }
    }

    function toggleMute() {
        video.muted = !video.muted;
        updateMuteStateUI(video.muted);
    }

    // EXPAND: Transform from floating bottom-right into large centered modal
    function expandAd() {
        if (isExpanded) return;
        isExpanded = true;
        adContainer.classList.add('ad-expanded');
        if (backdrop) backdrop.classList.add('ad-backdrop-active');
        document.body.classList.add('overflow-hidden');

        // Automatically un-mute upon user expansion click if desired
        if (video.muted) {
            video.muted = false;
            updateMuteStateUI(false);
        }

        // Resume play if paused
        if (video.paused) {
            video.play().catch(() => {});
        }
        syncBackToTop(false);
    }

    // MINIMIZE / SHRINK: Return to small floating bottom-right corner
    function shrinkAd() {
        if (!isExpanded) return;
        isExpanded = false;
        adContainer.classList.remove('ad-expanded');
        if (backdrop) backdrop.classList.remove('ad-backdrop-active');
        document.body.classList.remove('overflow-hidden');
        syncBackToTop(true);
    }

    // CLOSE: Completely hide and terminate ad
    function closeAd() {
        shrinkAd();
        video.pause();
        video.removeAttribute('src');
        video.load();
        adContainer.classList.add('ad-hidden');
        syncBackToTop(false);
    }

    // Next / Previous Video Navigation
    function playNext() {
        const next = (currentIndex + 1) % playlist.length;
        loadAndPlay(next);
    }

    function playPrev() {
        const prev = (currentIndex - 1 + playlist.length) % playlist.length;
        loadAndPlay(prev);
    }

    // CLICK HANDLER ON VIDEO BODY:
    // If floating: Clicking expands the video to big view!
    // If expanded: Clicking toggles Play / Pause.
    video.addEventListener('click', function(e) {
        if (!isExpanded) {
            expandAd();
        } else {
            togglePlay();
        }
    });

    // Expand & Minimize Buttons
    if (expandBtn) expandBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        expandAd();
    });

    if (minimizeBtn) minimizeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        shrinkAd();
    });

    // Close Button
    if (closeBtn) closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        closeAd();
    });

    // Backdrop Click shrinks the video
    if (backdrop) backdrop.addEventListener('click', function() {
        shrinkAd();
    });

    // Prev / Next Buttons
    if (prevBtn) prevBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        playPrev();
    });
    if (nextBtn) nextBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        playNext();
    });

    // Play / Pause Button
    if (playBtn) playBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        togglePlay();
    });

    // Mute / Unmute Button
    if (muteBtn) muteBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleMute();
    });

    // Interactive progress track scrubbing
    if (progressTrack) {
        progressTrack.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!video.duration) return;
            const rect = progressTrack.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const ratio = Math.max(0, Math.min(1, clickX / rect.width));
            video.currentTime = ratio * video.duration;
        });
    }

    // Advance to next video in continuous sequence when current finishes
    video.addEventListener('ended', function() {
        const nextIndex = (currentIndex + 1) % playlist.length;
        loadAndPlay(nextIndex);
    });

    // Handle loading errors gracefully by skipping to the next video
    video.addEventListener('error', function() {
        setTimeout(function() {
            const nextIndex = (currentIndex + 1) % playlist.length;
            loadAndPlay(nextIndex);
        }, 600);
    });

    // Update progress bar as video plays
    video.addEventListener('timeupdate', function() {
        if (video.duration && progressBar) {
            const percent = (video.currentTime / video.duration) * 100;
            progressBar.style.width = percent.toFixed(2) + '%';
        }
    });

    video.addEventListener('play', function() { updatePlayStateUI(true); });
    video.addEventListener('pause', function() { updatePlayStateUI(false); });

    // Keyboard shortcuts (Escape key shrinks or closes expanded ad)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isExpanded) {
            shrinkAd();
        }
    });

    // Resize handler for back-to-top synchronization
    window.addEventListener('resize', function() {
        if (!adContainer.classList.contains('ad-hidden') && !isExpanded) {
            syncBackToTop(true);
        }
    });

    // Initialize playback
    syncBackToTop(true);
    updateMuteStateUI(true);
    loadAndPlay(0);
})();
</script>
