/* global immoManager */
( function () {
	'use strict';

	// Public API container — initialisiere leer, damit auch ohne Settings vorhanden.
	window.immoCalculators = window.immoCalculators || {};

	var settings = ( window.immoManager && window.immoManager.calc ) || null;
	if ( ! settings ) { return; }

	function formatMoney( amount ) {
		var c = ( settings && settings.currency ) || {};
		var dec = parseInt( c.decimals, 10 ) || 0;
		var thousands = typeof c.thousands === 'string' ? c.thousands : '.';
		var decimal   = decSep();
		var symbol    = c.symbol || '€';
		var position  = c.position || 'after';
		var fixed = Math.round( amount * Math.pow( 10, dec ) ) / Math.pow( 10, dec );
		var parts = fixed.toFixed( dec ).split( '.' );
		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousands );
		var num = parts.join( decimal );
		return position === 'before' ? symbol + ' ' + num : num + ' ' + symbol;
	}

	function decSep() {
		return ( settings && settings.currency && typeof settings.currency.decimal === 'string' )
			? settings.currency.decimal : ',';
	}

	function calcCosts( price, commissionFree ) {
		var items = [];
		var r = settings.rates;
		var s = settings.show;
		price = Math.max( 0, price || 0 );

		if ( s.grest ) {
			items.push( {
				key: 'grest',
				label: 'Grunderwerbsteuer',
				rate: r.grest,
				amount: price * r.grest / 100,
			} );
		}
		if ( s.grundbuch ) {
			items.push( {
				key: 'grundbuch',
				label: 'Grundbucheintragung',
				rate: r.grundbuch,
				amount: price * r.grundbuch / 100,
			} );
		}
		if ( s.notar ) {
			var notarFlat = settings.notar.mode === 'flat';
			var notarAmount = notarFlat ? settings.notar.flat : ( price * r.notar / 100 );
			items.push( {
				key: 'notar',
				label: 'Notar/Treuhand',
				rate: notarFlat ? null : r.notar,
				amount: notarAmount,
			} );
		}
		if ( s.provision && ! commissionFree ) {
			var provision = price * r.provision / 100;
			items.push( {
				key: 'provision',
				label: 'Maklerprovision',
				rate: r.provision,
				amount: provision,
			} );
			if ( s.ust ) {
				items.push( {
					key: 'ust',
					label: 'USt auf Provision',
					rate: r.ust,
					amount: provision * r.ust / 100,
				} );
			}
		}

		var total = items.reduce( function ( a, i ) { return a + i.amount; }, 0 );
		return { items: items, total: total, grandTotal: price + total };
	}

	function renderCostsPanel( calc, price, commissionFree ) {
		var data = calcCosts( price, commissionFree );
		var itemsEl = $( '.immo-calc-items', calc );
		if ( itemsEl ) {
			itemsEl.innerHTML = data.items.map( function ( item ) {
				var rate = item.rate !== null && item.rate !== undefined
					? '<span class="immo-calc-row-rate">' + item.rate.toFixed( 1 ).replace( '.', decSep() ) + ' %</span>'
					: '';
				return '<div class="immo-calc-row">' +
					'<span class="immo-calc-row-label">' + item.label + rate + '</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( item.amount ) + '</span>' +
					'</div>';
			} ).join( '' );
		}
		var totalEl = $( '.immo-calc-costs-total', calc );
		if ( totalEl ) { totalEl.textContent = formatMoney( data.total ); }
		var grandEl = $( '.immo-calc-grand-total-value', calc );
		if ( grandEl ) { grandEl.textContent = formatMoney( data.grandTotal ); }
		return data;
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
		var state = {
			price: parseFloat( calc.getAttribute( 'data-base-price' ) ) || 0,
			commissionFree: calc.getAttribute( 'data-commission-free' ) === '1',
		};

		initTabs( calc );
		initAmortizationToggle( calc );

		var priceInput = $( '#immo-calc-price', calc );
		if ( priceInput ) {
			priceInput.addEventListener( 'input', function () {
				state.price = Math.max( 0, parseFloat( priceInput.value ) || 0 );
				render();
			} );
		}

		function render() {
			renderCostsPanel( calc, state.price, state.commissionFree );
			// renderFinancingPanel kommt in Phase 5
		}
		render();

		calc._state = state;
		calc._render = render;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		$$( '.immo-calculator' ).forEach( initCalculator );
	} );

	// Public API erweitern.
	window.immoCalculators.formatMoney = formatMoney;
	window.immoCalculators.calcCosts = calcCosts;
} )();
