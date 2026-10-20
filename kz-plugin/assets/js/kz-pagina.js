/**
 * KZ-Pagina — de uitslagen (sjabloon "uitslagen") om door te klikken.
 * De plugin tekent alle jaren en categorieën al op de server (ge-escapet);
 * dit script wisselt alleen welk paneel zichtbaar is. Zonder JavaScript
 * staat het laatste jaar met de eerste categorie open.
 */
(function () {
    'use strict';

    function kies( knoppen, panelen, sleutel, waarde ) {
        knoppen.forEach( function ( k ) {
            k.setAttribute( 'aria-pressed', k.dataset[ sleutel ] === waarde ? 'true' : 'false' );
        } );
        panelen.forEach( function ( p ) {
            p.hidden = p.dataset[ sleutel ] !== waarde;
        } );
    }

    document.addEventListener( 'click', function ( e ) {
        var knop = e.target.closest ? e.target.closest( '.kzp__jaar, .kzp__categorie' ) : null;
        if ( ! knop ) {
            return;
        }
        if ( knop.classList.contains( 'kzp__jaar' ) ) {
            var blok = knop.closest( '[data-kzp-uitslagen]' );
            if ( ! blok ) {
                return;
            }
            kies(
                Array.prototype.slice.call( blok.querySelectorAll( '.kzp__jaar' ) ),
                Array.prototype.slice.call( blok.querySelectorAll( '.kzp__jaar-paneel' ) ),
                'jaar',
                knop.dataset.jaar
            );
        } else {
            var paneel = knop.closest( '.kzp__jaar-paneel' );
            if ( ! paneel ) {
                return;
            }
            kies(
                Array.prototype.slice.call( paneel.querySelectorAll( '.kzp__categorie' ) ),
                Array.prototype.slice.call( paneel.querySelectorAll( '.kzp__uitslag' ) ),
                'categorie',
                knop.dataset.categorie
            );
        }
    } );
})();
