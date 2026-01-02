document.addEventListener('DOMContentLoaded', async () => {
    // --- GLOBAL STATE ---
    let state = {
        map: null,
        user: null,
        devices: [],
        activeDeviceId: null,
        currentPolyline: null,
        currentMarker: null,
        geofencesLayer: null,
        drawControl: null,
        autoRefreshInterval: null,
    };

    // --- DOM ELEMENTS ---
    const appView = document.getElementById('app-view');
    const userEmailEl = document.getElementById('user-email');
    const deviceListEl = document.getElementById('device-list');
    const mapControlsEl = document.getElementById('map-controls');
    const dateFilterEl = document.getElementById('date-filter');
    const modalOverlay = document.getElementById('modal-overlay');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const manageAlertsBtn = document.getElementById('manage-alerts-btn');

    // --- CORE FUNCTIONS ---
    const checkLoginStatus = async () => {
        try {
            const response = await fetch('api/auth_session_check.php');
            const result = await response.json();
            if (!result.loggedIn) {
                window.location.href = 'login.html';
                return false;
            }
            state.user = result.user;
            userEmailEl.textContent = state.user.email;
            appView.classList.remove('hidden');
            return true;
        } catch (e) {
            window.location.href = 'login.html';
            return false;
        }
    };

    const initializeMap = () => {
        state.map = L.map('map').setView([21.0285, 105.8541], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(state.map);
        state.geofencesLayer = new L.FeatureGroup().addTo(state.map);
        state.map.on(L.Draw.Event.CREATED, handleDrawingCreated);
    };

    const loadDevices = async () => {
        try {
            const response = await fetch('api/api_devices.php');
            state.devices = await response.json();
            renderDeviceList();
        } catch (error) {
            console.error('Failed to load devices:', error);
        }
    };

    const renderDeviceList = () => {
        deviceListEl.innerHTML = '';
        if (state.devices.length === 0) {
            deviceListEl.innerHTML = `<li class="no-devices">Chưa có thiết bị nào.</li>`;
            return;
        }
        state.devices.forEach(device => {
            const li = document.createElement('li');
            li.innerHTML = `<span>${device.device_disp}</span><small>${device.device_id}</small>`;
            li.dataset.deviceId = device.device_id;
            if (device.device_id === state.activeDeviceId) {
                li.classList.add('active');
            }
            deviceListEl.appendChild(li);
        });
    };

    const selectDevice = (deviceId) => {
        if (state.activeDeviceId === deviceId) return;
        state.activeDeviceId = deviceId;
        renderDeviceList();
        fetchAndDrawPath();
    };

    const fetchAndDrawPath = async () => {
        clearInterval(state.autoRefreshInterval);
        state.autoRefreshInterval = null;
        if (state.currentPolyline) state.map.removeLayer(state.currentPolyline);
        if (state.currentMarker) state.map.removeLayer(state.currentMarker);
        state.currentPolyline = null;
        state.currentMarker = null;

        if (!state.activeDeviceId) {
            mapControlsEl.classList.add('hidden');
            return;
        }
        
        mapControlsEl.classList.remove('hidden');
        const dateRange = dateFilterEl.value;

        try {
            const response = await fetch(`api/api_get_path.php?device_id=${state.activeDeviceId}&range=${dateRange}`);
            const pathData = await response.json();
            
            if (pathData.length > 0) {
                const latLngs = pathData.map(p => [p.lat, p.lng]);
                state.currentPolyline = L.polyline(latLngs, { color: 'blue' }).addTo(state.map);
                
                const lastPoint = pathData[pathData.length - 1];
                const popupContent = `<b>Vị trí cuối</b><br>Thời gian: ${lastPoint.ts}<br>Tốc độ: ${lastPoint.speed} km/h`;
                state.currentMarker = L.marker([lastPoint.lat, lastPoint.lng]).addTo(state.map).bindPopup(popupContent).openPopup();

                state.map.fitBounds(state.currentPolyline.getBounds());
            }

            if (dateRange === 'today') {
                state.autoRefreshInterval = setInterval(updateLatestPath, 15000);
            }
        } catch (error) {
            console.error('Failed to draw path:', error);
        }
    };
    
    const updateLatestPath = async () => {
        if (!state.activeDeviceId || dateFilterEl.value !== 'today') {
            clearInterval(state.autoRefreshInterval);
            return;
        }
        try {
            const response = await fetch(`api/api_get_path.php?device_id=${state.activeDeviceId}&range=today&limit=50`);
            const pathData = await response.json();
            if (pathData.length > 0) {
                const latLngs = pathData.map(p => [p.lat, p.lng]);
                const lastPoint = pathData[pathData.length - 1];
                if (state.currentPolyline) {
                    state.currentPolyline.setLatLngs(latLngs);
                }
                if (state.currentMarker) {
                    state.currentMarker.setLatLng([lastPoint.lat, lastPoint.lng]);
                    state.currentMarker.getPopup().setContent(`<b>Vị trí cuối</b><br>Thời gian: ${lastPoint.ts}<br>Tốc độ: ${lastPoint.speed} km/h`);
                }
            }
        } catch(e) { console.error("Auto-refresh failed", e)}
    };

    // --- MODAL & UI LOGIC ---
    const showModal = (title, content) => {
        modalTitle.innerHTML = title;
        modalBody.innerHTML = content;
        modalOverlay.classList.remove('hidden');
    };

    const hideModal = () => {
        modalOverlay.classList.add('hidden');
        modalTitle.innerHTML = '';
        modalBody.innerHTML = '';
        if (state.drawControl) {
            state.map.removeControl(state.drawControl);
            state.drawControl = null;
        }
    };

    // --- DEVICE MANAGEMENT MODAL ---
    const openAddDeviceModal = () => {
        const content = `
            <form id="deviceForm">
                <div class="form-group">
                    <label for="deviceDisp">Tên hiển thị (ví dụ: Xe của Bố)</label>
                    <input type="text" id="deviceDisp" required>
                </div>
                <div class="form-group">
                    <label for="deviceId">Device ID (Số SIM, IMEI...)</label>
                    <input type="text" id="deviceId" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Lưu thiết bị</button>
                </div>
            </form>
        `;
        showModal('Thêm thiết bị mới', content);

        document.getElementById('deviceForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const device_disp = document.getElementById('deviceDisp').value;
            const device_id = document.getElementById('deviceId').value;
            try {
                const response = await fetch('api/api_devices.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({device_disp, device_id})
                });
                const result = await response.json();
                if (response.ok) {
                    alert(`Thêm thành công!\nAPI Key cho thiết bị này là:\n${result.api_key}\n\nVui lòng lưu lại và nạp vào thiết bị. Khóa này sẽ không được hiển thị lại.`);
                    hideModal();
                    await loadDevices();
                } else {
                    alert(`Lỗi: ${result.message}`);
                }
            } catch(e) { console.error(e); }
        });
    };

    // --- ALERT & RULE MANAGEMENT MODAL ---
    const openAlertsModal = async () => {
        if (!state.activeDeviceId) return;

        const device = state.devices.find(d => d.device_id === state.activeDeviceId);
        const title = `Quản lý cảnh báo cho: ${device.device_disp}`;

        // Fetch existing geofences and rules
        const [geofences, rules] = await Promise.all([
            fetch(`api/api_geofences.php?device_id=${state.activeDeviceId}`).then(res => res.json()),
            fetch(`api/api_rules.php?device_id=${state.activeDeviceId}`).then(res => res.json())
        ]);
        
        const geofencesHtml = geofences.map(g => `
            <li data-id="${g.id}">${g.name} <button class="btn-delete-geofence">&times;</button></li>
        `).join('');

        const rulesHtml = rules.map(r => `
            <li data-id="${r.id}">${r.rule_name} <button class="btn-delete-rule">&times;</button></li>
        `).join('');
        
        const content = `
            <div class="modal-section">
                <h4><i class="fas fa-draw-polygon"></i> Vùng an toàn (Geofences)</h4>
                <ul id="geofence-list">${geofencesHtml}</ul>
                <button id="add-geofence-btn" class="btn-secondary">Vẽ vùng mới trên bản đồ</button>
            </div>
            <hr>
            <div class="modal-section">
                <h4><i class="fas fa-exclamation-triangle"></i> Luật cảnh báo</h4>
                <ul id="rule-list">${rulesHtml}</ul>
                <button id="add-rule-btn" class="btn-secondary">Thêm luật mới</button>
            </div>
        `;
        showModal(title, content);

        // Add event listeners for the new buttons
        document.getElementById('add-geofence-btn').addEventListener('click', () => startDrawingGeofence(geofences, rules));
        document.getElementById('add-rule-btn').addEventListener('click', () => openAddRuleForm(geofences));

        // Event listeners for deleting
        document.getElementById('geofence-list').addEventListener('click', handleDeleteGeofence);
        document.getElementById('rule-list').addEventListener('click', handleDeleteRule);
    };

    const startDrawingGeofence = (geofences, rules) => {
        hideModal(); // Hide modal to see the map
        if (state.drawControl) state.map.removeControl(state.drawControl);

        state.drawControl = new L.Control.Draw({
            draw: {
                polygon: true,
                polyline: false, marker: false, circle: false, circlemarker: false, rectangle: false
            },
            edit: { featureGroup: state.geofencesLayer }
        });
        state.map.addControl(state.drawControl);
    };

    const handleDrawingCreated = async (e) => {
        const layer = e.layer;
        const latLngs = layer.getLatLngs()[0].map(p => [p.lat, p.lng]); // Extract coordinates
        const name = prompt("Đặt tên cho vùng mới (ví dụ: Trường học, Nhà):");

        if (name) {
            try {
                const response = await fetch('api/api_geofences.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        device_id: state.activeDeviceId,
                        name: name,
                        area_data: latLngs
                    })
                });
                if (response.ok) {
                    alert('Đã tạo vùng thành công!');
                } else {
                    alert('Lỗi khi tạo vùng.');
                }
            } catch(e) { console.error(e); }
        }
        openAlertsModal(); // Re-open the modal to show the new list
    };
    
    const openAddRuleForm = (geofences) => {
        const geofenceOptions = geofences.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
        const daysOfWeek = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
        const daysOfWeekHtml = daysOfWeek.map((day, index) => `
            <label><input type="checkbox" name="days" value="${index === 0 ? 7 : index}">${day}</label>
        `).join('');

        const content = `
            <form id="ruleForm">
                <div class="form-group">
                    <label for="ruleName">Tên luật (ví dụ: Giám sát giờ học)</label>
                    <input type="text" id="ruleName" required>
                </div>
                <div class="form-group">
                    <label for="ruleType">Nếu thiết bị...</label>
                    <select id="ruleType" required>
                        <option value="geofence_exit">...Rời khỏi vùng</option>
                        <option value="no_movement">...Không di chuyển</option>
                    </select>
                </div>
                <div id="rule-options"></div> <!-- Dynamic options here -->
                <div class="form-group">
                    <label>...vào các ngày:</label>
                    <div class="days-selector">${daysOfWeekHtml}</div>
                </div>
                <div class="form-group time-range">
                    <label>...trong khoảng thời gian:</label>
                    <input type="time" id="timeStart" required>
                    <span>đến</span>
                    <input type="time" id="timeEnd" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Lưu Luật</button>
                </div>
            </form>
        `;
        showModal('Tạo luật cảnh báo mới', content);

        const ruleTypeSelect = document.getElementById('ruleType');
        const ruleOptionsDiv = document.getElementById('rule-options');

        const updateRuleOptions = () => {
            const type = ruleTypeSelect.value;
            if (type === 'geofence_exit') {
                ruleOptionsDiv.innerHTML = `
                    <div class="form-group">
                        <label for="geofenceId">...vùng được chọn là:</label>
                        <select id="geofenceId">${geofenceOptions}</select>
                    </div>`;
            } else if (type === 'no_movement') {
                ruleOptionsDiv.innerHTML = `
                    <div class="form-group">
                        <label for="maxSpeed">...với vận tốc dưới (km/h):</label>
                        <input type="number" id="maxSpeed" value="1" step="0.1">
                    </div>`;
            }
        };
        
        updateRuleOptions(); // Initial call
        ruleTypeSelect.addEventListener('change', updateRuleOptions);
        document.getElementById('ruleForm').addEventListener('submit', handleSaveRule);
    };

    const handleSaveRule = async (e) => {
        e.preventDefault();
        const selectedDays = Array.from(document.querySelectorAll('input[name="days"]:checked')).map(cb => cb.value);

        const ruleData = {
            device_id: state.activeDeviceId,
            rule_name: document.getElementById('ruleName').value,
            rule_type: document.getElementById('ruleType').value,
            time_start: document.getElementById('timeStart').value,
            time_end: document.getElementById('timeEnd').value,
            days_of_week: selectedDays,
        };

        if (ruleData.rule_type === 'geofence_exit') {
            ruleData.geofence_id = document.getElementById('geofenceId').value;
        } else if (ruleData.rule_type === 'no_movement') {
            ruleData.max_speed = document.getElementById('maxSpeed').value;
            ruleData.min_speed = 0;
        }

        try {
            const response = await fetch('api/api_rules.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(ruleData)
            });
            if (response.ok) {
                alert('Đã lưu luật thành công!');
                openAlertsModal(); // Refresh modal
            } else {
                alert('Lỗi khi lưu luật.');
            }
        } catch(e) { console.error(e); }
    };
    
    const handleDeleteGeofence = async (e) => {
        if (!e.target.classList.contains('btn-delete-geofence')) return;
        const geofenceId = e.target.closest('li').dataset.id;
        if (confirm('Bạn có chắc muốn xóa vùng này? Các luật liên quan cũng sẽ bị xóa.')) {
            try {
                await fetch('api/api_geofences.php', {
                    method: 'DELETE',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: geofenceId, device_id: state.activeDeviceId })
                });
                openAlertsModal();
            } catch(e) { console.error(e); }
        }
    };

    const handleDeleteRule = async (e) => {
        if (!e.target.classList.contains('btn-delete-rule')) return;
        const ruleId = e.target.closest('li').dataset.id;
        if (confirm('Bạn có chắc muốn xóa luật này?')) {
            try {
                await fetch('api/api_rules.php', {
                    method: 'DELETE',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: ruleId })
                });
                openAlertsModal();
            } catch(e) { console.error(e); }
        }
    };


    // --- EVENT LISTENERS ---
    document.getElementById('logout-btn').addEventListener('click', async () => {
        await fetch('api/auth_logout.php');
        window.location.href = 'login.html';
    });
    deviceListEl.addEventListener('click', (e) => {
        const li = e.target.closest('li');
        if (li && li.dataset.deviceId) {
            selectDevice(li.dataset.deviceId);
        }
    });
    dateFilterEl.addEventListener('change', fetchAndDrawPath);
    document.getElementById('add-device-btn').addEventListener('click', openAddDeviceModal);
    manageAlertsBtn.addEventListener('click', openAlertsModal);
    modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) hideModal();
    });
    document.querySelector('.modal-close-btn').addEventListener('click', hideModal);
    document.getElementById('toggle-sidebar-btn').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
    });
    

    // --- INITIALIZATION ---
    const startApp = async () => {
        const loggedIn = await checkLoginStatus();
        if (loggedIn) {
            initializeMap();
            await loadDevices();
            if (state.devices.length > 0) {
                selectDevice(state.devices[0].device_id);
            }
        }
    };

    startApp();
});