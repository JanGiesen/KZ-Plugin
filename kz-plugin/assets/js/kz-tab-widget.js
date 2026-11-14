/**
 * KZ-Tab Widget — front-end gedrag.
 * Elke widget-instantie levert zijn data via een JSON <script
 * type="application/json"> tag met id "{widget_id}-data" (zie
 * class-kz-tab-widget.php), zodat er geen inline JavaScript nodig is.
 *
 * Alles gaat via textContent, nooit via innerHTML: de namen en punten komen
 * uit de shortcode, en die kan elke redacteur invullen.
 */
(function () {
    'use strict';

    function maakKnop( tekst, kies ) {
        var btn = document.createElement( 'button' );
        btn.type = 'button';
        btn.textContent = tekst;
        btn.setAttribute( 'aria-pressed', 'false' );
        btn.addEventListener( 'click', kies );
        return btn;
    }

    function markeer( houder, actief ) {
        Array.prototype.forEach.call( houder.children, function ( btn ) {
            var aan = btn.dataset.waarde === actief;
            btn.classList.toggle( 'active', aan );
            btn.setAttribute( 'aria-pressed', aan ? 'true' : 'false' );
        } );
    }

    function initWidget( dataScript ) {
        var widgetId = dataScript.id.replace( /-data$/, '' );
        var data;
        try {
            data = JSON.parse( dataScript.textContent );
        } catch ( e ) {
            return;
        }
        if ( ! data || typeof data !== 'object' ) {
            return;
        }

        var years = Object.keys( data );
        if ( years.length === 0 ) {
            return;
        }

        var yearTabs     = document.getElementById( widgetId + '-year-tabs' );
        var categoryTabs = document.getElementById( widgetId + '-category-tabs' );
        var resultsDiv   = document.getElementById( widgetId + '-results' );

        if ( ! yearTabs || ! categoryTabs || ! resultsDiv ) {
            return;
        }

        var activeYear = years[ years.length - 1 ];
        var activeCategory = Object.keys( data[ activeYear ] || {} )[ 0 ];

        // Jaarknoppen één keer bouwen; bij een klik alleen de markering wisselen,
        // zodat de toetsenbordfocus op de knop blijft staan.
        years.forEach( function ( year ) {
            var btn = maakKnop( year, function () {
                activeYear = year;
                activeCategory = Object.keys( data[ year ] || {} )[ 0 ];
                markeer( yearTabs, activeYear );
                bouwCategorieen();
                renderContent();
            } );
            btn.dataset.waarde = year;
            yearTabs.appendChild( btn );
        } );

        // Categorieën verschillen per jaar, dus die bouwen we per jaar opnieuw.
        function bouwCategorieen() {
            categoryTabs.textContent = '';
            Object.keys( data[ activeYear ] || {} ).forEach( function ( cat ) {
                var btn = maakKnop( cat.toUpperCase(), function () {
                    activeCategory = cat;
                    markeer( categoryTabs, activeCategory );
                    renderContent();
                } );
                btn.dataset.waarde = cat;
                categoryTabs.appendChild( btn );
            } );
            markeer( categoryTabs, activeCategory );
        }

        function renderContent() {
            var lijst = ( data[ activeYear ] || {} )[ activeCategory ];
            resultsDiv.textContent = '';
            if ( ! Array.isArray( lijst ) || lijst.length === 0 ) {
                var leeg = document.createElement( 'p' );
                leeg.textContent = 'Geen data beschikbaar voor deze categorie.';
                resultsDiv.appendChild( leeg );
                return;
            }
            lijst.forEach( function ( item, i ) {
                var rij = document.createElement( 'div' );
                rij.className = 'row';
                var naam = document.createElement( 'div' );
                naam.textContent = ( i + 1 ) + '. ' + String( item && item.naam != null ? item.naam : '' );
                var punten = document.createElement( 'div' );
                punten.textContent = String( item && item.punten != null ? item.punten : '' );
                rij.appendChild( naam );
                rij.appendChild( punten );
                resultsDiv.appendChild( rij );
            } );
        }

        markeer( yearTabs, activeYear );
        bouwCategorieen();
        renderContent();
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        document.querySelectorAll( 'script[type="application/json"][id$="-data"]' ).forEach( function ( el ) {
            if ( el.dataset && el.dataset.kzTabWidget === '1' ) {
                initWidget( el );
            }
        } );
    } );
})();
