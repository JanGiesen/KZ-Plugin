/**
 * KZ-Evenement Info — blokken die naast elkaar staan even hoog maken.
 * Blokken met (vrijwel) dezelfde bovenkant staan naast elkaar; die krijgen
 * de hoogte van de hoogste, en ook hun rode koppen worden even hoog zodat
 * de balken op één lijn liggen. Onder elkaar (mobiel) gebeurt er niets.
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
            var kop = blok.querySelector('.kz-evt__top');
            if (kop) {
                kop.style.minHeight = '';
            }
        });

        var metingen = blokken.map(function (blok) {
            var rect = blok.getBoundingClientRect();
            return { blok: blok, top: rect.top + window.pageYOffset };
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

            // Eerst de rode koppen even hoog, zodat de balken en datumtickets
            // eronder op dezelfde lijn beginnen; daarna pas de hele blokken.
            var koppen = groep.map(function (m) { return m.blok.querySelector('.kz-evt__top'); }).filter(Boolean);
            var maxKop = Math.max.apply(null, koppen.map(function (kop) { return kop.getBoundingClientRect().height; }));
            koppen.forEach(function (kop) {
                kop.style.minHeight = Math.ceil(maxKop) + 'px';
            });

            var max = Math.max.apply(null, groep.map(function (m) { return m.blok.getBoundingClientRect().height; }));
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
