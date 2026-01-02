// Utility functions for map operations
const MapUtils = {
    /**
     * Clean province name by removing code suffix
     */
    cleanName: function(str) {
        return str ? str.split(' (Mã')[0].trim() : '';
    },

    /**
     * Get province map image path from databando folder
     * Tries multiple naming patterns to match the image file
     */
    getProvinceMapImagePath: async function(provinceName) {
        if (!provinceName) return null;
        
        // Remove common prefixes and clean the name
        let clean = provinceName
            .replace(/^(Thủ đô|thành phố|tỉnh)\s+/i, '') // Remove "Thủ đô", "thành phố", "tỉnh"
            .trim();
        
        // Possible file name patterns
        const patterns = [
            `Bản đồ ${clean}.jpg`,
            `Bản đồ ${clean}.jpeg`,
            `Bản đồ tỉnh ${clean}.jpg`,
            `Bản đồ tỉnh ${clean}.jpeg`,
            `Bản đồ thành phố ${clean}.jpg`,
            `Bản đồ thành phố ${clean}.jpeg`,
            `Bản đồ Thủ đô ${clean}.jpg`,
            `Bản đồ Thủ đô ${clean}.jpeg`,
        ];
        
        // Also try with original name
        if (provinceName !== clean) {
            patterns.unshift(
                `Bản đồ ${provinceName}.jpg`,
                `Bản đồ ${provinceName}.jpeg`,
                `Bản đồ tỉnh ${provinceName}.jpg`,
                `Bản đồ tỉnh ${provinceName}.jpeg`
            );
        }
        
        // Test each pattern
        for (const pattern of patterns) {
            const path = `./databando/${pattern}`;
            try {
                const response = await fetch(path, { method: 'HEAD' });
                if (response.ok) {
                    return path;
                }
            } catch (e) {
                // Continue to next pattern
            }
        }
        
        return null;
    },

    /**
     * Fetch images from Google Custom Search API
     */
    fetchImages: async function(query) {
        try {
            const url = `https://vnmap-safeschool.net/geoserver/api/search-images?q=${encodeURIComponent(query)}`;
            const res = await fetch(url);
            if (res.ok) return await res.json();
        } catch (e) {
            // Silently fail
        }
        return { imageUrls: [] };
    },

    /**
     * Render image gallery in container
     */
    renderGallery: function(container, images, alt) {
        container.innerHTML = '';
        if (images && images.length > 0) {
            images.slice(0, 3).forEach(url => {
                container.innerHTML += `<a href="${url}" target="_blank" class="block h-full overflow-hidden rounded-lg hover:opacity-90 transition"><img src="${url}" alt="${alt}" class="w-full h-full object-cover"></a>`;
            });
        } else {
            container.innerHTML = `<div class="col-span-3 text-center text-xs text-gray-400 py-4 bg-gray-50 rounded">Không có hình ảnh</div>`;
        }
    }
};
