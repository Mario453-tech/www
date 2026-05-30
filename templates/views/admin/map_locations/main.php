<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.map.heading') ?></h1>

<?php if ($msg):   ?><p role="status" class="alert alert-success"><?= htmlspecialchars($msg)   ?></p><?php endif ?>
<?php if ($error): ?><p role="alert"  class="alert alert-error"  ><?= htmlspecialchars($error) ?></p><?php endif ?>

<div class="cards">
    <div class="card"><p class="label"><?= t('admin.map.stat_regions') ?></p><p class="value"><?= count($regions) ?></p></div>
    <div class="card"><p class="label"><?= t('admin.map.stat_locations') ?></p><p class="value"><?= count($locations) ?></p></div>
    <div class="card"><p class="label"><?= t('admin.map.stat_active') ?></p><p class="value green"><?= $countActive ?></p></div>
    <div class="card"><p class="label"><?= t('admin.map.stat_with_well') ?></p><p class="value orange"><?= count($wellCounts) ?></p></div>
</div>

<!-- Parametry regionw -->
<section class="panel" aria-label="Parametry regionw">
    <p class="panel-title"><?= t('admin.map.regions_title') ?></p>
    <?php foreach ($regions as $r): ?>
    <details class="region-details">
        <summary class="region-summary" style="color:<?= htmlspecialchars($r['color_hex']) ?>">
            <?= htmlspecialchars($r['name']) ?>
            <span class="muted region-summary-meta">
                <?= t('admin.map.cost_label') ?>: <?= number_format((float)$r['entry_cost'], 0, '.', ' ') ?> <?= t('common.pln') ?>
                 +<?= round((float)$r['production_bonus'] * 100) ?>%
                 <?= round((float)$r['tax_rate'] * 100) ?>%/h
                 <?= t('admin.map.risk_label') ?> <?= (int)$r['political_risk'] ?>/5
            </span>
        </summary>
        <form method="post" class="region-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action"    value="update_region">
            <input type="hidden" name="region_id" value="<?= (int)$r['id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label><?= t('admin.map.entry_cost_label') ?></label>
                    <input type="number" name="entry_cost" value="<?= (float)$r['entry_cost'] ?>" class="input-w-lg" step="100000">
                </div>
                <div class="form-group">
                    <label><?= t('admin.map.prod_bonus_label') ?></label>
                    <input type="number" name="production_bonus" value="<?= round((float)$r['production_bonus'] * 100) ?>" class="input-w-sm" step="1" min="0">
                </div>
                <div class="form-group">
                    <label><?= t('admin.map.tax_label_form') ?></label>
                    <input type="number" name="tax_rate" value="<?= round((float)$r['tax_rate'] * 100) ?>" class="input-w-sm" step="0.5" min="0">
                </div>
                <div class="form-group">
                    <label><?= t('admin.map.risk_form_label') ?></label>
                    <input type="number" name="political_risk" value="<?= (int)$r['political_risk'] ?>" class="input-w-xs" min="1" max="5">
                </div>
                <div class="form-group form-group--end">
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('common.save') ?></button>
                </div>
            </div>
        </form>
    </details>
    <?php endforeach ?>
</section>

<!-- Dodaj lokalizacj -->
<section class="panel">
    <p class="panel-title"><?= t('admin.map.add_title') ?></p>
    <form method="post" class="form-add-loc">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="add_location">
        <div class="form-row">
            <div class="form-group">
                <label><?= t('admin.map.field_region') ?></label>
                <select name="region_id" required>
                    <?php foreach ($regions as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_name') ?></label>
                <input type="text" name="name" required class="input-w-lg">
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_country') ?></label>
                <input type="text" name="country_code" maxlength="5" required class="input-w-xs">
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_lat') ?></label>
                <input type="number" name="latitude" step="any" required class="input-w-sm">
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_lng') ?></label>
                <input type="number" name="longitude" step="any" required class="input-w-sm">
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_richness') ?></label>
                <input type="number" name="oil_richness" step="0.1" min="0.5" max="3.0" value="1.0" required class="input-w-sm">
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_type') ?></label>
                <select name="well_type">
                    <option value="onshore"><?= t('admin.map.type_onshore') ?></option>
                    <option value="offshore"><?= t('admin.map.type_offshore') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_desc') ?></label>
                <input type="text" name="description" class="input-w-lg">
            </div>
            <div class="form-group form-group--end">
                <button type="submit" class="btn btn-primary"><?= t('admin.map.add_btn') ?></button>
            </div>
        </div>
    </form>
</section>

<!-- Lista lokalizacji -->
<section class="panel">
    <p class="panel-title"><?= t('admin.map.list_title') ?> (<?= $totalLocs ?>)</p>
    <div class="data-list data-list--locations">
        <div class="list-header list-header--locations">
            <span>ID</span>
            <span><?= t('admin.map.field_name') ?></span>
            <span><?= t('admin.map.field_region') ?></span>
            <span><?= t('admin.map.field_country') ?></span>
            <span><?= t('admin.map.field_type') ?></span>
            <span><?= t('admin.map.field_richness') ?></span>
            <span><?= t('admin.map.col_wells') ?></span>
            <span><?= t('admin.map.col_status') ?></span>
            <span><?= t('common.actions') ?></span>
        </div>
        <?php foreach ($pagedLocs as $loc):
            $locId  = (int)$loc['id'];
            $hasWell = ($wellCounts[$locId] ?? 0) > 0;
        ?>
        <article class="list-row list-row--loc <?= !$loc['available'] ? 'row-disabled' : '' ?>" role="row">
            <span class="muted"><?= $locId ?></span>
            <span><?= htmlspecialchars($loc['name']) ?></span>
            <span style="color:<?= htmlspecialchars($loc['color_hex']) ?>"><?= htmlspecialchars($loc['region_name']) ?></span>
            <span class="muted"><?= htmlspecialchars($loc['country_code']) ?></span>
            <span class="muted"><?= $loc['well_type'] === 'offshore' ? '' : '' ?></span>
            <span><?= number_format((float)$loc['oil_richness'], 1) ?></span>
            <span><?= $hasWell ? '<span class="orange">' . (int)$wellCounts[$locId] . '</span>' : '<span class="muted"></span>' ?></span>
            <span>
                <?php if ($loc['available']): ?>
                <span class="badge badge-active"><?= t('admin.map.status_active') ?></span>
                <?php else: ?>
                <span class="badge badge-inactive"><?= t('admin.map.status_inactive') ?></span>
                <?php endif ?>
            </span>
            <span class="action-btns">
                <form method="post">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="toggle_location">
                    <input type="hidden" name="loc_id" value="<?= $locId ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $loc['available'] ? t('admin.map.btn_disable') : t('admin.map.btn_enable') ?></button>
                </form>
                <button type="button" class="btn btn-success btn-sm"
                    onclick="openEdit(<?= htmlspecialchars(json_encode($loc), ENT_QUOTES) ?>)"><?= t('common.edit') ?></button>
                <?php if (!$hasWell): ?>
                <form method="post"
                      onsubmit="return confirm('<?= t('admin.map.delete_confirm') ?>: <?= htmlspecialchars($loc['name'], ENT_QUOTES) ?>?')">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="delete_location">
                    <input type="hidden" name="loc_id" value="<?= $locId ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><?= t('common.delete') ?></button>
                </form>
                <?php else: ?>
                <span class="muted loc-has-well" title="<?= t('admin.map.has_well_title') ?>"></span>
                <?php endif ?>
            </span>
        </article>
        <?php endforeach ?>
    </div>

    <?php if ($totalLocPgs > 1): ?>
    <nav class="pagination" aria-label="Paginacja lokalizacji">
        <?php if ($currentLocPg > 1): ?>
        <a href="?page=<?= $currentLocPg - 1 ?>" class="btn btn-secondary btn-sm"><?= t('common.prev') ?></a>
        <?php endif ?>
        <?php for ($p = 1; $p <= $totalLocPgs; $p++): ?>
        <a href="?page=<?= $p ?>"
           class="btn btn-sm <?= $p === $currentLocPg ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
        <?php endfor ?>
        <?php if ($currentLocPg < $totalLocPgs): ?>
        <a href="?page=<?= $currentLocPg + 1 ?>" class="btn btn-secondary btn-sm"><?= t('common.next') ?></a>
        <?php endif ?>
        <span class="muted pagination-info">
            <?= $locOffset + 1 ?><?= min($locOffset + $locsPerPage, $totalLocs) ?> <?= t('common.of') ?> <?= $totalLocs ?>
        </span>
    </nav>
    <?php endif ?>
</section>

<!-- Modal edycji -->
<div id="edit-modal" class="modal-overlay" hidden>
    <div class="modal-box">
        <h2 class="modal-title"><?= t('admin.map.edit_title') ?></h2>
        <form method="post" id="edit-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="edit_location">
            <input type="hidden" name="loc_id" id="edit-loc-id">
            <div class="form-group">
                <label><?= t('admin.map.field_region') ?></label>
                <select name="region_id" id="edit-region-id" class="input-full">
                    <?php foreach ($regions as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group form-group--flex">
                    <label><?= t('admin.map.field_name') ?></label>
                    <input type="text" name="name" id="edit-name" class="input-full" required>
                </div>
                <div class="form-group form-group--country">
                    <label><?= t('admin.map.field_country') ?></label>
                    <input type="text" name="country_code" id="edit-country" class="input-full" maxlength="5" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group form-group--flex">
                    <label><?= t('admin.map.field_lat') ?></label>
                    <input type="number" name="latitude" id="edit-lat" class="input-full" step="any" required>
                </div>
                <div class="form-group form-group--flex">
                    <label><?= t('admin.map.field_lng') ?></label>
                    <input type="number" name="longitude" id="edit-lng" class="input-full" step="any" required>
                </div>
                <div class="form-group self-end">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="openGlobePicker()"> Ustaw na globie</button>
                </div>
                <div class="form-group form-group--richness">
                    <label><?= t('admin.map.field_richness') ?></label>
                    <input type="number" name="oil_richness" id="edit-richness" class="input-full" step="0.1" min="0.5" max="3.0" required>
                </div>
                <div class="form-group form-group--type">
                    <label><?= t('admin.map.field_type') ?></label>
                    <select name="well_type" id="edit-type" class="input-full">
                        <option value="onshore"><?= t('admin.map.type_onshore') ?></option>
                        <option value="offshore"><?= t('admin.map.type_offshore') ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label><?= t('admin.map.field_desc') ?></label>
                <input type="text" name="description" id="edit-desc" class="input-full">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEdit()"><?= t('common.cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
            </div>
        </form>
    </div>
</div>

<!--  Globe picker modal  -->
<div id="globe-picker-modal" class="globe-picker-modal" style="display:none">
    <div class="globe-picker-modal__inner">
        <div class="globe-picker-modal__header">
            <strong class="globe-picker-modal__title"> Kliknij na globie aby ustawi wsprzdne</strong>
            <button onclick="closeGlobePicker()" class="btn-icon-close" title="Zamknij"></button>
        </div>
        <div id="globe-picker-canvas" class="globe-picker-canvas"></div>
        <div id="globe-picker-info" class="globe-picker-info">
            Kliknij dowolne miejsce na kuli ziemskiej
        </div>
        <div class="globe-picker-footer">
            <button id="globe-picker-confirm" onclick="confirmGlobeCoords()" class="btn btn-primary btn-sm" disabled> Zatwierd</button>
            <button onclick="closeGlobePicker()" class="btn btn-secondary btn-sm">Anuluj</button>
        </div>
    </div>
</div>

<!-- Three.js  potrzebny tylko dla globe pickera, nie ma go w globalnym headerze -->
<script src="https://cdn.jsdelivr.net/npm/three@0.134.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.134.0/examples/js/controls/OrbitControls.js"></script>

<script>
// Globe picker 
(function() {
    var gpScene, gpCamera, gpRenderer, gpEarth, gpGroup, gpMarker, gpControls;
    var gpPicked = null;
    var GLOBE_R  = 1.0;
    var ready    = false;

    function latLngToVec3(lat, lng, r) {
        var phi   = (90 - lat)  * Math.PI / 180;
        var theta = (lng + 180) * Math.PI / 180;
        return new THREE.Vector3(
            -r * Math.sin(phi) * Math.cos(theta),
             r * Math.cos(phi),
             r * Math.sin(phi) * Math.sin(theta)
        );
    }

    function vec3ToLatLng(v) {
        var r   = v.length();
        var phi = Math.acos(Math.max(-1, Math.min(1, v.y / r)));
        var lat = 90 - phi * 180 / Math.PI;
        var lng = Math.atan2(v.z, -v.x) * 180 / Math.PI - 180;
        if (lng < -180) lng += 360;
        return { lat: Math.round(lat * 10000) / 10000, lng: Math.round(lng * 10000) / 10000 };
    }

    function initGlobe() {
        var container = document.getElementById('globe-picker-canvas');
        var W = container.clientWidth, H = container.clientHeight || 380;

        gpScene    = new THREE.Scene();
        gpCamera   = new THREE.PerspectiveCamera(60, W / H, 0.1, 100);
        gpCamera.position.z = 3;

        gpRenderer = new THREE.WebGLRenderer({ antialias: true });
        gpRenderer.setSize(W, H);
        gpRenderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        container.innerHTML = '';
        container.appendChild(gpRenderer.domElement);

        gpGroup = new THREE.Group();
        gpScene.add(gpGroup);

        var loader = new THREE.TextureLoader();
        var geo    = new THREE.SphereGeometry(GLOBE_R, 48, 48);
        gpEarth    = new THREE.Mesh(geo, new THREE.MeshPhongMaterial({
            map: loader.load('/textures/00_earthmap8k.jpg'),
        }));
        gpGroup.add(gpEarth);

 // Marker czerwona kulka
        gpMarker = new THREE.Mesh(
            new THREE.SphereGeometry(0.025, 12, 12),
            new THREE.MeshBasicMaterial({ color: 0xff2222 })
        );
        gpMarker.visible = false;
        gpGroup.add(gpMarker);

        gpScene.add(new THREE.AmbientLight(0xffffff, 1.2));
        var sun = new THREE.DirectionalLight(0xffffff, 2);
        sun.position.set(5, 3, 5);
        gpScene.add(sun);

        gpControls = new THREE.OrbitControls(gpCamera, gpRenderer.domElement);
        gpControls.enableDamping  = true;
        gpControls.dampingFactor  = 0.07;
        gpControls.minDistance    = 1.5;
        gpControls.maxDistance    = 6;
        gpControls.enablePan      = false;

 // Klik raycasting
        gpRenderer.domElement.addEventListener('click', function(e) {
            var rect   = gpRenderer.domElement.getBoundingClientRect();
            var mouse  = new THREE.Vector2(
                ((e.clientX - rect.left)  / rect.width)  * 2 - 1,
               -((e.clientY - rect.top)   / rect.height) * 2 + 1
            );
            var ray = new THREE.Raycaster();
            ray.setFromCamera(mouse, gpCamera);
            var hits = ray.intersectObject(gpEarth);
            if (!hits.length) return;

 // Punkt przecicia do lokalnego ukadu grupy (uwzgldnia rotacj)
            var local = gpGroup.worldToLocal(hits[0].point.clone());
            var ll    = vec3ToLatLng(local);
            gpPicked  = ll;

            gpMarker.position.copy(latLngToVec3(ll.lat, ll.lng, GLOBE_R + 0.03));
            gpMarker.visible = true;

            document.getElementById('globe-picker-info').textContent =
                'Lat: ' + ll.lat.toFixed(4) + '  |  Lng: ' + ll.lng.toFixed(4);
            document.getElementById('globe-picker-confirm').disabled = false;
        });

        ready = true;
        animate();
    }

    function animate() {
        if (!ready) return;
        requestAnimationFrame(animate);
        gpControls.update();
        gpRenderer.render(gpScene, gpCamera);
    }

    window.openGlobePicker = function() {
        var modal = document.getElementById('globe-picker-modal');
        modal.style.display = 'flex';
        gpPicked = null;
        document.getElementById('globe-picker-info').textContent = 'Kliknij dowolne miejsce na kuli ziemskiej';
        document.getElementById('globe-picker-confirm').disabled = true;

        if (!ready) {
 // Three.js zaadowany?
            if (typeof THREE === 'undefined') {
                alert('Three.js nie jest zaadowany na tej stronie. Wpisz wsprzdne rcznie.');
                modal.style.display = 'none';
                return;
            }
            initGlobe();
        } else {
            if (gpMarker) gpMarker.visible = false;
 // Poka aktualn pozycj z pl formularza
            var lat = parseFloat(document.getElementById('edit-lat').value);
            var lng = parseFloat(document.getElementById('edit-lng').value);
            if (!isNaN(lat) && !isNaN(lng)) {
                gpMarker.position.copy(latLngToVec3(lat, lng, GLOBE_R + 0.03));
                gpMarker.visible = true;
            }
        }
    };

    window.closeGlobePicker = function() {
        document.getElementById('globe-picker-modal').style.display = 'none';
    };

    window.confirmGlobeCoords = function() {
        if (!gpPicked) return;
        document.getElementById('edit-lat').value = gpPicked.lat;
        document.getElementById('edit-lng').value = gpPicked.lng;
        closeGlobePicker();
    };
})();
</script>

<script src="/assets/js/map_locations.js"></script>
