document.addEventListener('DOMContentLoaded', () => {
        // --- CONFIGURATION ---
        const API_URL = 'https://vnmap-safeschool.net/api.php';
        const TTS_BACKEND = 'tts.php';
        
        // --- DOM ELEMENTS ---
        const mapContainer = document.getElementById('map-container');
        const titleText = document.getElementById('title-text');
        const loader = document.getElementById('loader');
        const tooltip = document.getElementById('tooltip');
        
        // Details Panels
        const provinceDetailsContent = document.getElementById('province-details-content');
        const provinceStats = document.getElementById('province-stats');
        const provinceGallery = document.getElementById('province-image-gallery');
        const communePanel = document.getElementById('commune-panel');
        const communeName = document.getElementById('commune-name');
        const communeDetails = document.getElementById('commune-details');
        
        // Search & Interactive
        const communeSearch = document.getElementById('commune-search');
        const searchResults = document.getElementById('search-results');
        const zoomInBtn = document.getElementById('zoom-in');
        const zoomOutBtn = document.getElementById('zoom-out');

        // TTS Elements
        const audioPlayer = document.getElementById('global-player');
        const btnSpeakProvince = document.getElementById('speak-province-btn');
        const btnSpeakCommune = document.getElementById('speak-commune-btn');
        
        // State
        let allCommunes = [], geojsonData = [], svg;
        let viewBox = { x: 0, y: 0, w: 1, h: 1 };
        let initialViewBox = null; // Store initial viewBox for reset
        let activePath = null;
        let currentProvinceName = "";

        // Icons
        const ICON_PLAY = `<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>`;
        const ICON_STOP = `<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z"/></svg>`;
        const ICON_LOADING = `<svg class="animate-spin h-5 w-5 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;

        // --- 1. GET ID FROM URL ---
        const urlParams = new URLSearchParams(window.location.search);
        const currentProvinceId = urlParams.get('id');

        if (!currentProvinceId) {
            loader.innerHTML = `<p class="text-red-500 font-bold bg-white p-4 rounded">Thiếu ID Tỉnh trên URL</p>`;
            return;
        }

        // --- 2. TTS LOGIC (CHUNKED FOR LONG TEXT) ---
        
        let ttsQueue = [];
        let isTTSPlaying = false;
        let currentTTSButton = null;
        let ttsAbortController = null;

        function getCurrentLang() {
            const match = document.cookie.match(/googtrans=\/vi\/([^;]+)/);
            if (match && match[1]) {
                let lang = match[1].toLowerCase();
                if (lang.includes('zh')) return 'cn'; 
                return lang;
            }
            return 'vi'; 
        }

        function resetTTS() {
            audioPlayer.pause();
            audioPlayer.currentTime = 0;
            ttsQueue = [];
            isTTSPlaying = false;
            
            if (currentTTSButton) {
                currentTTSButton.innerHTML = ICON_PLAY;
                currentTTSButton.disabled = false;
            }
            currentTTSButton = null;
            if (ttsAbortController) {
                ttsAbortController.abort();
                ttsAbortController = null;
            }
        }

        audioPlayer.onended = async () => {
            if(ttsQueue.length > 0) {
                await playNextChunk();
            } else {
                resetTTS();
            }
        };

        async function playNextChunk() {
            if (ttsQueue.length === 0) return;
            
            const textChunk = ttsQueue.shift();
            const lang = getCurrentLang();

            try {
                ttsAbortController = new AbortController();
                const response = await fetch(TTS_BACKEND, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text: textChunk, lang: lang }),
                    signal: ttsAbortController.signal
                });

                if (!response.ok) throw new Error("TTS API Error");

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                
                audioPlayer.src = url;
                audioPlayer.play();
            } catch (error) {
                if(error.name === 'AbortError') return;
                console.error("Chunk failed:", error);
                playNextChunk(); // Try next chunk
            }
        }

        function splitTextIntoChunks(text, maxLength = 200) {
            const cleanText = text.replace(/\s+/g, ' ').trim();
            if (!cleanText) return [];

            const sentences = cleanText.match(/[^.?!;:]+([.?!;:]+|$)/g) || [cleanText];
            const chunks = [];
            let currentChunk = "";

            sentences.forEach(sentence => {
                if ((currentChunk.length + sentence.length) > maxLength && currentChunk.length > 0) {
                    chunks.push(currentChunk.trim());
                    currentChunk = "";
                }
                currentChunk += sentence + " ";
            });
            if (currentChunk.trim().length > 0) chunks.push(currentChunk.trim());
            return chunks;
        }

        function speakText(text, button) {
            if (isTTSPlaying && currentTTSButton === button) {
                resetTTS();
                return;
            }
            if (isTTSPlaying) resetTTS();

            const chunks = splitTextIntoChunks(text);
            if (chunks.length === 0) return;

            currentTTSButton = button;
            button.innerHTML = ICON_LOADING;
            isTTSPlaying = true;
            button.innerHTML = ICON_STOP;

            ttsQueue = chunks;
            playNextChunk();
        }

        btnSpeakProvince.addEventListener('click', () => {
            const stats = provinceStats.innerText;
            const details = provinceDetailsContent.innerText;
            const fullText = `${currentProvinceName}. ${stats}. ${details}`;
            speakText(fullText, btnSpeakProvince);
        });

        btnSpeakCommune.addEventListener('click', () => {
            const name = communeName.innerText;
            const details = communeDetails.innerText;
            const fullText = `${name}. ${details}`;
            speakText(fullText, btnSpeakCommune);
        });


        // --- 3. DATA & MAP LOGIC ---

        function cleanName(str) { return str.split(' (Mã')[0].trim(); }
        
        async function fetchImages(query) {
            try {
                const url = `https://vnmap-safeschool.net/geoserver/api/search-images?q=${encodeURIComponent(query)}`;
                const res = await fetch(url);
                if(res.ok) return await res.json();
            } catch(e) {}
            return { imageUrls: [] };
        }

        function renderGallery(container, images, alt) {
            container.innerHTML = '';
            if (images && images.length > 0) {
                images.slice(0, 3).forEach(url => {
                    container.innerHTML += `<a href="${url}" target="_blank" class="block h-full overflow-hidden rounded-lg hover:opacity-90 transition"><img src="${url}" alt="${alt}" class="w-full h-full object-cover"></a>`;
                });
            } else {
                container.innerHTML = `<div class="col-span-3 text-center text-xs text-gray-400 py-4 bg-gray-50 rounded">Không có hình ảnh</div>`;
            }
        }

        async function updateProvinceUI(data) {
            resetTTS();
            
            currentProvinceName = cleanName(data.Tong_quan_tinh_thanh_pho);
            titleText.textContent = currentProvinceName;
            document.title = `Bản đồ ${currentProvinceName}`;

            const fields = [
                {k:'Vi_tri_dia_ly', l:'Vị trí địa lý'}, {k:'Dieu_kien_tu_nhien', l:'Điều kiện tự nhiên'},
                {k:'Dan_cu_va_xa_hoi', l:'Dân cư & Xã hội'}, {k:'Kinh_te', l:'Kinh tế'},
                {k:'Lich_su_hinh_thanh', l:'Lịch sử'}, {k:'Van_hoa_du_lich', l:'Văn hoá & Du lịch'}, {k:'Thong_tin_truoc_sat_nhap', l:'Thông tin trước sát nhập'}, {k:'Thong_tin_sau_sat_nhap', l:'Thông tin sau sát nhập'}
            ];

            let html = '';
            fields.forEach(f => {
                if(data[f.k]) {
                    html += `
                    <details class="group border-b border-gray-100 last:border-0 pb-2">
                        <summary class="font-bold text-purple-800 text-sm group-hover:text-purple-600 transition">${f.l}</summary>
                        <div class="prose prose-sm mt-2 text-gray-600 pl-3 border-l-2 border-purple-200 text-justify leading-relaxed text-xs lg:text-sm">
                            ${data[f.k].replace(/\n/g, '<br>')}
                        </div>
                    </details>`;
                }
            });
            provinceDetailsContent.innerHTML = html || '<p class="text-gray-400 italic">Đang cập nhật dữ liệu...</p>';

            const imgData = await fetchImages(currentProvinceName);
            renderGallery(provinceGallery, imgData.imageUrls, currentProvinceName);
            
            // Show minimap with province map image
            if (typeof Minimap !== 'undefined') {
                Minimap.show(currentProvinceName);
            }
        }

        async function updateCommuneUI(c) {
            resetTTS();
            
            communePanel.classList.remove('opacity-50', 'grayscale', 'pointer-events-none');
            communeName.textContent = c.tenhc;
            
            const pop = parseInt(c.dansonguoi)||0;
            const area = parseFloat(c.dientichkm2)||0;
            
            communeDetails.innerHTML = `
                <div class="grid grid-cols-2 gap-y-2 gap-x-4 text-xs lg:text-sm">
                    <span class="text-gray-500">Loại hình:</span> <span class="font-medium">${c.loai}</span>
                    <span class="text-gray-500">Diện tích:</span> <span class="font-medium">${area.toLocaleString('vi')} km²</span>
                    <span class="text-gray-500">Dân số:</span> <span class="font-medium">${pop.toLocaleString('vi')} người</span>
                </div>
                <div class="mt-3 pt-3 border-t border-purple-100">
                    <p class="text-xs font-bold text-gray-400 mb-2 uppercase">Hình ảnh khu vực</p>
                    <div id="commune-gallery" class="grid grid-cols-3 gap-2 h-20">
                        <div class="bg-gray-100 rounded animate-pulse col-span-3"></div>
                    </div>
                </div>
            `;

            const imgData = await fetchImages(`${c.tenhc}, ${currentProvinceName}`);
            renderGallery(document.getElementById('commune-gallery'), imgData.imageUrls, c.tenhc);
        }


        // --- 4. MAP INITIALIZATION ---

        async function initMap() {
            try {
                const provRes = await fetch(`${API_URL}?action=get_province_details&id=${currentProvinceId}`);
                if (!provRes.ok) throw new Error("Lỗi API CSDL");
                const provData = await provRes.json();
                
                await updateProvinceUI(provData);

                const safeName = currentProvinceName.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d");
                const potentialFiles = [
                    `./data/${currentProvinceName}.json`,
                    `./data/tỉnh ${currentProvinceName}.json`,
                    `./data/${safeName}.json`,
                    `./data/${safeName.replace(/\s/g,'-')}.json`
                ];

                let geoRes = null;
                for (const f of potentialFiles) {
                    try { const r = await fetch(f); if(r.ok) { geoRes=r; break; } } catch(e){}
                }
                if(!geoRes) throw new Error("Không tìm thấy file bản đồ .json");
                
                const listRes = await fetch('./data/xa.json');
                const listData = await listRes.json();

                let provCode = currentProvinceId; 
                const codeMatch = provData.Tong_quan_tinh_thanh_pho?.match(/Mã tỉnh:\s*(\d+)/);
                if(codeMatch) provCode = codeMatch[1];

                allCommunes = listData.filter(x => x.matinh == provCode);
                
                const tArea = allCommunes.reduce((a,b)=>a+(parseFloat(b.dientichkm2)||0),0);
                const tPop = allCommunes.reduce((a,b)=>a+(parseInt(b.dansonguoi)||0),0);
                provinceStats.innerHTML = `
                    <div class="flex justify-around text-center">
                        <div><div class="font-bold text-purple-700">${allCommunes.length}</div><div class="text-xs">Đơn vị hành chính</div></div>
                        <div class="w-px bg-gray-200"></div>
                        <div><div class="font-bold text-blue-600">${(tArea).toLocaleString('vi')}</div><div class="text-xs">ki-lô-mét-vuông</div></div>
                        <div class="w-px bg-gray-200"></div>
                        <div><div class="font-bold text-green-600">${tPop}</div><div class="text-xs">Dân số</div></div>
                    </div>
                `;

                geojsonData = await geoRes.json();
                
                let minX=Infinity, minY=Infinity, maxX=-Infinity, maxY=-Infinity;
                geojsonData.forEach(f => {
                    const coords = f.geometry.type==='Polygon'? [f.geometry.coordinates] : f.geometry.coordinates;
                    coords.forEach(poly => poly.forEach(ring => ring.forEach(p => {
                        if(p[0]<minX) minX=p[0]; if(p[0]>maxX) maxX=p[0];
                        if(p[1]<minY) minY=p[1]; if(p[1]>maxY) maxY=p[1];
                    })));
                });
                viewBox = {x:minX, y:minY, w:maxX-minX, h:maxY-minY};
                initialViewBox = {x:minX, y:minY, w:maxX-minX, h:maxY-minY}; // Store initial for reset
                
                svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
                svg.setAttribute('viewBox', `${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`);
                svg.classList.add('w-full', 'h-full', 'select-none');

                geojsonData.forEach(feature => {
                    if(!feature.geometry) return;
                    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
                    
                    let d = "";
                    const types = feature.geometry.type==='Polygon' ? [feature.geometry.coordinates] : feature.geometry.coordinates;
                    types.forEach(poly => poly.forEach(ring => {
                        d += `M ${ring.map(p => p.join(',')).join(' L ')} Z `;
                    }));
                    path.setAttribute('d', d);
                    path.setAttribute('vector-effect', 'non-scaling-stroke');
                    
                    const communeData = allCommunes.find(c => c.maxa == feature.ma1);
                    if(communeData) {
                        path.dataset.id = feature.ma1;
                        
                        path.onclick = (e) => {
                            e.stopPropagation();
                            if(activePath) activePath.classList.remove('active');
                            path.classList.add('active');
                            activePath = path;
                            updateCommuneUI(communeData);
                        };

                        path.onmouseenter = (e) => {
                            tooltip.style.display = 'block';
                            tooltip.textContent = communeData.tenhc;
                        };
                    }
                    svg.appendChild(path);
                });

                mapContainer.innerHTML = '';
                mapContainer.appendChild(svg);
                loader.classList.add('hidden');

            } catch (err) {
                console.error(err);
                loader.innerHTML = `<div class="text-red-500 bg-white p-4 rounded shadow">Lỗi: ${err.message}</div>`;
            }
        }

        // --- 5. ZOOM & PAN LOGIC ---
        
        function updateView() { if(svg) svg.setAttribute('viewBox', `${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`); }
        
        // Reset view to initial state (for minimap click)
        function resetView() {
            // Always get latest SVG reference in case it was recreated
            const freshSvg = document.querySelector('#map-container svg');
            if (initialViewBox && freshSvg) {
                svg = freshSvg;
                viewBox = { ...initialViewBox };
                updateView();
            } else {
                console.warn('[ResetView] No SVG or no initial viewBox!');
            }
        }
        
        // Expose resetView globally for minimap to use
        window.mapResetView = resetView;
        
        zoomInBtn.onclick = () => { viewBox.x += viewBox.w*0.1; viewBox.y += viewBox.h*0.1; viewBox.w *= 0.8; viewBox.h *= 0.8; updateView(); };
        zoomOutBtn.onclick = () => { viewBox.x -= viewBox.w*0.1; viewBox.y -= viewBox.h*0.1; viewBox.w *= 1.2; viewBox.h *= 1.2; updateView(); };

        let isDown=false, startX, startY;
        mapContainer.onmousedown = (e) => { isDown=true; startX=e.clientX; startY=e.clientY; mapContainer.style.cursor='grabbing'; };
        window.onmouseup = () => { isDown=false; mapContainer.style.cursor='grab'; };
        window.onmousemove = (e) => {
            if(isDown && svg) {
                e.preventDefault();
                const scaleX = viewBox.w / mapContainer.clientWidth;
                const scaleY = viewBox.h / mapContainer.clientHeight;
                viewBox.x -= (e.clientX - startX) * scaleX;
                viewBox.y -= (e.clientY - startY) * scaleY;
                updateView();
                startX = e.clientX; startY = e.clientY;
            }
            tooltip.style.left = (e.pageX + 15) + 'px';
            tooltip.style.top = (e.pageY + 15) + 'px';
        };
        mapContainer.onmouseleave = () => tooltip.style.display = 'none';

        mapContainer.onwheel = (e) => {
            e.preventDefault();
            const scale = e.deltaY > 0 ? 1.1 : 0.9;
            const mouseX = (e.clientX - mapContainer.getBoundingClientRect().left) / mapContainer.clientWidth;
            const mouseY = (e.clientY - mapContainer.getBoundingClientRect().top) / mapContainer.clientHeight;
            
            const newW = viewBox.w * scale;
            const newH = viewBox.h * scale;
            
            viewBox.x += (viewBox.w - newW) * mouseX;
            viewBox.y += (viewBox.h - newH) * mouseY;
            viewBox.w = newW; viewBox.h = newH;
            updateView();
        };

        // --- 6. SEARCH LOGIC ---
        communeSearch.oninput = (e) => {
            const val = e.target.value.toLowerCase().trim();
            searchResults.innerHTML = '';
            if(val.length < 2) { searchResults.classList.add('hidden'); return; }
            
            const matches = allCommunes.filter(c => c.tenhc.toLowerCase().includes(val)).slice(0, 8);
            if(matches.length > 0) {
                searchResults.classList.remove('hidden');
                matches.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'p-3 hover:bg-purple-50 cursor-pointer border-b last:border-0 text-sm';
                    div.textContent = c.tenhc;
                    div.onclick = () => {
                        const path = svg.querySelector(`path[data-id="${c.maxa}"]`);
                        if(path) path.dispatchEvent(new Event('click')); 
                        communeSearch.value = c.tenhc;
                        searchResults.classList.add('hidden');
                    };
                    searchResults.appendChild(div);
                });
            } else { searchResults.classList.add('hidden'); }
        };

        // Start App
        initMap();
    });