// --- A-Frame カスタムコンポーネント: 近接時のサイズ制御 ---
AFRAME.registerComponent('proximity-listener', {
    schema: {
        shrinkRadius: { type: 'number', default: 3 }
    },
    init: function () {
        this.cameraEl = document.querySelector('#myCamera');
        this.isShrunk = false;
    },
    tick: function () {
        const camPos = new THREE.Vector3();
        const elPos = new THREE.Vector3();
        this.cameraEl.object3D.getWorldPosition(camPos);
        this.el.object3D.getWorldPosition(elPos);

        const dist = camPos.distanceTo(elPos);

        if (dist < this.data.shrinkRadius && !this.isShrunk) {
            this.el.emit('shrink');
            this.isShrunk = true;
        } else if (dist >= this.data.shrinkRadius && this.isShrunk) {
            this.el.emit('grow');
            this.isShrunk = false;
        }
    }
});

window.onload = () => {
    const scene = document.querySelector('a-scene');
    const debugPanel = document.getElementById('debug-panel');
    const fileInput = document.getElementById('fileInput');
    const fileLabel = document.getElementById('fileLabel');
    const startScreen = document.getElementById('start-screen');
    const mainUI = document.getElementById('main-ui');
    const shotBtn = document.getElementById('shotBtn');

    let selectedImgUrl = null;
    let selectedBlob = null;
    let selectedAspect = 1;
    let appStarted = false;
    let currentPos = { lat: 0, lng: 0 };
    let currentUser = null; // { loggedIn, role, username } or null

    // --- 地図関連 ---
    const MAPS_API_KEY = 'YOUR_GOOGLE_MAPS_API_KEY'; // ★ Google Cloud Console で取得したAPIキーを入力
    let gMap = null, gMapReady = false, currentMarker = null;
    let savedPhotos = [];

    // --- 1. 距離計算・方位計算 ---
    function getDistance(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // 北から時計回りの方位角（度）を返す
    function getBearingDeg(lat1, lng1, lat2, lng2) {
        const φ1 = lat1 * Math.PI / 180, φ2 = lat2 * Math.PI / 180;
        const Δλ = (lng2 - lng1) * Math.PI / 180;
        const y = Math.sin(Δλ) * Math.cos(φ2);
        const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }

    // カメラの向き（北から時計回りの度数）
    function getCameraHeadingDeg() {
        const cam = document.querySelector('#myCamera');
        if (!cam?.object3D) return 0;
        const dir = new THREE.Vector3();
        cam.object3D.getWorldDirection(dir);
        return (Math.atan2(dir.x, dir.z) * 180 / Math.PI + 360) % 360;
    }

    // --- 2. GPS監視 ---
    navigator.geolocation.watchPosition(pos => {
        currentPos.lat = pos.coords.latitude;
        currentPos.lng = pos.coords.longitude;
        const acc = Math.round(pos.coords.accuracy);

        const entities = document.querySelectorAll('[gps-entity-place]');

        // 近くの写真をカウント
        let nearbyCount = 0;
        entities.forEach(el => {
            const attr = el.getAttribute('gps-entity-place');
            const dist = getDistance(currentPos.lat, currentPos.lng, parseFloat(attr.latitude), parseFloat(attr.longitude));
            if (dist < 15) nearbyCount++;
        });

        let statusMsg = nearbyCount > 0
            ? `<span style="color: #ffeb3b; font-weight: bold;">📍 近くに ${nearbyCount} 枚！</span>`
            : `<span style="opacity: 0.6;">(近くに写真なし)</span>`;

        debugPanel.innerHTML = `精度: ${acc}m<br>座標: ${currentPos.lat.toFixed(5)}, ${currentPos.lng.toFixed(5)}<br>合計: ${entities.length}枚 / ${statusMsg}`;

        // 地図が開いていれば現在地を更新
        if (document.getElementById('ar-map-overlay').classList.contains('show')) {
            updateMapPosition();
        }
    }, err => {
        console.error(err);
        const msgs = {
            1: '📍 位置情報の使用が拒否されています。\nスマホの設定でブラウザの位置情報を許可してください。',
            2: '📍 位置情報を取得できません。GPS電波を確認してください。',
            3: '📍 位置情報の取得がタイムアウトしました。',
        };
        debugPanel.innerHTML = `<span style="color:#ff6b6b;font-weight:bold;">${msgs[err.code] || '位置情報エラー'}</span>`;
    }, { enableHighAccuracy: true });

    // --- 3. サーバーから保存済み写真を読み込む ---
    async function loadSavedPhotos() {
        try {
            const res = await fetch('api/photos.php', { credentials: 'include' });
            const data = await res.json();
            savedPhotos = data.photos || [];
            savedPhotos.forEach(p => createARPhoto({
                lat:      parseFloat(p.lat),
                lng:      parseFloat(p.lng),
                yOffset:  parseFloat(p.y_offset),
                aspect:   parseFloat(p.aspect),
                image:    p.image_url,
                comment:  p.comment || ''
            }));
        } catch (e) {
            console.error('写真の読み込み失敗:', e);
        }
    }

    // --- 4. 認証チェック ---
    async function checkAuth() {
        try {
            const res  = await fetch('api/auth.php?action=check', { credentials: 'include' });
            currentUser = await res.json();
        } catch (e) {
            currentUser = { loggedIn: false };
        }
        updateUserUI();
    }

    function updateUserUI() {
        // スタート画面のリンクを更新
        const links = document.getElementById('start-user-links');
        if (currentUser && currentUser.loggedIn) {
            links.innerHTML = `
                <span class="user-greeting">👤 ${currentUser.username}</span>
                <a href="user/myphotos.php">マイ写真</a>
                <a href="#" id="start-logout-btn">ログアウト</a>`;
            document.getElementById('start-logout-btn').addEventListener('click', async e => {
                e.preventDefault(); e.stopPropagation();
                await fetch('api/auth.php?action=logout', { method: 'POST', credentials: 'include' });
                currentUser = { loggedIn: false };
                updateUserUI();
            });
        } else {
            links.innerHTML = `
                <a href="user/login.php">ログイン</a>
                <a href="user/register.php">新規登録</a>`;
        }

        // clearBtn のラベルと動作を更新
        const clearBtn = document.getElementById('clearBtn');
        if (currentUser && currentUser.loggedIn) {
            clearBtn.textContent = currentUser.role === 'admin' ? '⚙️ 管理' : '👤 マイ写真';
            clearBtn.style.borderColor = '#4a90e2';
            clearBtn.style.color = '#4a90e2';
        } else {
            clearBtn.textContent = '👤 ログイン';
            clearBtn.style.borderColor = '#ff4444';
            clearBtn.style.color = '#ff4444';
        }
    }

    // --- 5. スタート処理 ---
    startScreen.addEventListener('click', async () => {
        // 位置情報パーミッションの確認
        if (navigator.permissions) {
            try {
                const status = await navigator.permissions.query({ name: 'geolocation' });
                if (status.state === 'denied') {
                    alert('位置情報の使用が拒否されています。\nスマホの設定でブラウザの位置情報を許可してから再度お試しください。');
                    return;
                }
            } catch (e) { /* 未対応ブラウザは無視 */ }
        }
        startScreen.style.display = 'none';
        mainUI.style.display = 'flex';
        appStarted = true;
        document.getElementById('radar').classList.add('show');
        loadSavedPhotos();
        requestAnimationFrame(updateARIndicators);
    });

    checkAuth();

    // --- 6. 写真選択（base64 + Blob を両方保持）---
    fileInput.addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            const img = new Image();
            img.onload = () => {
                const c = document.createElement('canvas');
                const max = 1024;
                let w = img.width, h = img.height;
                if (w > h && w > max) { h *= max / w; w = max; }
                else if (h > max) { w *= max / h; h = max; }
                c.width = w; c.height = h;
                const ctx = c.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                selectedImgUrl = c.toDataURL('image/jpeg', 0.9);
                selectedAspect = w / h;
                // AR即時表示用にBlobも作成
                c.toBlob(blob => { selectedBlob = blob; }, 'image/jpeg', 0.9);
                fileLabel.innerText = "✅ 画面をタップして配置！";
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });

    // --- 6. AR写真の生成 ---
    function createARPhoto(data) {
        const entity = document.createElement('a-entity');
        entity.setAttribute('gps-entity-place', `latitude: ${data.lat}; longitude: ${data.lng};`);

        const plane = document.createElement('a-plane');
        plane.setAttribute('look-at', '#myCamera');

        const yPos = data.yOffset !== undefined ? data.yOffset : 1.0;
        plane.setAttribute('position', `0 ${yPos} 0`);

        const size = 2.5;
        if (data.aspect >= 1) {
            plane.setAttribute('width', size);
            plane.setAttribute('height', size / data.aspect);
        } else {
            plane.setAttribute('height', size);
            plane.setAttribute('width', size * data.aspect);
        }
        plane.setAttribute('material', 'shader: flat; side: double; transparent: true;');

        // 写真データをエンティティに紐付け（レイキャスト用）
        entity.photoData = { comment: data.comment || '', lat: data.lat, lng: data.lng, image: data.image || '' };

        const loader = new THREE.TextureLoader();
        loader.load(data.image, (texture) => {
            const mesh = plane.getObject3D('mesh');
            mesh.material.map = texture;
            mesh.material.needsUpdate = true;
        });

        entity.setAttribute('animation__shrink', { property: 'scale', to: '0.3 0.3 0.3', dur: 300, easing: 'easeOutQuad', startEvents: 'shrink' });
        entity.setAttribute('animation__grow',   { property: 'scale', to: '1 1 1',         dur: 300, easing: 'easeOutQuad', startEvents: 'grow'   });
        entity.setAttribute('proximity-listener', { shrinkRadius: 3 });

        entity.appendChild(plane);
        scene.appendChild(entity);
    }

    // --- レーダー描画 ---
    function updateRadar(headingDeg) {
        const canvas = document.getElementById('radar');
        if (!canvas || !currentPos.lat) return;
        const ctx = canvas.getContext('2d');
        const S = canvas.width, cx = S/2, cy = S/2, R = S/2 - 2;
        const RANGE = 80; // 表示範囲(m)
        ctx.clearRect(0, 0, S, S);

        // 円形クリップ
        ctx.save();
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI*2); ctx.clip();

        // 背景
        ctx.fillStyle = 'rgba(0,0,0,0.72)'; ctx.fillRect(0, 0, S, S);

        // 距離リング
        ctx.strokeStyle = 'rgba(0,255,100,0.2)'; ctx.lineWidth = 1;
        [0.4, 0.75].forEach(f => {
            ctx.beginPath(); ctx.arc(cx, cy, R*f, 0, Math.PI*2); ctx.stroke();
        });

        // 写真ドット（ヘディングアップ）
        savedPhotos.forEach(p => {
            const dist = getDistance(currentPos.lat, currentPos.lng, parseFloat(p.lat), parseFloat(p.lng));
            if (dist > RANGE) return;
            const bearing = getBearingDeg(currentPos.lat, currentPos.lng, parseFloat(p.lat), parseFloat(p.lng));
            const rel = (bearing - headingDeg) * Math.PI / 180;
            const scale = R / RANGE;
            const px = cx + Math.sin(rel) * dist * scale;
            const py = cy - Math.cos(rel) * dist * scale;
            ctx.beginPath(); ctx.arc(px, py, 5, 0, Math.PI*2);
            ctx.fillStyle = '#ff4444'; ctx.fill();
            ctx.strokeStyle = '#fff'; ctx.lineWidth = 1; ctx.stroke();
        });

        ctx.restore();

        // 自分（青い三角・常に上向き＝進行方向）
        ctx.fillStyle = '#4285F4';
        ctx.beginPath();
        ctx.moveTo(cx, cy - 9); ctx.lineTo(cx - 5, cy + 5); ctx.lineTo(cx + 5, cy + 5);
        ctx.closePath(); ctx.fill();
        ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5; ctx.stroke();

        // 外枠
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI*2);
        ctx.strokeStyle = 'rgba(0,255,100,0.55)'; ctx.lineWidth = 1.5; ctx.stroke();
    }

    // --- インジケーター更新ループ ---
    const arrowEls = new Map(); // 空マップ（setArUiVisible互換用）
    function updateARIndicators() {
        if (appStarted && !document.getElementById('ar-map-overlay').classList.contains('show')) {
            updateRadar(getCameraHeadingDeg());
        }
        requestAnimationFrame(updateARIndicators);
    }

    // --- ライトボックス表示 ---
    function showLightbox(photoData) {
        document.getElementById('lightbox-img').src = photoData.image || '';
        document.getElementById('lightbox-comment').textContent =
            photoData.comment ? `💬 ${photoData.comment}` : '';
        document.getElementById('lightbox-coords').textContent =
            `📍 ${parseFloat(photoData.lat).toFixed(5)}, ${parseFloat(photoData.lng).toFixed(5)}`;
        document.getElementById('photo-lightbox').classList.add('show');
    }

    // --- 7. タップで5m先に配置してサーバーへ保存 ---
    const handleTap = async (e) => {
        if (!appStarted || e.target.closest('.ui-container')) return;

        // タップ座標を取得
        const clientX = e.touches ? e.touches[0]?.clientX : e.clientX;
        const clientY = e.touches ? e.touches[0]?.clientY : e.clientY;
        if (clientX === undefined) return;

        // THREE.js レイキャストで AR 写真に当たっているか判定
        const raycaster = new THREE.Raycaster();
        raycaster.setFromCamera(
            new THREE.Vector2(
                (clientX / window.innerWidth)  * 2 - 1,
                -(clientY / window.innerHeight) * 2 + 1
            ),
            scene.camera
        );
        const meshes = [];
        scene.object3D.traverse(obj => { if (obj.isMesh) meshes.push(obj); });
        const hits = raycaster.intersectObjects(meshes, false);

        if (hits.length > 0) {
            // ヒットしたメッシュから親エンティティの photoData を探す
            let obj = hits[0].object;
            while (obj) {
                if (obj.el?.photoData) { showLightbox(obj.el.photoData); return; }
                if (obj.el?.parentEl?.photoData) { showLightbox(obj.el.parentEl.photoData); return; }
                obj = obj.parent;
            }
        }

        // フォールバック: タップ方向に最も近い写真を角度で検出（遠くの小さい写真に対応）
        const tapDir = raycaster.ray.direction.clone().normalize();
        const camPos = new THREE.Vector3();
        scene.camera.getWorldPosition(camPos);
        let bestAngle = 25; // 判定する最大角度（度）
        let bestEntity = null;
        document.querySelectorAll('[gps-entity-place]').forEach(el => {
            if (!el.photoData) return;
            const elPos = new THREE.Vector3();
            el.object3D.getWorldPosition(elPos);
            const toEl = elPos.clone().sub(camPos).normalize();
            const angle = Math.acos(Math.min(1, tapDir.dot(toEl))) * 180 / Math.PI;
            if (angle < bestAngle) { bestAngle = angle; bestEntity = el; }
        });
        if (bestEntity) { showLightbox(bestEntity.photoData); return; }

        // 写真が選択されていなければ何もしない
        if (!selectedImgUrl || !selectedBlob) return;

        // GPS未取得チェック
        if (Math.abs(currentPos.lat) < 0.01 && Math.abs(currentPos.lng) < 0.01) {
            alert('GPS位置情報を取得中です。しばらく待ってから再度お試しください。');
            return;
        }

        const camera = document.querySelector('#myCamera').object3D;
        const worldDir = new THREE.Vector3();
        camera.getWorldDirection(worldDir);
        const angle = Math.atan2(worldDir.x, worldDir.z);

        const distance = 5;
        let targetLat = currentPos.lat + (distance * Math.cos(angle)) / 111320;
        let targetLng = currentPos.lng + (distance * Math.sin(angle)) / (111320 * Math.cos(currentPos.lat * Math.PI / 180));

        const entities = document.querySelectorAll('[gps-entity-place]');
        const photoCount = entities.length;
        const yOffset = 1.0 + ((photoCount % 3) * 0.5);

        entities.forEach(el => {
            const attr = el.getAttribute('gps-entity-place');
            const distBetween = getDistance(targetLat, targetLng, parseFloat(attr.latitude), parseFloat(attr.longitude));
            if (distBetween < 0.6) {
                targetLat += (0.4 / 111320);
                targetLng += (0.4 / (111320 * Math.cos(targetLat * Math.PI / 180)));
            }
        });

        // ボタン状態リセット（先にキャプチャ）
        const snapUrl    = selectedImgUrl;
        const snapBlob   = selectedBlob;
        const snapAspect = selectedAspect;
        selectedImgUrl = null;
        selectedBlob   = null;
        fileLabel.innerText = "① 写真を選ぶ";

        // AR上に即時表示（base64で先に表示してUX向上）
        createARPhoto({ lat: targetLat, lng: targetLng, yOffset, aspect: snapAspect, image: snapUrl });

        // サーバーへ保存（FormData でファイル送信）
        try {
            const formData = new FormData();
            formData.append('lat',      targetLat);
            formData.append('lng',      targetLng);
            formData.append('y_offset', yOffset);
            formData.append('aspect',   snapAspect);
            formData.append('image',    new File([snapBlob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' }));

            await fetch('api/photos.php', { method: 'POST', body: formData });
        } catch (err) {
            console.error('サーバー保存失敗:', err);
        }
    };

    window.addEventListener('touchstart', handleTap);
    window.addEventListener('mousedown', handleTap);

    // --- 8. 高精度スクリーンショット保存 ---
    shotBtn.addEventListener('click', async () => {
        try {
            const video = document.querySelector('video');
            const glCanvas = scene.canvas;
            if (!video || !glCanvas) return;
            const canvas = document.createElement('canvas');
            canvas.width = window.innerWidth; canvas.height = window.innerHeight;
            const ctx = canvas.getContext('2d');
            const vAspect = video.videoWidth / video.videoHeight;
            const sAspect = canvas.width / canvas.height;
            let sx, sy, sw, sh;
            if (vAspect > sAspect) { sw = video.videoHeight * sAspect; sh = video.videoHeight; sx = (video.videoWidth - sw) / 2; sy = 0; }
            else { sw = video.videoWidth; sh = video.videoWidth / sAspect; sx = 0; sy = (video.videoHeight - sh) / 2; }
            ctx.drawImage(video, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
            scene.renderer.render(scene.object3D, scene.camera);
            const cw = glCanvas.width, ch = glCanvas.height, cAspect = cw / ch;
            let asx, asy, asw, ash;
            if (cAspect > sAspect) { asw = ch * sAspect; ash = ch; asx = (cw - asw) / 2; asy = 0; }
            else { asw = cw; ash = cw / sAspect; asx = 0; asy = (ch - ash) / 2; }
            ctx.drawImage(glCanvas, asx, asy, asw, ash, 0, 0, canvas.width, canvas.height);
            flashEffect();
            const url = canvas.toDataURL('image/jpeg', 0.8);
            saveOrShare(url);
        } catch (e) { console.error(e); }
    });

    function flashEffect() {
        const f = document.createElement('div');
        f.style.cssText = 'position:fixed;inset:0;background:white;z-index:99999;pointer-events:none;';
        document.body.appendChild(f);
        setTimeout(() => { f.style.transition = 'opacity .4s'; f.style.opacity = 0; setTimeout(() => f.remove(), 400); }, 50);
    }

    async function saveOrShare(url) {
        const blob = await (await fetch(url)).blob();
        const file = new File([blob], `ar-${Date.now()}.jpg`, { type: 'image/jpeg' });
        if (navigator.share) { try { await navigator.share({ files: [file] }); } catch (e) {} }
        else { const a = document.createElement('a'); a.href = url; a.download = file.name; a.click(); }
    }

    // ライトボックスを閉じる
    document.getElementById('lightbox-close').onclick = () => {
        document.getElementById('photo-lightbox').classList.remove('show');
    };

    // --- 地図ボタン ---
    function setArUiVisible(visible) {
        document.getElementById('debug-panel').style.display = visible ? '' : 'none';
        document.getElementById('main-ui').style.display     = visible ? 'flex' : 'none';
        document.getElementById('radar').classList.toggle('show', visible);
        arrowEls.forEach(el => { el.style.display = visible ? '' : 'none'; });
    }

    document.getElementById('mapBtn').onclick = () => {
        document.getElementById('ar-map-overlay').classList.add('show');
        setArUiVisible(false);
        if (!gMapReady) {
            gMapReady = true;
            const s = document.createElement('script');
            s.src = `https://maps.googleapis.com/maps/api/js?key=${MAPS_API_KEY}&callback=initARMap`;
            s.async = true;
            document.head.appendChild(s);
        } else if (gMap) {
            updateMapPosition();
        }
    };

    document.getElementById('ar-map-close').onclick = () => {
        document.getElementById('ar-map-overlay').classList.remove('show');
        setArUiVisible(true);
    };

    window.initARMap = () => {
        const center = currentPos.lat ? { lat: currentPos.lat, lng: currentPos.lng } : { lat: 35.6812, lng: 139.7671 };
        gMap = new google.maps.Map(document.getElementById('ar-map'), {
            center, zoom: 17, mapTypeId: 'roadmap',
            fullscreenControl: false, streetViewControl: false, mapTypeControl: false
        });

        // 現在地マーカー（青）
        currentMarker = new google.maps.Marker({
            position: center, map: gMap, title: '現在地',
            icon: { path: google.maps.SymbolPath.CIRCLE, scale: 10,
                    fillColor: '#4285F4', fillOpacity: 1,
                    strokeColor: '#fff', strokeWeight: 3 }
        });

        // 写真マーカー（赤）
        const infoWindow = new google.maps.InfoWindow();
        savedPhotos.forEach(p => {
            const marker = new google.maps.Marker({
                position: { lat: parseFloat(p.lat), lng: parseFloat(p.lng) },
                map: gMap, title: p.comment || '写真',
                icon: { url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                        scaledSize: new google.maps.Size(32, 32) }
            });
            marker.addListener('click', () => {
                infoWindow.setContent(`
                    <div style="max-width:180px">
                        <img src="${p.image_url}" style="width:100%;border-radius:8px;margin-bottom:6px">
                        <div style="font-size:13px;color:#333">${p.comment ? '💬 ' + p.comment : ''}</div>
                        <div style="font-size:11px;color:#888;margin-top:4px">
                            📍 ${parseFloat(p.lat).toFixed(5)}, ${parseFloat(p.lng).toFixed(5)}
                        </div>
                    </div>
                `);
                infoWindow.open(gMap, marker);
            });
        });
    };

    function updateMapPosition() {
        if (!gMap || !currentPos.lat) return;
        const pos = { lat: currentPos.lat, lng: currentPos.lng };
        gMap.setCenter(pos);
        if (currentMarker) currentMarker.setPosition(pos);
    }

    // ユーザーボタン → ログイン状態に応じて遷移
    document.getElementById('clearBtn').onclick = () => {
        if (currentUser && currentUser.loggedIn) {
            location.href = currentUser.role === 'admin' ? 'admin/index.php' : 'user/myphotos.php';
        } else {
            location.href = 'user/login.php';
        }
    };
};
