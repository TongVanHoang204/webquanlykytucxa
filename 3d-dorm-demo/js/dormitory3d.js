// Dormitory 3D Interactive Edition
// - 5 floors (A1..A5), each 6 rooms (A101..A106)
// - Exterior labels via CSS2DRenderer
// - Hover tooltip & side panel details
// - Fly-into-room animation (GSAP)

const container = document.getElementById('canvas-container');
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0b0c0f);

const camera = new THREE.PerspectiveCamera(60, container.clientWidth / container.clientHeight, 0.1, 1000);
camera.position.set(12, 10, 18);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.outputEncoding = THREE.sRGBEncoding;
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.1;
container.appendChild(renderer.domElement);

// CSS2DRenderer cho nhãn 3D
const labelRenderer = new THREE.CSS2DRenderer();
labelRenderer.setSize(container.clientWidth, container.clientHeight);
labelRenderer.domElement.style.position = 'absolute';
labelRenderer.domElement.style.top = '0';
container.appendChild(labelRenderer.domElement);

// OrbitControls
const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.target.set(0, 4, 0);

// Ánh sáng
const hemi = new THREE.HemisphereLight(0xe8f0ff, 0x101218, 0.8);
scene.add(hemi);
const dir = new THREE.DirectionalLight(0xffffff, 1.2);
dir.position.set(10, 18, 8);
scene.add(dir);

// Nền gradient
const gradTex = new THREE.CanvasTexture((function() {
    const c = document.createElement('canvas');
    c.width = 2;
    c.height = 256;
    const g = c.getContext('2d');
    const grd = g.createLinearGradient(0, 0, 0, 256);
    grd.addColorStop(0, '#202531');
    grd.addColorStop(1, '#0b0c10');
    g.fillStyle = grd;
    g.fillRect(0, 0, 2, 256);
    return c;
})());
scene.background = gradTex;

// Materials helpers
function metal(col = 0xffffff, rough = 0.25, metalness = 0.8) { return new THREE.MeshStandardMaterial({ color: col, roughness: rough, metalness: metalness }); }

function matte(col = 0xffffff, rough = 0.9) { return new THREE.MeshStandardMaterial({ color: col, roughness: rough, metalness: 0 }); }

function glass(col = 0xffffff) {
    return new THREE.MeshPhysicalMaterial({
        color: col,
        metalness: 0,
        roughness: 0.08,
        transmission: 0.9,
        thickness: 0.1,
        transparent: true,
        opacity: 0.95,
        reflectivity: 0.5,
        ior: 1.45
    });
}

function emissive(col = 0x99ccff, intensity = 0.7) { return new THREE.MeshStandardMaterial({ color: 0x111111, emissive: col, emissiveIntensity: intensity }); }

// Root / Groups
const root = new THREE.Group();
scene.add(root);
const building = new THREE.Group();
building.name = "building";
const room = new THREE.Group();
room.name = "room";

// ------- Building -------
const base = new THREE.Mesh(new THREE.BoxGeometry(30, 1, 20), matte(0x14161a, .8));
base.position.y = -0.5;
building.add(base);

const towerW = 12,
    towerH = 12,
    towerD = 8;
const tower = new THREE.Mesh(new THREE.BoxGeometry(towerW, towerH, towerD), matte(0xf4f6fb, .85));
tower.position.set(0, towerH / 2, 0);
building.add(tower);

const glassPanel = new THREE.Mesh(new THREE.BoxGeometry(towerW + 0.2, towerH + 0.2, 0.2), glass(0xcfe8ff));
glassPanel.position.set(0, towerH / 2, towerD / 2 + 0.1);
building.add(glassPanel);

// cửa sổ decor
const winGeo = new THREE.PlaneGeometry(0.6, 0.35);
const winMat = emissive(0xbfd9ff, 0.85);
const windows = new THREE.InstancedMesh(winGeo, winMat, 12 * 7);
let idx = 0;
for (let r = 0; r < 7; r++) {
    for (let c = 0; c < 12; c++) {
        const m = new THREE.Matrix4();
        const x = -5.5 + c * 1.0;
        const y = 1 + r * 1.6;
        const z = towerD / 2 + 0.11;
        m.setPosition(x, y, z);
        windows.setMatrixAt(idx++, m);
    }
}
building.add(windows);

// cánh & sảnh
const wingL = new THREE.Mesh(new THREE.BoxGeometry(4, 8, 6), matte(0xf1f3f8, .85));
wingL.position.set(-8.5, 4, 0);
const wingR = wingL.clone();
wingR.position.x = 8.5;
building.add(wingL, wingR);
const entrance = new THREE.Mesh(new THREE.BoxGeometry(4, 3, 1.4), metal(0xe9edf5, .3, .1));
entrance.position.set(0, 1.5, towerD / 2 + 0.7);
building.add(entrance);

// ------- Room interior -------
const roomW = 9,
    roomH = 3.2,
    roomD = 6;
const floorMesh = new THREE.Mesh(new THREE.PlaneGeometry(roomW, roomD), matte(0xf7f7fb, .95));
floorMesh.rotation.x = -Math.PI / 2;
room.add(floorMesh);
const wallMat = matte(0xffffff, .9);

function wall(w, h, th) { return new THREE.Mesh(new THREE.BoxGeometry(w, h, th), wallMat); }
const back = wall(roomW, roomH, 0.15);
back.position.set(0, roomH / 2, -roomD / 2);
room.add(back);
const left = wall(0.15, roomH, roomD);
left.position.set(-roomW / 2, roomH / 2, 0);
room.add(left);
const right = wall(0.15, roomH, roomD);
right.position.set(roomW / 2, roomH / 2, 0);
room.add(right);
const windowFrame = new THREE.Mesh(new THREE.BoxGeometry(5.6, 2.2, 0.12), metal(0xe0e6ef, .4, .2));
windowFrame.position.set(0, 1.8, -roomD / 2 + 0.08);
const glassWin = new THREE.Mesh(new THREE.PlaneGeometry(5.4, 2.0), glass(0xdff2ff));
glassWin.position.set(0, 1.8, -roomD / 2 + 0.12);
room.add(windowFrame, glassWin);
const sunStripe = new THREE.Mesh(new THREE.PlaneGeometry(2.5, 0.5), emissive(0xffffff, 0.4));
sunStripe.rotation.x = -Math.PI / 2;
sunStripe.position.set(0.5, 0.01, -0.5);
room.add(sunStripe);

// giường tầng + bàn + tủ
function metalMat() { return metal(0xf1f4f8, .35, .2); }

function woodMat() { return matte(0xf5efe6, .8); }

function makeBunk(x, z) {
    const g = new THREE.Group();
    const metalFrame = metalMat();
    const wood = woodMat();
    const postGeo = new THREE.CylinderGeometry(0.06, 0.06, 2.0, 12);
    const p1 = new THREE.Mesh(postGeo, metalFrame);
    p1.position.set(-0.9, 1.0, -0.45);
    const p2 = p1.clone();
    p2.position.x = 0.9;
    const p3 = p1.clone();
    p3.position.z = 0.45;
    const p4 = p2.clone();
    p4.position.z = 0.45;
    const railGeo = new THREE.BoxGeometry(1.8, 0.05, 0.05);

    function rail(y, z) { const r = new THREE.Mesh(railGeo, metalFrame);
        r.position.set(0, y, z); return r; }
    const r1 = rail(0.55, -0.45),
        r2 = rail(0.55, 0.45),
        r3 = rail(1.55, -0.45),
        r4 = rail(1.55, 0.45);
    const matGeo = new THREE.BoxGeometry(1.9, 0.12, 0.9);
    const mattress = new THREE.Mesh(matGeo, matte(0xffffff, .7));
    const mattress2 = mattress.clone();
    mattress.position.y = 0.62;
    mattress2.position.y = 1.62;
    const slatGeo = new THREE.BoxGeometry(1.8, 0.03, 0.8);
    const slat1 = new THREE.Mesh(slatGeo, wood);
    slat1.position.y = 0.56;
    const slat2 = slat1.clone();
    slat2.position.y = 1.56;
    const ladder = new THREE.Group();
    const side = new THREE.Mesh(new THREE.BoxGeometry(0.04, 1.2, 0.04), metalFrame);
    const side2 = side.clone();
    side.position.set(-0.95, 1.0, 0.4);
    side2.position.set(-0.85, 1.0, 0.4);
    ladder.add(side, side2);
    for (let i = 0; i < 5; i++) { const step = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.02, 0.4), metalFrame);
        step.position.set(-0.90, 0.6 + i * 0.15, 0.4);
        ladder.add(step); }
    g.add(p1, p2, p3, p4, r1, r2, r3, r4, slat1, slat2, mattress, mattress2, ladder);
    g.position.set(x, 0, z);
    return g;
}
room.add(makeBunk(-2.3, 0), makeBunk(2.3, 0));
const desk = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.06, 0.6), matte(0xf7f7fb, .9));
desk.position.set(0, 0.8, 1.6);
const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.8, 10), metal());
leg.position.set(-0.75, 0.4, 1.3);
const leg2 = leg.clone();
leg2.position.x = 0.75;
const leg3 = leg.clone();
leg3.position.z = 1.9;
const leg4 = leg2.clone();
leg4.position.z = 1.9;
room.add(desk, leg, leg2, leg3, leg4);
const wardrobe = new THREE.Mesh(new THREE.BoxGeometry(1.2, 2.2, 0.55), matte(0xf0f2f7, .85));
wardrobe.position.set(-3.6, 1.1, 1.6);
room.add(wardrobe);

// nền
const ground = new THREE.Mesh(new THREE.CircleGeometry(40, 64), matte(0x0e1014, .95));
ground.rotation.x = -Math.PI / 2;
ground.position.y = -0.001;
scene.add(ground);

// ---------- Data (mẫu) ----------
const roomData = {};
const floors = ["A1", "A2", "A3", "A4", "A5"];
const statusList = ["available", "occupied", "maintenance"];

function rand(a, b) { return Math.floor(Math.random() * (b - a + 1)) + a; }
floors.forEach((f, fi) => {
    for (let col = 1; col <= 6; col++) {
        const id = `${f}0${col}`;
        const st = statusList[(fi + col) % statusList.length];
        roomData[id] = {
            id,
            capacity: 4,
            current: st === "available" ? rand(0, 2) : st === "occupied" ? rand(3, 4) : 0,
            status: st,
            notes: st === "available" ? "Phòng trống một phần" : st === "occupied" ? "Đủ chỗ / đang sử dụng" : "Đang bảo trì định kỳ"
        };
    }
});

function statusChip(st) {
    const cls = st === "available" ? "st-available" : st === "occupied" ? "st-occupied" : "st-maint";
    const text = st === "available" ? "Còn trống" : st === "occupied" ? "Đang sử dụng" : "Bảo trì";
    return `<span class="status ${cls}">${text}</span>`;
}

// Fade helper
function addFade(obj, to = 1, dur = 1, delay = 0) {
    const mats = [];
    if (obj.traverse) obj.traverse(n => { if (n.material) mats.push(n.material); });
    else if (obj.material) mats.push(obj.material);
    mats.forEach(m => { m.transparent = true;
        gsap.to(m, { duration: dur, opacity: to, delay, ease: "power1.inOut" }); });
}

// phòng theo mặt tiền
const floorCount = 5,
    roomsPerFloor = 6;
const floorHeight = towerH / floorCount;
const colSpacing = (towerW - 2) / (roomsPerFloor - 1);

function parseRoomId(roomId) {
    const m = /^A([1-5])0?([1-6])$/.exec(roomId);
    if (!m) return null;
    return { floor: parseInt(m[1], 10), col: parseInt(m[2], 10) };
}

function roomExteriorTarget(roomId) {
    const p = parseRoomId(roomId);
    if (!p) return { target: new THREE.Vector3(0, 4, towerD / 2), cam: new THREE.Vector3(8, 9, 8) };
    const y = (p.floor - 0.5) * floorHeight;
    const x = -(towerW / 2 - 1) + (p.col - 1) * colSpacing;
    const z = towerD / 2 + 0.12;
    const target = new THREE.Vector3(x, y, z);
    const cam = new THREE.Vector3(x + 2.5, y + 2.0, z + 3.0);
    return { target, cam };
}

// Hitboxes + Labels
const roomMeshes = [];
floors.forEach((f) => {
    for (let col = 1; col <= 6; col++) {
        const id = `${f}0${col}`;
        const { target } = roomExteriorTarget(id);

        // invisible hitbox để hover/click
        const hit = new THREE.Mesh(new THREE.BoxGeometry(0.9, 1.8, 0.2), new THREE.MeshBasicMaterial({ visible: false }));
        hit.position.copy(target);
        hit.name = id;
        building.add(hit);
        roomMeshes.push(hit);

        // label 3D
        const div = document.createElement('div');
        div.className = 'room-label';
        div.textContent = id;
        const label = new THREE.CSS2DObject(div);
        label.position.copy(target.clone().add(new THREE.Vector3(0, 0.9, 0.05)));
        building.add(label);
    }
});

// ---------- State & animation ----------
let showing = "building";
root.add(building);

function showBuilding() {
    showing = "building";
    root.clear();
    root.add(building);
    gsap.to(camera.position, { duration: 1.2, x: 12, y: 10, z: 18, ease: "power2.inOut" });
    gsap.to(controls.target, { duration: 1.2, x: 0, y: 4, z: 0, ease: "power2.inOut" });
    addFade(building, 1, 0.8, 0);
}

function showRoom() {
    showing = "room";
    root.clear();
    root.add(room);
    gsap.to(camera.position, { duration: 1.2, x: 7.5, y: 3.8, z: 7.5, ease: "power2.inOut" });
    gsap.to(controls.target, { duration: 1.2, x: 0, y: 1.2, z: 0, ease: "power2.inOut" });
    addFade(room, 1, 1.0, 0);
}

function flyIntoRoom(roomId = "A101") {
    if (showing !== "building") { root.clear();
        root.add(building);
        showing = "building"; }
    const ext = roomExteriorTarget(roomId);
    const tl = gsap.timeline();
    tl.to(camera.position, { duration: 1.0, x: 8, y: 8, z: 10, ease: "power2.inOut" }, 0)
        .to(controls.target, { duration: 1.0, x: 0, y: 4, z: 0, ease: "power2.inOut" }, 0)
        .to(camera.position, { duration: 1.2, x: ext.cam.x, y: ext.cam.y, z: ext.cam.z, ease: "power2.inOut" }, 1.0)
        .to(controls.target, { duration: 1.2, x: ext.target.x, y: ext.target.y, z: ext.target.z, ease: "power2.inOut" }, 1.0)
        .to(building.children, {
            duration: 0.8,
            opacity: 0,
            ease: "power1.inOut",
            onStart: () => addFade(building, 0, 0.8, 0)
        }, 2.2)
        .add(() => {
            root.clear();
            root.add(room);
            gsap.to(camera.position, { duration: 1.0, x: 7.5, y: 3.8, z: 7.5, ease: "power2.inOut" });
            gsap.to(controls.target, { duration: 1.0, x: 0, y: 1.2, z: 0, ease: "power2.inOut" });
            addFade(room, 1, 1.0, 0);
            showing = "room";
            updatePanel(roomId);
            console.log("Đang hiển thị phòng:", roomId);
        }, 3.0);
}

// ---------- Tooltip & Panel ----------
const tooltip = document.getElementById('roomTooltip');
const panelContent = document.getElementById('panelContent');

function renderRoomHTML(data) {
    return `<div class="kv"><b>Phòng:</b> ${data.id}</div>
          <div class="kv"><b>Sức chứa:</b> ${data.capacity} SV</div>
          <div class="kv"><b>Hiện tại:</b> ${data.current} SV</div>
          <div class="kv"><b>Tình trạng:</b> ${statusChip(data.status)}</div>
          <div class="kv"><b>Ghi chú:</b> ${data.notes}</div>
          <div style="padding-top:10px"><button class="btn" onclick="flyIntoRoom('${data.id}')">Xem chi tiết (bay vào)</button></div>`;
}

function showRoomTooltip(x, y, roomId) {
    const data = roomData[roomId];
    tooltip.style.display = 'block';
    tooltip.style.left = `${x + 10}px`;
    tooltip.style.top = `${y + 10}px`;
    tooltip.innerHTML = `<b>${roomId}</b><br/>${statusChip(data.status)} · ${data.current}/${data.capacity} SV<br/><i>${data.notes}</i>`;
}

function hideRoomTooltip() { tooltip.style.display = 'none'; }

function updatePanel(roomId) {
    const data = roomData[roomId] || { id: roomId, capacity: 4, current: 0, status: "available", notes: "" };
    panelContent.innerHTML = renderRoomHTML(data);
}

// Raycaster hover/click
const raycaster = new THREE.Raycaster();
const mouse = new THREE.Vector2();
renderer.domElement.addEventListener('pointermove', (e) => {
    const rect = renderer.domElement.getBoundingClientRect();
    mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
    raycaster.setFromCamera(mouse, camera);
    const hit = raycaster.intersectObjects(roomMeshes);
    if (hit.length > 0) {
        const roomId = hit[0].object.name;
        showRoomTooltip(e.clientX, e.clientY, roomId);
        updatePanel(roomId);
    } else hideRoomTooltip();
});
renderer.domElement.addEventListener('pointerdown', (e) => {
    const rect = renderer.domElement.getBoundingClientRect();
    mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
    raycaster.setFromCamera(mouse, camera);
    const hit = raycaster.intersectObjects(roomMeshes);
    if (hit.length > 0) {
        const roomId = hit[0].object.name;
        flyIntoRoom(roomId);
    }
});

// ---------- UI: floor & room buttons ----------
const floorSelect = document.getElementById("floorSelect");
floors.forEach((f, i) => {
    const opt = document.createElement('option');
    opt.value = f;
    opt.textContent = f;
    if (i === 0) opt.selected = true;
    floorSelect.appendChild(opt);
});
const roomsWrap = document.getElementById("roomsWrap");

function buildRoomsUI() {
    roomsWrap.innerHTML = "";
    const floor = floorSelect.value;
    for (let i = 1; i <= 6; i++) {
        const id = `${floor}0${i}`;
        const btn = document.createElement('button');
        btn.className = "room-btn";
        btn.textContent = id;
        btn.onclick = () => flyIntoRoom(id);
        roomsWrap.appendChild(btn);
    }
}
floorSelect.onchange = buildRoomsUI;
buildRoomsUI();

// ---------- Resize & Animate ----------
window.addEventListener('resize', () => {
    const w = container.clientWidth;
    const h = container.clientHeight;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
    labelRenderer.setSize(w, h);
});

(function loop() {
    requestAnimationFrame(loop);
    controls.update();
    renderer.render(scene, camera);
    labelRenderer.render(scene, camera);
})();

// Start
showBuilding();