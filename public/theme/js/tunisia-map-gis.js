(function () {
    function zoneForGovernorate(name) {
        var north = new Set([
            'Tunis', 'Ariana', 'Ben Arous', 'Manouba', 'Nabeul', 'Zaghouan',
            'Bizerte', 'Beja', 'Jendouba', 'El Kef', 'Siliana'
        ]);
        var center = new Set([
            'Sousse', 'Monastir', 'Mahdia', 'Sfax', 'Kairouan', 'Kasserine', 'Sidi Bou Zid'
        ]);
        if (north.has(name)) {
            return 'north';
        }
        if (center.has(name)) {
            return 'center';
        }
        return 'south';
    }

    function flattenCoordinates(geometry) {
        if (!geometry) {
            return [];
        }
        if (geometry.type === 'Polygon') {
            return [geometry.coordinates];
        }
        if (geometry.type === 'MultiPolygon') {
            return geometry.coordinates;
        }
        return [];
    }

    function computeBounds(features) {
        var minLon = Infinity;
        var maxLon = -Infinity;
        var minLat = Infinity;
        var maxLat = -Infinity;

        features.forEach(function (feature) {
            flattenCoordinates(feature.geometry).forEach(function (polygon) {
                polygon.forEach(function (ring) {
                    ring.forEach(function (pt) {
                        var lon = pt[0];
                        var lat = pt[1];
                        if (lon < minLon) minLon = lon;
                        if (lon > maxLon) maxLon = lon;
                        if (lat < minLat) minLat = lat;
                        if (lat > maxLat) maxLat = lat;
                    });
                });
            });
        });

        return {
            minLon: minLon,
            maxLon: maxLon,
            minLat: minLat,
            maxLat: maxLat
        };
    }

    function createProjector(bounds, width, height, pad) {
        var lonRange = bounds.maxLon - bounds.minLon;
        var latRange = bounds.maxLat - bounds.minLat;
        var usableW = width - 2 * pad;
        var usableH = height - 2 * pad;
        var scale = Math.min(usableW / lonRange, usableH / latRange);
        var xOffset = (usableW - lonRange * scale) / 2;
        var yOffset = (usableH - latRange * scale) / 2;

        return function project(pt) {
            var lon = pt[0];
            var lat = pt[1];
            var x = pad + xOffset + (lon - bounds.minLon) * scale;
            var y = height - (pad + yOffset + (lat - bounds.minLat) * scale);
            return [x, y];
        };
    }

    function polygonToPath(polygon, project) {
        var d = '';
        polygon.forEach(function (ring) {
            if (!ring.length) {
                return;
            }
            var p0 = project(ring[0]);
            d += 'M' + p0[0].toFixed(2) + ' ' + p0[1].toFixed(2);
            for (var i = 1; i < ring.length; i += 1) {
                var p = project(ring[i]);
                d += ' L' + p[0].toFixed(2) + ' ' + p[1].toFixed(2);
            }
            d += ' Z ';
        });
        return d.trim();
    }

    function centroidFromFirstRing(polygon, project) {
        var ring = polygon[0] || [];
        if (!ring.length) {
            return [0, 0];
        }
        var sx = 0;
        var sy = 0;
        for (var i = 0; i < ring.length; i += 1) {
            var p = project(ring[i]);
            sx += p[0];
            sy += p[1];
        }
        return [sx / ring.length, sy / ring.length];
    }

    function buildRoutePath(cityPositions) {
        var order = ['Tunis', 'Sousse', 'Sfax', 'Gabes', 'Tataouine'];
        var points = order.map(function (name) {
            return cityPositions.get(name);
        }).filter(Boolean);

        if (!points.length) {
            return '';
        }

        var d = 'M' + points[0][0].toFixed(2) + ' ' + points[0][1].toFixed(2);
        for (var i = 1; i < points.length; i += 1) {
            d += ' L' + points[i][0].toFixed(2) + ' ' + points[i][1].toFixed(2);
        }
        return d;
    }

    function createCityDot(cityGrid, name, zone, x, y, width, height) {
        var dot = document.createElement('span');
        var major = name === 'Tunis' || name === 'Sousse' || name === 'Sfax' || name === 'Gabes';
        dot.className = 'auth-map-city-dot zone-' + zone + (major ? ' is-major' : '');
        dot.style.left = ((x / width) * 100).toFixed(2) + '%';
        dot.style.top = ((y / height) * 100).toFixed(2) + '%';
        dot.title = name;
        cityGrid.appendChild(dot);
    }

    function createCityTag(mapCanvas, name, x, y, width, height, secondary) {
        var tag = document.createElement('span');
        tag.className = 'auth-map-city-tag' + (secondary ? ' auth-map-city-tag--secondary' : '');
        tag.style.left = ((x / width) * 100).toFixed(2) + '%';
        tag.style.top = ((y / height) * 100).toFixed(2) + '%';
        tag.textContent = name;
        mapCanvas.appendChild(tag);
    }

    function renderMap(container, geojson) {
        var svg = container.querySelector('.auth-map-svg');
        var govLayer = container.querySelector('[data-tn-gov-layer]');
        var cityGrid = container.querySelector('[data-tn-city-grid]');
        var route = container.querySelector('[data-tn-route]');
        if (!svg || !govLayer || !cityGrid || !route || !geojson || !Array.isArray(geojson.features)) {
            return;
        }

        var viewBox = (svg.getAttribute('viewBox') || '0 0 520 680').split(/\s+/).map(Number);
        var width = viewBox[2] || 520;
        var height = viewBox[3] || 680;
        var features = geojson.features;
        var bounds = computeBounds(features);
        var project = createProjector(bounds, width, height, 24);

        govLayer.innerHTML = '';
        cityGrid.innerHTML = '';
        container.querySelectorAll('.auth-map-city-tag').forEach(function (el) { el.remove(); });

        var cityPositions = new Map();

        features.forEach(function (feature, idx) {
            var name = feature && feature.properties ? feature.properties.shapeName : '';
            if (!name) {
                return;
            }
            var zone = zoneForGovernorate(name);
            var polygons = flattenCoordinates(feature.geometry);
            var pathD = '';
            polygons.forEach(function (polygon) {
                pathD += polygonToPath(polygon, project) + ' ';
            });

            if (!pathD.trim()) {
                return;
            }

            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('class', 'auth-gov-zone zone-' + zone);
            path.setAttribute('style', '--gov-delay:' + (idx * 0.08).toFixed(2) + 's;');
            path.setAttribute('d', pathD.trim());
            var title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
            title.textContent = name;
            path.appendChild(title);
            govLayer.appendChild(path);

            var c = centroidFromFirstRing(polygons[0], project);
            cityPositions.set(name, c);
            createCityDot(cityGrid, name, zone, c[0], c[1], width, height);
        });

        route.setAttribute('d', buildRoutePath(cityPositions));

        ['Tunis', 'Sousse', 'Sfax', 'Gabes'].forEach(function (name) {
            var p = cityPositions.get(name);
            if (p) {
                createCityTag(container, name, p[0], p[1], width, height, false);
            }
        });

        ['Tataouine', 'Kebili', 'Tozeur'].forEach(function (name) {
            var p = cityPositions.get(name);
            if (p) {
                createCityTag(container, name, p[0], p[1], width, height, true);
            }
        });
    }

    function initTunisiaMaps() {
        var maps = document.querySelectorAll('[data-tn-map]');
        maps.forEach(function (mapEl) {
            var url = mapEl.getAttribute('data-geojson-url');
            if (!url) {
                return;
            }
            fetch(url)
                .then(function (res) { return res.json(); })
                .then(function (geojson) { renderMap(mapEl, geojson); })
                .catch(function () { /* keep page functional if data fails */ });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTunisiaMaps);
    } else {
        initTunisiaMaps();
    }
})();
