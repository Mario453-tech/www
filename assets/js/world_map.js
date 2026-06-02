/**
 * world_map.js
 * Globus: threejs-earth-main (1:1, adapted for CDN r134 + div container)
 * Panel boczny + pinezki: Oil Empire
 */

(function () {
    'use strict';

 // i18n dictionary / Slownik i18n
    var _ML = window.MAP_LANG || {};
    function mlt(k, p) {
        var s = _ML[k] || k;
        if (p) Object.keys(p).forEach(function(pk) { s = s.replace(':' + pk, p[pk]); });
        return s;
    }

 // Data from PHP / Dane z PHP
    var RAW        = JSON.parse(document.getElementById('map-data-json').value);
    var CSRF       = document.getElementById('csrf-token').value;
    var playerCash = parseFloat(document.getElementById('player-cash').value);

    var regions   = RAW.regions;
    var locations = RAW.locations;

    var regionById  = {};
    regions.forEach(function(r)  { regionById[r.id]  = r; });
    var locationById = {};
    locations.forEach(function(l) { locationById[l.id] = l; });
    var locByRegion = {};
    locations.forEach(function(l) {
        if (!locByRegion[l.region_id]) locByRegion[l.region_id] = [];
        locByRegion[l.region_id].push(l);
    });

 // Status colors / Kolory statusow
    var STATUS_COLOR = {
        'active':         0x2ecc71,
        'paused_cash':    0xf1c40f,
        'paused_storage': 0xf1c40f,
        'paused_staff':   0xf1c40f,
        'no_operator':    0xe67e22,
        'no_technician':  0xe67e22,
        'contaminated':   0xe67e22,
        'broken':         0xe74c3c,
        'blowout':        0xff1100,
        'seized':         0x9b59b6,
        'servicing':      0x3498db,
    };

 // Region centers / Centra regionow
    var REGION_CENTERS = {
        'middle_east':    { lat: 24,  lng: 47   },
        'russia':         { lat: 62,  lng: 100  },
        'africa':         { lat:  0,  lng: 20   },
        'usa_canada':     { lat: 48,  lng: -100 },
        'north_europe':   { lat: 63,  lng: 15   },
        'southeast_asia': { lat:  5,  lng: 115  },
        'latam':          { lat: -22, lng: -60  },
    };

 // Filtering and sorting / Filtrowanie i sortowanie
    var sortMode   = 'default';
    var tierFilter = 'all';

    function calcCost(loc) {
        var entryCost = parseFloat(loc.effective_entry_cost || loc.entry_cost || 5000000);
        return Math.round(entryCost * Math.max(1.0, parseFloat(loc.oil_richness) * 0.8));
    }
    function calcProd(loc) {
        var r = regionById[loc.region_id];
        var prodBonus = parseFloat(r ? r.production_bonus : 0);
        var baseP = loc.well_type === 'offshore' ? 59 : 54;
        return baseP * (1 + prodBonus) * parseFloat(loc.oil_richness);
    }
    function applyFilterSort(locs) {
        var out = tierFilter === 'all'
            ? locs.slice()
            : locs.filter(function(l) { return (l.tier || 'medium') === tierFilter; });
        if (sortMode === 'price-asc')  out.sort(function(a,b) { return calcCost(a) - calcCost(b); });
        if (sortMode === 'price-desc') out.sort(function(a,b) { return calcCost(b) - calcCost(a); });
        if (sortMode === 'prod-asc')   out.sort(function(a,b) { return calcProd(a) - calcProd(b); });
        if (sortMode === 'prod-desc')  out.sort(function(a,b) { return calcProd(b) - calcProd(a); });
        return out;
    }

    var TIER_LABEL = { starter: ' Starter', medium: ' Średni', advanced: ' Zaawansowany' };
    var TIER_CLS   = { starter: 'tier-starter', medium: 'tier-medium', advanced: 'tier-advanced' };

 // 
 // GLOBE / GLOBUS
 // 

    var container = document.getElementById('globe-container');
    if (!container || typeof THREE === 'undefined') return;

    var W = container.clientWidth;
    var H = container.clientHeight;

 // Build starfield / Zbuduj pole gwiazd
    function getStarfield(opts) {
        var numStars = (opts && opts.numStars) ? opts.numStars : 500;
        function randomSpherePoint() {
            var radius = Math.random() * 25 + 25;
            var u = Math.random(), v = Math.random();
            var theta = 2 * Math.PI * u;
            var phi   = Math.acos(2 * v - 1);
            return new THREE.Vector3(
                radius * Math.sin(phi) * Math.cos(theta),
                radius * Math.sin(phi) * Math.sin(theta),
                radius * Math.cos(phi)
            );
        }
        var verts = [], colors = [], col;
        for (var i = 0; i < numStars; i++) {
            var p = randomSpherePoint();
            col = new THREE.Color().setHSL(0.6, 0.2, Math.random());
            verts.push(p.x, p.y, p.z);
            colors.push(col.r, col.g, col.b);
        }
        var geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute(verts, 3));
        geo.setAttribute('color',    new THREE.Float32BufferAttribute(colors, 3));
        return new THREE.Points(geo, new THREE.PointsMaterial({
            size: 0.2,
            vertexColors: true,
            map: new THREE.TextureLoader().load('/textures/stars/circle.png'),
        }));
    }

 // Build Fresnel material / Zbuduj material Fresnela
    function getFresnelMat(opts) {
        var rimHex    = (opts && opts.rimHex)    ? opts.rimHex    : 0x0088ff;
        var facingHex = (opts && opts.facingHex) ? opts.facingHex : 0x000000;
        return new THREE.ShaderMaterial({
            uniforms: {
                color1:       { value: new THREE.Color(rimHex) },
                color2:       { value: new THREE.Color(facingHex) },
                fresnelBias:  { value: 0.1 },
                fresnelScale: { value: 0.1 },
                fresnelPower: { value: 1.0 },
            },
            vertexShader: [
                'uniform float fresnelBias;',
                'uniform float fresnelScale;',
                'uniform float fresnelPower;',
                'varying float vReflectionFactor;',
                'void main() {',
                '  vec4 mvPosition    = modelViewMatrix * vec4(position, 1.0);',
                '  vec4 worldPosition = modelMatrix * vec4(position, 1.0);',
                '  vec3 worldNormal   = normalize(mat3(modelMatrix[0].xyz, modelMatrix[1].xyz, modelMatrix[2].xyz) * normal);',
                '  vec3 I = worldPosition.xyz - cameraPosition;',
                '  vReflectionFactor = fresnelBias + fresnelScale * pow(1.0 + dot(normalize(I), worldNormal), fresnelPower);',
                '  gl_Position = projectionMatrix * mvPosition;',
                '}',
            ].join('\n'),
            fragmentShader: [
                'uniform vec3 color1;',
                'uniform vec3 color2;',
                'varying float vReflectionFactor;',
                'void main() {',
                '  float f = clamp(vReflectionFactor, 0.0, 1.0);',
                '  gl_FragColor = vec4(mix(color2, color1, vec3(f)), f);',
                '}',
            ].join('\n'),
            transparent: true,
            blending:    THREE.AdditiveBlending,
        });
    }

 // Scene setup / Konfiguracja sceny
    var scene  = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(75, W / H, 0.1, 1000);
    camera.position.z = 5;

    var renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(W, H);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    container.appendChild(renderer.domElement);

 // Earth group / Grupa Ziemi
    var earthGroup = new THREE.Group();
    earthGroup.rotation.z = -23.4 * Math.PI / 180;
    scene.add(earthGroup);

    var GLOBE_R = 1.0;
    var controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.minDistance   = 1.4;
    controls.maxDistance   = 8;

    var loader   = new THREE.TextureLoader();
    var geometry = new THREE.IcosahedronGeometry(GLOBE_R, 12);

 // Layer 1: day map / Warstwa 1: mapa dzienna
    var earthMesh = new THREE.Mesh(geometry, new THREE.MeshPhongMaterial({
        map:         loader.load('/textures/00_earthmap1k.jpg'),
        specularMap: loader.load('/textures/02_earthspec1k.jpg'),
        bumpMap:     loader.load('/textures/01_earthbump1k.jpg'),
        bumpScale:   0.04,
    }));
    earthGroup.add(earthMesh);

 // Layer 2: night lights / Warstwa 2: swiatla nocne
    var lightsMesh = new THREE.Mesh(geometry, new THREE.MeshBasicMaterial({
        map:      loader.load('/textures/03_earthlights1k.jpg'),
        blending: THREE.AdditiveBlending,
    }));
    earthGroup.add(lightsMesh);

 // Layer 3: clouds / Warstwa 3: chmury
    var cloudsMesh = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({
        map:         loader.load('/textures/04_earthcloudmap.jpg'),
        transparent: true,
        opacity:     0.8,
        blending:    THREE.AdditiveBlending,
        alphaMap:    loader.load('/textures/05_earthcloudmaptrans.jpg'),
    }));
    cloudsMesh.scale.setScalar(1.003);
    earthGroup.add(cloudsMesh);

 // Layer 4: Fresnel glow / Warstwa 4: poswiata Fresnela
    var glowMesh = new THREE.Mesh(geometry, getFresnelMat());
    glowMesh.scale.setScalar(1.01);
    earthGroup.add(glowMesh);

 // Stars / Gwiazdy
    var stars = getStarfield({ numStars: 2000 });
    scene.add(stars);

 // Lighting / Oswietlenie
    scene.add(new THREE.AmbientLight(0x404040, 1.5));

    var sunLight = new THREE.DirectionalLight(0xffffff, 3.5);
    sunLight.position.set(0, 0, 3);
    scene.add(sunLight);

 // 
 // MAP MARKERS / PINEZKI
 // 

    function latLngToVec3(lat, lng, r) {
        var phi   = (90 - lat)  * Math.PI / 180;
        var theta = (lng + 180) * Math.PI / 180;
        return new THREE.Vector3(
            -r * Math.sin(phi) * Math.cos(theta),
             r * Math.cos(phi),
             r * Math.sin(phi) * Math.sin(theta)
        );
    }

    var clickableObjects = [];
    var allScalable      = [];

    function spawnDot(pos, color, dotR, userData, pulsePhase) {
        var isPulse = pulsePhase !== undefined;

        var border = new THREE.Mesh(
            new THREE.SphereGeometry(dotR * 1.22, 10, 10),
            new THREE.MeshBasicMaterial({ color: 0xffffff })
        );
        border.position.copy(pos);
        if (isPulse) { border.userData.pulse = true; border.userData.pulsePhase = pulsePhase; }
        earthGroup.add(border);
        allScalable.push(border);

        var mesh = new THREE.Mesh(
            new THREE.SphereGeometry(dotR, 10, 10),
            new THREE.MeshBasicMaterial({ color: color })
        );
        mesh.position.copy(pos);
        mesh.userData = Object.assign({}, userData);
        if (isPulse) { mesh.userData.pulse = true; mesh.userData.pulsePhase = pulsePhase; }
        earthGroup.add(mesh);
        clickableObjects.push(mesh);
        allScalable.push(mesh);

        var halo = new THREE.Mesh(
            new THREE.SphereGeometry(dotR * 1.9, 8, 8),
            new THREE.MeshBasicMaterial({
                color: color, transparent: true, opacity: 0.14,
                depthWrite: false, blending: THREE.AdditiveBlending,
            })
        );
        halo.position.copy(pos);
        halo.userData = Object.assign({}, userData);
        if (isPulse) { halo.userData.pulse = true; halo.userData.pulsePhase = pulsePhase; halo.userData.isHalo = true; }
        earthGroup.add(halo);
        clickableObjects.push(halo);
        allScalable.push(halo);
    }

    locations.forEach(function(loc) {
        var lat = parseFloat(loc.latitude);
        var lng = parseFloat(loc.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        var isMine     = loc.occupied_by_me;
        var isOccupied = loc.occupied_by_anyone;
        var r          = regionById[loc.region_id];
        var color;
        if (isMine) {
            var ws = loc.my_well_status || 'active';
            color  = STATUS_COLOR[ws] !== undefined ? STATUS_COLOR[ws] : 0x2ecc71;
        } else if (isOccupied) {
            color = 0x2c3e50;
        } else {
            color = parseInt((r ? r.color_hex : '#c8a84b').replace('#', ''), 16);
        }

        var estProd = calcProd(loc);
        var baseR   = isMine ? 0.016 : 0.009;
        var dotR    = Math.max(baseR * 0.8, Math.min(baseR * 2.5, baseR * (estProd / 60)));
        var pos     = latLngToVec3(lat, lng, GLOBE_R + 0.013);
        var pulse   = (isMine && loc.my_well_status === 'active') ? Math.random() * Math.PI * 2 : undefined;

        spawnDot(pos, color, dotR, { type: 'location', locationId: parseInt(loc.id) }, pulse);
    });

    regions.forEach(function(r) {
        var center = REGION_CENTERS[r.code];
        if (!center) return;

        var color = parseInt(r.color_hex.replace('#', ''), 16);
        var pos   = latLngToVec3(center.lat, center.lng, GLOBE_R + 0.011);
        var rSize = 0.028;

        var border = new THREE.Mesh(
            new THREE.SphereGeometry(rSize * 1.22, 12, 12),
            new THREE.MeshBasicMaterial({ color: 0xffffff })
        );
        border.position.copy(pos);
        earthGroup.add(border);
        allScalable.push(border);

        var mesh = new THREE.Mesh(
            new THREE.SphereGeometry(rSize, 12, 12),
            new THREE.MeshBasicMaterial({ color: color })
        );
        mesh.position.copy(pos);
        mesh.userData = { type: 'region', regionId: parseInt(r.id) };
        earthGroup.add(mesh);
        clickableObjects.push(mesh);
        allScalable.push(mesh);

        var halo = new THREE.Mesh(
            new THREE.SphereGeometry(rSize * 2.2, 8, 8),
            new THREE.MeshBasicMaterial({
                color: color, transparent: true, opacity: 0.15,
                depthWrite: false, blending: THREE.AdditiveBlending,
            })
        );
        halo.position.copy(pos);
        halo.userData = { type: 'region', regionId: parseInt(r.id) };
        earthGroup.add(halo);
        clickableObjects.push(halo);
        allScalable.push(halo);
    });

 // Raycasting / Raycasting
    var raycaster    = new THREE.Raycaster();
    var mouse        = new THREE.Vector2();
    var mouseDownPos = { x: 0, y: 0 };

    renderer.domElement.addEventListener('mousedown', function(e) {
        mouseDownPos = { x: e.clientX, y: e.clientY };
    });
    renderer.domElement.addEventListener('mouseup', function(e) {
        if (Math.abs(e.clientX - mouseDownPos.x) > 5 ||
            Math.abs(e.clientY - mouseDownPos.y) > 5) return;
        var rect = renderer.domElement.getBoundingClientRect();
        mouse.x  =  ((e.clientX - rect.left) / rect.width)  * 2 - 1;
        mouse.y  = -((e.clientY - rect.top)  / rect.height) * 2 + 1;
        raycaster.setFromCamera(mouse, camera);
        var hits = raycaster.intersectObjects(clickableObjects);
        if (!hits.length) return;
        var obj = hits[0].object;
        if (obj.userData.type === 'location') showLocation(obj.userData.locationId);
        else if (obj.userData.type === 'region') showRegion(obj.userData.regionId);
    });

 // Tooltip / Podpowiedz
    var tooltip = document.createElement('div');
    tooltip.style.cssText = [
        'position:fixed', 'background:rgba(5,10,20,.90)', 'color:#e0eeff',
        'padding:5px 12px', 'border-radius:7px', 'font-size:12px',
        'font-weight:500', 'pointer-events:none', 'display:none', 'z-index:999',
        'border:1px solid rgba(60,110,220,.30)', 'box-shadow:0 2px 14px rgba(0,0,0,.55)',
    ].join(';');
    document.body.appendChild(tooltip);

    renderer.domElement.addEventListener('mousemove', function(e) {
        var rect = renderer.domElement.getBoundingClientRect();
        mouse.x  =  ((e.clientX - rect.left) / rect.width)  * 2 - 1;
        mouse.y  = -((e.clientY - rect.top)  / rect.height) * 2 + 1;
        raycaster.setFromCamera(mouse, camera);
        var hits = raycaster.intersectObjects(clickableObjects);
        if (hits.length) {
            var obj = hits[0].object, label = '';
            if (obj.userData.type === 'location') {
                var loc = locationById[obj.userData.locationId];
                if (loc) label = loc.name + (loc.occupied_by_me ? ' ' : loc.occupied_by_anyone ? ' ' : '');
            } else if (obj.userData.type === 'region') {
                var reg = regionById[obj.userData.regionId];
                if (reg) label = ' ' + reg.name;
            }
            if (label) {
                tooltip.textContent = label;
                tooltip.style.display = 'block';
                tooltip.style.left = (e.clientX + 16) + 'px';
                tooltip.style.top  = (e.clientY - 8)  + 'px';
                renderer.domElement.style.cursor = 'pointer';
                return;
            }
        }
        tooltip.style.display = 'none';
        renderer.domElement.style.cursor = 'grab';
    });

 // Animation loop / Petla animacji
    var clock = new THREE.Clock();

    function animate() {
        requestAnimationFrame(animate);
        var t = clock.getElapsedTime();

        earthMesh.rotation.y  += 0.002;
        lightsMesh.rotation.y += 0.002;
        cloudsMesh.rotation.y += 0.0023;
        glowMesh.rotation.y   += 0.002;
        stars.rotation.y      -= 0.0002;

        var camDist  = camera.position.length();
        var dotScale = Math.pow(camDist / 5, 1.35);
        dotScale     = Math.max(0.4, Math.min(2.4, dotScale));

        for (var i = 0; i < allScalable.length; i++) {
            var m = allScalable[i];
            if (m.userData.pulse) continue;
            m.scale.setScalar(dotScale);
        }
        for (var i = 0; i < allScalable.length; i++) {
            var m = allScalable[i];
            if (!m.userData.pulse) continue;
            var amp = m.userData.isHalo ? 0.55 : 0.28;
            var p   = 1 + amp * Math.sin(t * 3 + m.userData.pulsePhase);
            m.scale.setScalar(dotScale * p);
        }

        controls.update();
        renderer.render(scene, camera);
    }
    animate();

    window.addEventListener('resize', function() {
        var w = container.clientWidth;
        var h = container.clientHeight;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    });

 // 
 // PANEL BOCZNY
 // 

    var elPlaceholder   = document.getElementById('sidebar-placeholder');
    var elRegionPanel   = document.getElementById('sidebar-region');
    var elLocationPanel = document.getElementById('sidebar-location');
    var elBrowsePanel   = document.getElementById('sidebar-browse');
    var currentRegionId = null;

    function showPanel(which) {
        elPlaceholder.style.display   = which === 'placeholder' ? '' : 'none';
        elRegionPanel.style.display   = which === 'region'      ? '' : 'none';
        elLocationPanel.style.display = which === 'location'    ? '' : 'none';
        elBrowsePanel.style.display   = which === 'browse'      ? '' : 'none';
    }

    document.querySelectorAll('#top-tier-btns .map-filter-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            tierFilter = btn.dataset.tier;
            document.querySelectorAll('#top-tier-btns .map-filter-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            refreshCurrentPanel();
        });
    });
    document.querySelectorAll('#top-sort-btns .map-sort-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            sortMode = btn.dataset.sort;
            document.querySelectorAll('#top-sort-btns .map-sort-btn').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            refreshCurrentPanel();
        });
    });

    function refreshCurrentPanel() {
        if (elRegionPanel.style.display !== 'none' && currentRegionId !== null) refreshRegionLocations();
        else if (elBrowsePanel.style.display !== 'none') refreshBrowseLocations();
        else showAllLocations();
    }

    function renderLocList(locs, containerId) {
        var el = document.getElementById(containerId);
        if (!el) return;
        var sorted = applyFilterSort(locs);
        if (!sorted.length) {
            el.innerHTML = '<p class="c-muted" style="padding:8px 0">' + mlt('no_filter_results') + '</p>';
            return;
        }
        el.innerHTML = sorted.map(function(loc) {
            var isMine     = loc.occupied_by_me;
            var isOccupied = loc.occupied_by_anyone;
            var r          = regionById[loc.region_id];
            var cls        = isMine ? 'loc-item--mine' : isOccupied ? 'loc-item--taken' : 'loc-item--free';
            var tier       = loc.tier || 'medium';
            var needsPermit = !isMine && !isOccupied && r && r.has_permit === false;
            var badge      = isMine
                ? '<span class="loc-badge loc-badge--mine">'  + mlt('badge_mine')  + '</span>'
                : isOccupied
                    ? '<span class="loc-badge loc-badge--taken">' + mlt('badge_taken') + '</span>'
                    : needsPermit
                        ? '<span class="loc-badge loc-badge--permit">' + mlt('badge_permit') + '</span>'
                        : '<span class="loc-badge loc-badge--free">'  + mlt('badge_free')  + '</span>';
            var regionTag = containerId === 'browse-list'
                ? '<span style="color:' + (r ? r.color_hex : '#aaa') + '">' + escHtml(r ? r.name : '') + '</span> - ' : '';
            return '<div class="loc-item ' + cls + '" onclick="window.__mapShowLocation(' + loc.id + ')">'
                + '<div class="loc-item-name">' + escHtml(loc.name)
                + '<span class="loc-tier-badge ' + (TIER_CLS[tier] || '') + '">' + (TIER_LABEL[tier] || tier) + '</span></div>'
                + '<div class="loc-item-meta">'
                + regionTag
                + (loc.well_type === 'offshore' ? mlt('offshore') : mlt('onshore'))
                + ' - ' + calcProd(loc).toFixed(1) + ' bbl/h - ' + fmt(calcCost(loc)) + ' PLN'
                + badge
                + '</div></div>';
        }).join('');
    }

    function refreshRegionLocations() {
        if (currentRegionId === null) return;
        renderLocList(locByRegion[currentRegionId] || [], 'sr-locations');
    }
    function refreshBrowseLocations() { renderLocList(locations, 'browse-list'); }

    function showRegion(regionId) {
        var r = regionById[regionId];
        if (!r) return;
        currentRegionId = regionId;
        document.getElementById('sr-name').textContent = r.name;
        document.getElementById('sr-sub').textContent  = r.description || '';
        var riskStars = ''.repeat(parseInt(r.political_risk));
        document.getElementById('sr-metrics').innerHTML =
            '<div class="sr-metric"><div class="sr-m-val c-good">+' + Math.round(r.production_bonus * 100) + '%</div><div class="sr-m-lbl">' + mlt('prod_bonus')     + '</div></div>'
          + '<div class="sr-metric"><div class="sr-m-val c-warn">'  + Math.round(r.tax_rate * 100)        + '%</div><div class="sr-m-lbl">' + mlt('tax_rate')       + '</div></div>'
          + '<div class="sr-metric"><div class="sr-m-val">'         + riskStars                           + '</div><div class="sr-m-lbl">' + mlt('political_risk')  + '</div></div>'
          + '<div class="sr-metric"><div class="sr-m-val c-gold">'  + fmt(r.entry_cost)                   + '</div><div class="sr-m-lbl">' + mlt('entry_cost')      + '</div></div>';
        refreshRegionLocations();
        showPanel('region');
    }

    function showAllLocations() { refreshBrowseLocations(); showPanel('browse'); }

    function showLocation(locationId) {
        var loc = locationById[locationId];
        if (!loc) return;
        var r = regionById[loc.region_id];

        var isMine     = loc.occupied_by_me;
        var isOccupied = loc.occupied_by_anyone;
        var entryCost  = parseFloat(loc.effective_entry_cost || (r ? r.entry_cost : 5000000));
        var richness   = parseFloat(loc.oil_richness);
        var totalCost  = Math.round(entryCost * Math.max(1.0, richness * 0.8));
        var taxRate    = parseFloat(loc.effective_tax_rate || (r ? r.tax_rate : 0));
        var prodBonus  = parseFloat(r ? r.production_bonus : 0);
        var baseP      = loc.well_type === 'offshore' ? 59 : 54;
        var estProd    = (baseP * (1 + prodBonus) * richness).toFixed(2);
        var tier       = loc.tier || 'medium';

        document.getElementById('loc-hdr').innerHTML =
            '<div class="loc-name">' + escHtml(loc.name) + '</div>'
          + '<div class="loc-region" style="color:' + (r ? r.color_hex : '#aaa') + '">' + escHtml(r ? r.name : '') + '</div>'
          + '<div class="loc-type">'
          + (loc.well_type === 'offshore' ? mlt('offshore') : mlt('onshore'))
          + ' - ' + loc.country_code
          + '<span class="loc-tier-badge ' + (TIER_CLS[tier] || '') + '" style="margin-left:6px">' + (TIER_LABEL[tier] || tier) + '</span></div>';

        document.getElementById('loc-metrics').innerHTML =
            '<div class="sr-metric"><div class="sr-m-val c-gold">' + estProd + ' bbl/h</div><div class="sr-m-lbl">' + mlt('est_production') + '</div></div>'
          + '<div class="sr-metric"><div class="sr-m-val">×' + richness.toFixed(1) + '</div><div class="sr-m-lbl">' + mlt('oil_richness') + '</div></div>'
          + '<div class="sr-metric"><div class="sr-m-val c-warn">' + Math.round(taxRate * 100) + '%/h</div><div class="sr-m-lbl">' + mlt('regional_tax') + '</div></div>';

        var buyHtml = '';
        if (isMine) {
            var wStatus  = loc.my_well_status || 'active';
            var wProd    = loc.my_well_production != null ? parseFloat(loc.my_well_production).toFixed(1) : '-';
            var wCond    = loc.my_well_condition  != null ? parseFloat(loc.my_well_condition).toFixed(1)  : '-';
            var wLevel   = loc.my_well_level != null ? loc.my_well_level : '-';
            var wStLabel = mlt('well_status_' + wStatus) || wStatus;
            var wStCls   = wStatus === 'active' ? 'c-good' : 'c-bad';
            var condCls  = parseFloat(wCond) >= 70 ? 'c-good' : (parseFloat(wCond) >= 40 ? 'c-warn' : 'c-bad');
            buyHtml =
                '<div class="loc-owned-badge">' + mlt('my_well', { id: loc.my_well_id }) + '</div>'
              + '<div class="loc-well-stats">'
              + '<div class="loc-ws-row"><span class="loc-ws-lbl">' + mlt('well_status')     + '</span><span class="loc-ws-val ' + wStCls  + '">' + wStLabel + '</span></div>'
              + '<div class="loc-ws-row"><span class="loc-ws-lbl">' + mlt('well_production') + '</span><span class="loc-ws-val c-gold">'  + wProd + ' bbl/h</span></div>'
              + '<div class="loc-ws-row"><span class="loc-ws-lbl">' + mlt('well_condition')  + '</span><span class="loc-ws-val ' + condCls + '">' + wCond + '%</span></div>'
              + '<div class="loc-ws-row"><span class="loc-ws-lbl">' + mlt('well_level')      + '</span><span class="loc-ws-val">lvl ' + wLevel + '</span></div>'
              + '</div>'
              + '<a href="/technical" class="btn-well-technical">' + mlt('well_go_technical') + '</a>';
        } else if (isOccupied) {
            buyHtml = '<div class="loc-taken-badge">' + mlt('taken_badge') + '</div>';
        } else if (r && r.has_permit === false) {
            buyHtml =
                '<div class="loc-permit-required">'
              + '<div class="loc-permit-title">' + mlt('permit_required_title') + '</div>'
              + '<div class="loc-permit-text">'  + mlt('permit_required_text')  + '</div>'
              + '<a href="/legal" class="btn-permit-legal">' + mlt('permit_required_btn') + '</a>'
              + '</div>';
        } else if (playerCash < totalCost) {
            buyHtml =
                '<div class="loc-nofunds">'
              + '<div class="loc-nofunds-title">' + mlt('no_funds_title') + '</div>'
              + '<div class="loc-nofunds-diff">'  + mlt('no_funds_diff', { amount: fmt(totalCost - playerCash) }) + '</div>'
              + '</div>';
        } else {
            buyHtml =
                '<form id="buy-form" class="buy-form">'
              + '<input type="hidden" name="action"      value="buy_well">'
              + '<input type="hidden" name="csrf_token"  value="' + CSRF + '">'
              + '<input type="hidden" name="location_id" value="' + loc.id + '">'
              + '<div class="buy-summary">'
              + '<div class="buy-sum-row"><span>' + mlt('location_label')   + '</span><strong>' + escHtml(loc.name) + '</strong></div>'
              + '<div class="buy-sum-row"><span>' + mlt('region_label')     + '</span><strong style="color:' + (r ? r.color_hex : '#aaa') + '">' + escHtml(r ? r.name : '') + '</strong></div>'
              + '<div class="buy-sum-row"><span>' + mlt('est_production')   + '</span><strong class="c-gold">'  + estProd + ' bbl/h</strong></div>'
              + '<div class="buy-sum-row"><span>' + mlt('regional_tax_row') + '</span><strong class="c-warn">'  + Math.round(taxRate * 100) + mlt('tax_suffix') + '</strong></div>'
              + '<div class="buy-sum-row buy-sum-total"><span>' + mlt('buy_cost') + '</span><strong class="c-good">' + fmt(totalCost) + ' PLN</strong></div>'
              + '</div>'
              + '<button type="submit" class="btn-buy-well">' + mlt('buy_btn', { cost: fmt(totalCost) }) + '</button>'
              + '</form>';
        }

        document.getElementById('loc-buy-section').innerHTML = buyHtml;

        var form = document.getElementById('buy-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var runPurchase = function () {
                    var btn = form.querySelector('button');
                    btn.disabled = true;
                    btn.textContent = mlt('buying');

                    fetch('/map', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: new FormData(form)
                    })
                    .then(function(resp) {
                        return resp.json().catch(function () {
                            return { success: false, message: 'Błąd odpowiedzi serwera.' };
                        });
                    })
                    .then(function(data) {
                        if (data && data.success) {
                            if (typeof showGameToast === 'function') {
                                showGameToast(mlt('buy_success_title'), data.message || '', 'success');
                            }
                            setTimeout(function () {
                                window.location.href = '/map';
                            }, 900);
                            return;
                        }

                        btn.disabled = false;
                        btn.textContent = mlt('buy_btn', { cost: fmt(totalCost) });
                        alertError((data && data.message) || mlt('err_connection'), mlt('buy_error_title'));
                    })
                    .catch(function() {
                        btn.disabled = false;
                        btn.textContent = mlt('buy_btn', { cost: fmt(totalCost) });
                        alertError(mlt('err_connection'), mlt('buy_error_title'));
                    });
                };

                var confirmText = mlt('buy_confirm_text', {
                    location: loc.name,
                    cost: fmt(totalCost)
                });

                if (typeof confirmAction === 'function') {
                    confirmAction(confirmText, runPurchase, {
                        title: mlt('buy_confirm_title'),
                        type: 'warning',
                        confirmLabel: mlt('buy_confirm_btn')
                    });
                    return;
                }

                runPurchase();
            });
        }
        showPanel('location');
    }

    document.getElementById('loc-back').addEventListener('click', function() {
        if (currentRegionId) showRegion(currentRegionId); else showPanel('placeholder');
    });
    document.getElementById('browse-back').addEventListener('click', function() { showPanel('placeholder'); });
    document.getElementById('btn-browse-all').addEventListener('click', showAllLocations);

    window.__mapShowLocation = showLocation;
    window.__mapShowAllLoc   = showAllLocations;

    function fmt(n) {
        return new Intl.NumberFormat(window.APP_LOCALE || 'pl-PL', { maximumFractionDigits: 0 }).format(n);
    }
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    if (location.search) history.replaceState(null, '', '/map');

})();
