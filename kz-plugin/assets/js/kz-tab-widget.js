/**
 * KZ-Tab Widget — front-end gedrag.
 * Verplaatst uit een inline <script> in de shortcode-output naar dit externe
 * bestand. Elke widget-instantie levert zijn data via een JSON <script
 * type="application/json"> tag met id "{widget_id}-data" (zie
 * class-kz-tab-widget.php), zodat er geen inline JavaScript meer nodig is.
 */
(function () {
    'use strict';

    function initWidget( dataScript ) {
        var widgetId = dataScript.id.replace( /-data$/, '' );
        var data;
        try {
            data = JSON.parse( dataScript.textContent );
        } catch ( e ) {
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
        var activeCategory = Object.keys( data[ activeYear ] )[ 0 ];

        function renderTabs() {
            yearTabs.innerHTML = '';
            years.forEach( function ( year ) {
                var btn = document.createElement( 'button' );
                btn.textContent = year;
                btn.classList.toggle( 'active', year === activeYear );
                btn.onclick = function () {
                    activeYear = year;
                    activeCategory = Object.keys( data[ year ] )[ 0 ];
                    renderTabs();
                    renderContent();
                };
                yearTabs.appendChild( btn );
            } );

            categoryTabs.innerHTML = '';
            Object.keys( data[ activeYear ] ).forEach( function ( cat ) {
                var btn = document.createElement( 'button' );
                btn.textContent = cat.toUpperCase();
                btn.classList.toggle( 'active', cat === activeCategory );
                btn.onclick = function () {
                    activeCategory = cat;
                    renderTabs();
                    renderContent();
                };
                categoryTabs.appendChild( btn );
            } );
        }

        function renderContent() {
            var lijst = data[ activeYear ][ activeCategory ];
            if ( ! lijst || lijst.length === 0 ) {
                resultsDiv.innerHTML = '<p>Geen data beschikbaar voor deze categorie.</p>';
                return;
            }
            var html = lijst
                .map( function ( item, i ) {
                    return '<div class="row"><div>' + ( i + 1 ) + '. ' + item.naam + '</div><div>' + item.punten + '</div></div>';
                } )
                .join( '' );
            resultsDiv.innerHTML = html;
        }

        renderTabs();
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
