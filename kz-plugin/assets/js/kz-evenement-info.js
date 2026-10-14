/**
 * KZ-Evenement Info — blokken die naast elkaar staan even hoog maken.
 * Blokken met (vrijwel) dezelfde bovenkant staan naast elkaar; die krijgen
 * de hoogte van de hoogste. Onder elkaar (mobiel) gebeurt er niets.
 */
(function () {
    'use strict';

    function gelijkTrekken() {
        var blokken = Array.prototype.slice.call(document.querySelectorAll('.kz-evt'));
        if (blokken.length < 2) {
            return;
        }

        blokken.forEach(function (blok) {
            blok.style.minHeight = '';
        });

        var metingen = blokken.map(function (blok) {
            var rect = blok.getBoundingClientRect();
            return { blok: blok, top: rect.top + window.pageYOffset, hoogte: rect.height };
        }).sort(function (a, b) {
            return a.top - b.top;
        });

        var groepen = [];
        metingen.forEach(function (meting) {
            var laatste = groepen[groepen.length - 1];
            if (laatste && Math.abs(laatste[0].top - meting.top) <= 4) {
                laatste.push(meting);
            } else {
                groepen.push([meting]);
            }
        });

        groepen.forEach(function (groep) {
            if (groep.length < 2) {
                return;
            }
            var max = Math.max.apply(null, groep.map(function (m) { return m.hoogte; }));
            groep.forEach(function (m) {
                m.blok.style.minHeight = Math.ceil(max) + 'px';
            });
        });
    }

    var timer;
    function plan() {
        clearTimeout(timer);
        timer = setTimeout(gelijkTrekken, 100);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', gelijkTrekken);
    } else {
        gelijkTrekken();
    }
    window.addEventListener('load', gelijkTrekken);
    window.addEventListener('resize', plan);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(gelijkTrekken);
    }
})();
