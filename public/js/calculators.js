/* global immoManager */
( function () {
	'use strict';

	// Public API container — initialisiere leer, damit auch ohne Settings vorhanden.
	window.immoCalculators = window.immoCalculators || {};

	var settings = ( window.immoManager && window.immoManager.calc ) || null;
	if ( ! settings ) { return; }

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function decSep() {
		return ( settings && settings.currency && typeof settings.currency.decimal === 'string' )
			? settings.currency.decimal : ',';
	}

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

	/**
	 * Nebenkosten-Berechnung.
	 */
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

	/**
	 * Annuitätenrechnung mit jährlicher Sondertilgung.
	 */
	function buildAmortization( principal, annualRate, termYears, extraPerYear ) {
		var K = Math.max( 0, principal );
		var n = Math.max( 1, termYears * 12 );
		var i = ( annualRate / 12 ) / 100;
		var m = i === 0 ? K / n : ( K * i ) / ( 1 - Math.pow( 1 + i, -n ) );
		extraPerYear = Math.max( 0, extraPerYear || 0 );

		var rows = [];
		var balance = K;
		var totalInterest = 0;
		var startYear = new Date().getFullYear();

		for ( var year = 1; year <= termYears; year++ ) {
			var yearInterest = 0;
			var yearPrincipal = 0;
			for ( var month = 1; month <= 12; month++ ) {
				if ( balance <= 0.01 ) { break; }
				var interestPart = balance * i;
				var principalPart = m - interestPart;
				if ( principalPart > balance ) { principalPart = balance; }
				balance -= principalPart;
				yearInterest += interestPart;
				yearPrincipal += principalPart;
			}
			if ( extraPerYear > 0 && balance > 0 ) {
				var extra = Math.min( extraPerYear, balance );
				balance -= extra;
				yearPrincipal += extra;
			}
			totalInterest += yearInterest;
			rows.push( {
				year: startYear + year - 1,
				interest: yearInterest,
				principal: yearPrincipal,
				balance: Math.max( 0, balance ),
			} );
			if ( balance <= 0.01 ) { break; }
		}
		return { rate: m, rows: rows, totalInterest: totalInterest, totalPayments: K + totalInterest };
	}

	/**
	 * Render: Nebenkosten-Panel.
	 */
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

	/**
	 * Render: Finanzierungs-Panel.
	 */
	function renderFinancingPanel( calc, costs, financeState ) {
		var need = costs.grandTotal;

		var equityValue;
		if ( financeState.equityMode === 'pct' ) {
			equityValue = ( financeState.price * financeState.equity ) / 100;
		} else {
			equityValue = financeState.equity;
		}
		equityValue = Math.max( 0, equityValue );

		var loan = Math.max( 0, need - equityValue );

		var needEl = $( '.immo-calc-need', calc );
		if ( needEl ) { needEl.textContent = formatMoney( need ); }
		var equityEurEl = $( '.immo-calc-equity-eur', calc );
		if ( equityEurEl ) { equityEurEl.textContent = formatMoney( equityValue ); }
		var loanEl = $( '.immo-calc-loan', calc );
		if ( loanEl ) { loanEl.textContent = formatMoney( loan ); }

		var resultsEl = $( '.immo-calc-results', calc );
		var tableWrap = $( '.immo-amortization-table-wrap', calc );
		var tableToggle = $( '.immo-amortization-toggle', calc );

		if ( loan <= 0.5 ) {
			if ( resultsEl ) {
				resultsEl.innerHTML = '<div class="immo-calc-no-financing">' +
					( ( settings.i18n && settings.i18n.noFinancing ) || 'Keine Finanzierung notwendig.' ) +
					'</div>';
			}
			if ( tableToggle ) { tableToggle.style.display = 'none'; }
			if ( tableWrap ) { tableWrap.hidden = true; }
			return;
		}

		var amort = buildAmortization( loan, financeState.interest, financeState.term, financeState.extra );
		if ( resultsEl ) {
			resultsEl.innerHTML =
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Monatliche Rate</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( amort.rate ) + '</span></div>' +
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Gesamt-Zinsen</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( amort.totalInterest ) + '</span></div>' +
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Gesamtaufwand</span>' +
					'<span class="immo-calc-row-amount">' + formatMoney( amort.totalPayments ) + '</span></div>' +
				'<div class="immo-calc-row"><span class="immo-calc-row-label">Tilgung-Ende</span>' +
					'<span class="immo-calc-row-amount">' + ( amort.rows.length ? amort.rows[ amort.rows.length - 1 ].year : '–' ) + '</span></div>';
		}

		if ( tableToggle ) {
			tableToggle.style.display = '';
			tableToggle.setAttribute( 'aria-expanded', 'false' );
		}
		if ( tableWrap ) { tableWrap.hidden = true; }
		var tbody = $( '.immo-amortization-rows', calc );
		if ( tbody ) {
			tbody.innerHTML = amort.rows.map( function ( r ) {
				return '<tr>' +
					'<td>' + r.year + '</td>' +
					'<td>' + formatMoney( r.interest ) + '</td>' +
					'<td>' + formatMoney( r.principal ) + '</td>' +
					'<td>' + formatMoney( r.balance ) + '</td>' +
					'</tr>';
			} ).join( '' );
		}
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
				? ( ( settings.i18n && settings.i18n.hideTable ) || 'Tilgungsplan ausblenden' )
				: ( ( settings.i18n && settings.i18n.showTable ) || 'Tilgungsplan anzeigen' ) );
		} );
	}

	/**
	 * Initialisiere einen einzelnen Calculator-Container.
	 *
	 * Modus wird über das `data-mode`-Attribut bestimmt: 'costs' oder 'financing'.
	 */
	function initCalculator( calc ) {
		var mode = calc.getAttribute( 'data-mode' ) || 'costs';

		var state = {
			price: parseFloat( calc.getAttribute( 'data-base-price' ) ) || 0,
			commissionFree: calc.getAttribute( 'data-commission-free' ) === '1',
		};

		var finCfg = settings.finance || {};
		function readNum( el, def, isInt ) {
			if ( ! el ) { return def; }
			var raw = el.value;
			var p = isInt ? parseInt( raw, 10 ) : parseFloat( raw );
			return isNaN( p ) ? def : p;
		}

		var finance = null;
		if ( mode === 'financing' ) {
			finance = {
				price:      state.price,
				equity:     readNum( $( '.immo-calc-equity', calc ),   ( finCfg.equityPct    || 20 ),  false ),
				equityMode: 'pct',
				interest:   readNum( $( '.immo-calc-interest', calc ), ( finCfg.interestRate || 3.5 ), false ),
				term:       readNum( $( '.immo-calc-term', calc ),     ( finCfg.termYears    || 25 ),  true ),
				extra:      readNum( $( '.immo-calc-extra', calc ),    ( finCfg.extraPayment || 0 ),   false ),
			};
		}

		initAmortizationToggle( calc );

		// Equity-Mode-Toggle (nur Finanzierung).
		if ( mode === 'financing' ) {
			$$( '.immo-calc-equity-toggle button', calc ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					$$( '.immo-calc-equity-toggle button', calc ).forEach( function ( b ) {
						b.classList.toggle( 'active', b === btn );
					} );
					finance.equityMode = btn.getAttribute( 'data-mode' );
					render();
				} );
			} );
		}

		// Input-Bindings.
		var bindings = {
			'.immo-calc-price': function ( v ) {
				state.price = Math.max( 0, parseFloat( v ) || 0 );
				if ( finance ) { finance.price = state.price; }
			},
		};

		if ( mode === 'financing' && finance ) {
			bindings['.immo-calc-equity']   = function ( v ) { finance.equity   = Math.max( 0, parseFloat( v ) || 0 ); };
			bindings['.immo-calc-interest'] = function ( v ) { finance.interest = Math.max( 0, Math.min( 20, parseFloat( v ) || 0 ) ); };
			bindings['.immo-calc-term']     = function ( v ) { finance.term     = Math.max( 1, Math.min( 50, parseInt( v, 10 ) || 1 ) ); };
			bindings['.immo-calc-extra']    = function ( v ) { finance.extra    = Math.max( 0, parseFloat( v ) || 0 ); };
		}

		Object.keys( bindings ).forEach( function ( sel ) {
			var input = $( sel, calc );
			if ( input ) {
				input.addEventListener( 'input', function () {
					bindings[ sel ]( input.value );
					render();
				} );
			}
		} );

		// Unit-Dropdown.
		var unitSelect = $( '.immo-calc-unit-select', calc );
		if ( unitSelect ) {
			unitSelect.addEventListener( 'change', function () {
				var opt = unitSelect.options[ unitSelect.selectedIndex ];
				if ( ! opt ) { return; }
				var newPrice = parseFloat( opt.getAttribute( 'data-price' ) ) || 0;
				state.price = newPrice;
				if ( finance ) { finance.price = newPrice; }
				state.commissionFree = opt.getAttribute( 'data-commission-free' ) === '1';
				var priceInput = $( '.immo-calc-price', calc );
				if ( priceInput ) { priceInput.value = String( Math.round( newPrice ) ); }
				render();
			} );
		}

		function render() {
			if ( mode === 'costs' ) {
				renderCostsPanel( calc, state.price, state.commissionFree );
			} else {
				// Finanzierung: Nebenkosten intern berechnen, um Finanzierungsbedarf zu ermitteln.
				var costs = calcCosts( state.price, state.commissionFree );
				renderFinancingPanel( calc, costs, finance );
			}
		}
		render();

		calc._state = state;
		calc._finance = finance;
		calc._render = render;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		$$( '.immo-calculator' ).forEach( initCalculator );
	} );

	// Public API erweitern.
	window.immoCalculators.formatMoney = formatMoney;
	window.immoCalculators.calcCosts = calcCosts;
	window.immoCalculators.buildAmortization = buildAmortization;
} )();
