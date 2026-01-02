// Minimap functionality - shows province overview map
const Minimap = {
    /**
     * Show minimap with province map image
     */
    show: async function(provinceName) {
        const minimapOverlay = document.getElementById('minimap-overlay');
        const minimapImage = document.getElementById('minimap-image');
        if (!minimapOverlay || !minimapImage) return;

        // Ensure click handler is always present (re-attached every call for safety)
        minimapOverlay.onclick = function(e) {
            e.stopPropagation();
            console.log('[Minimap] Click detected.');
            if (typeof window.mapResetView === 'function') {
                console.log('[Minimap] Calling mapResetView');
                window.mapResetView();
            } else {
                console.error('[Minimap] mapResetView is not defined on window!', window.mapResetView);
            }
        };

        // Get the image path using MapUtils
        const imagePath = await MapUtils.getProvinceMapImagePath(provinceName);
        if (imagePath) {
            minimapImage.src = imagePath;
            minimapImage.onload = () => {
                minimapOverlay.style.opacity = '1';
                minimapOverlay.style.pointerEvents = 'auto';
                minimapOverlay.classList.remove('pointer-events-none');
            };
            minimapImage.onerror = () => {
                minimapOverlay.style.opacity = '0';
                minimapOverlay.style.pointerEvents = 'none';
                if (!minimapOverlay.classList.contains('pointer-events-none')) {
                  minimapOverlay.classList.add('pointer-events-none');
                }
            };
        } else {
            minimapOverlay.style.opacity = '0';
            minimapOverlay.style.pointerEvents = 'none';
            if (!minimapOverlay.classList.contains('pointer-events-none')) {
              minimapOverlay.classList.add('pointer-events-none');
            }
        }
    },

    /**
     * Hide minimap
     */
    hide: function() {
        const minimapOverlay = document.getElementById('minimap-overlay');
        if (minimapOverlay) {
            minimapOverlay.style.opacity = '0';
            minimapOverlay.style.pointerEvents = 'none';
            if (!minimapOverlay.classList.contains('pointer-events-none')) {
              minimapOverlay.classList.add('pointer-events-none');
            }
        }
    }
};
