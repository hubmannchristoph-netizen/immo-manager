/* global immoManager */
( function () {
	'use strict';

	var settings = ( window.immoManager && window.immoManager.calc ) || null;
	if ( ! settings ) { return; }

	function formatMoney( amount ) {
		var c = settings.currency;
		var dec = parseInt( c.decimals, 10 ) || 0;
		var fixed = Math.round( amount * Math.pow( 10, dec ) ) / Math.pow( 10, dec );
		var parts = fixed.toFixed( dec ).split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, c.thousands );
		var num = parts.join( c.decimal );
		return c.position === 'before' ? c.symbol + ' ' + num : num + ' ' + c.symbol;
	}

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function initTabs( calc ) {
		var tabs = $$( '.immo-calculator-tab', calc );
		var panels = $$( '.immo-calculator-panel', calc );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var target = tab.getAttribute( 'data-tab' );
				tabs.forEach( function ( t ) {
					var active = t === tab;
					t.classList.toggle( 'active', active );
					t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );
				panels.forEach( function ( p ) {
					p.hidden = p.getAttribute( 'data-panel' ) !== target;
				} );
			} );
		} );
	}

	function initAmortizationToggle( calc ) {
		var toggle = $( '.immo-amortization-toggle', calc );
		var wrap   = $( '.immo-amortization-table-wrap', calc );
		if ( ! toggle || ! wrap ) { return; }
		toggle.addEventListener( 'click', function () {
			var open = wrap.hidden;
			wrap.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggle.textContent = ( open ? '▲ ' : '▼ ' ) + ( open
				? ( settings.i18n && settings.i18n.hideTable )  || 'Tilgungsplan ausblenden'
				: ( settings.i18n && settings.i18n.showTable ) || 'Tilgungsplan anzeigen' );
		} );
	}

	function initCalculator( calc ) {
		initTabs( calc );
		initAmortizationToggle( calc );
		// Berechnung wird in Phase 4/5 ergänzt.
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		$$( '.immo-calculator' ).forEach( initCalculator );
	} );

	// Public API für Tests.
	window.immoCalculators = {
		formatMoney: formatMoney,
	};
} )();
