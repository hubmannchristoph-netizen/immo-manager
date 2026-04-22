<?php
/**
 * Österreichische Bundesländer und Bezirke.
 *
 * @package ImmoManager
 */

namespace ImmoManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Regions
 *
 * Statische Referenzdaten für Dropdowns (Bundesland → Bezirk).
 * Keys werden in Post-Meta gespeichert, Labels werden lokalisiert ausgegeben.
 */
class Regions {

	/**
	 * Alle Bundesländer (Keys sind stabil, Labels kommen aus der Sprache).
	 *
	 * @return array<string, string>
	 */
	public static function get_states(): array {
		return array(
			'burgenland'        => __( 'Burgenland', 'immo-manager' ),
			'kaernten'          => __( 'Kärnten', 'immo-manager' ),
			'niederoesterreich' => __( 'Niederösterreich', 'immo-manager' ),
			'oberoesterreich'   => __( 'Oberösterreich', 'immo-manager' ),
			'salzburg'          => __( 'Salzburg', 'immo-manager' ),
			'steiermark'        => __( 'Steiermark', 'immo-manager' ),
			'tirol'             => __( 'Tirol', 'immo-manager' ),
			'vorarlberg'        => __( 'Vorarlberg', 'immo-manager' ),
			'wien'              => __( 'Wien', 'immo-manager' ),
		);
	}

	/**
	 * Bezirke eines Bundeslandes.
	 *
	 * @param string $state Bundesland-Key.
	 *
	 * @return array<string, string> Key => Label.
	 */
	public static function get_districts( string $state ): array {
		$all = self::get_all_districts();
		return $all[ $state ] ?? array();
	}

	/**
	 * Alle Bezirke pro Bundesland.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_all_districts(): array {
		return array(
			'burgenland'        => array(
				'eisenstadt'           => 'Eisenstadt (Stadt)',
				'rust'                 => 'Rust (Stadt)',
				'eisenstadt_umgebung'  => 'Eisenstadt-Umgebung',
				'guessing'             => 'Güssing',
				'jennersdorf'          => 'Jennersdorf',
				'mattersburg'          => 'Mattersburg',
				'neusiedl_am_see'      => 'Neusiedl am See',
				'oberpullendorf'       => 'Oberpullendorf',
				'oberwart'             => 'Oberwart',
			),
			'kaernten'          => array(
				'klagenfurt_stadt'     => 'Klagenfurt (Stadt)',
				'villach_stadt'        => 'Villach (Stadt)',
				'feldkirchen'          => 'Feldkirchen',
				'hermagor'             => 'Hermagor',
				'klagenfurt_land'      => 'Klagenfurt-Land',
				'sankt_veit'           => 'Sankt Veit an der Glan',
				'spittal'              => 'Spittal an der Drau',
				'villach_land'         => 'Villach-Land',
				'voelkermarkt'         => 'Völkermarkt',
				'wolfsberg'            => 'Wolfsberg',
			),
			'niederoesterreich' => array(
				'krems_stadt'          => 'Krems (Stadt)',
				'sankt_poelten_stadt'  => 'St. Pölten (Stadt)',
				'waidhofen_stadt'      => 'Waidhofen an der Ybbs (Stadt)',
				'wiener_neustadt_stadt' => 'Wiener Neustadt (Stadt)',
				'amstetten'            => 'Amstetten',
				'baden'                => 'Baden',
				'bruck_an_der_leitha'  => 'Bruck an der Leitha',
				'gaenserndorf'         => 'Gänserndorf',
				'gmuend'               => 'Gmünd',
				'hollabrunn'           => 'Hollabrunn',
				'horn'                 => 'Horn',
				'korneuburg'           => 'Korneuburg',
				'krems_land'           => 'Krems-Land',
				'lilienfeld'           => 'Lilienfeld',
				'melk'                 => 'Melk',
				'mistelbach'           => 'Mistelbach',
				'moedling'             => 'Mödling',
				'neunkirchen'          => 'Neunkirchen',
				'sankt_poelten_land'   => 'St. Pölten-Land',
				'scheibbs'             => 'Scheibbs',
				'tulln'                => 'Tulln',
				'waidhofen_land'       => 'Waidhofen an der Thaya',
				'wiener_neustadt_land' => 'Wiener Neustadt-Land',
				'zwettl'               => 'Zwettl',
			),
			'oberoesterreich'   => array(
				'linz_stadt'           => 'Linz (Stadt)',
				'steyr_stadt'          => 'Steyr (Stadt)',
				'wels_stadt'           => 'Wels (Stadt)',
				'braunau'              => 'Braunau am Inn',
				'eferding'             => 'Eferding',
				'freistadt'            => 'Freistadt',
				'gmunden'              => 'Gmunden',
				'grieskirchen'         => 'Grieskirchen',
				'kirchdorf'            => 'Kirchdorf an der Krems',
				'linz_land'            => 'Linz-Land',
				'perg'                 => 'Perg',
				'ried'                 => 'Ried im Innkreis',
				'rohrbach'             => 'Rohrbach',
				'schaerding'           => 'Schärding',
				'steyr_land'           => 'Steyr-Land',
				'urfahr_umgebung'      => 'Urfahr-Umgebung',
				'voecklabruck'         => 'Vöcklabruck',
				'wels_land'            => 'Wels-Land',
			),
			'salzburg'          => array(
				'salzburg_stadt'       => 'Salzburg (Stadt)',
				'hallein'              => 'Hallein',
				'salzburg_umgebung'    => 'Salzburg-Umgebung',
				'sankt_johann'         => 'St. Johann im Pongau',
				'tamsweg'              => 'Tamsweg',
				'zell_am_see'          => 'Zell am See',
			),
			'steiermark'        => array(
				'graz_stadt'           => 'Graz (Stadt)',
				'bruck_muerzzuschlag'  => 'Bruck-Mürzzuschlag',
				'deutschlandsberg'     => 'Deutschlandsberg',
				'graz_umgebung'        => 'Graz-Umgebung',
				'hartberg_fuerstenfeld' => 'Hartberg-Fürstenfeld',
				'leibnitz'             => 'Leibnitz',
				'leoben'               => 'Leoben',
				'liezen'               => 'Liezen',
				'murau'                => 'Murau',
				'murtal'               => 'Murtal',
				'suedoststeiermark'    => 'Südoststeiermark',
				'voitsberg'            => 'Voitsberg',
				'weiz'                 => 'Weiz',
			),
			'tirol'             => array(
				'innsbruck_stadt'      => 'Innsbruck (Stadt)',
				'imst'                 => 'Imst',
				'innsbruck_land'       => 'Innsbruck-Land',
				'kitzbuehel'           => 'Kitzbühel',
				'kufstein'             => 'Kufstein',
				'landeck'              => 'Landeck',
				'lienz'                => 'Lienz',
				'reutte'               => 'Reutte',
				'schwaz'               => 'Schwaz',
			),
			'vorarlberg'        => array(
				'bludenz'              => 'Bludenz',
				'bregenz'              => 'Bregenz',
				'dornbirn'             => 'Dornbirn',
				'feldkirch'            => 'Feldkirch',
			),
			'wien'              => array(
				'1010' => '1010 – Innere Stadt',
				'1020' => '1020 – Leopoldstadt',
				'1030' => '1030 – Landstraße',
				'1040' => '1040 – Wieden',
				'1050' => '1050 – Margareten',
				'1060' => '1060 – Mariahilf',
				'1070' => '1070 – Neubau',
				'1080' => '1080 – Josefstadt',
				'1090' => '1090 – Alsergrund',
				'1100' => '1100 – Favoriten',
				'1110' => '1110 – Simmering',
				'1120' => '1120 – Meidling',
				'1130' => '1130 – Hietzing',
				'1140' => '1140 – Penzing',
				'1150' => '1150 – Rudolfsheim-Fünfhaus',
				'1160' => '1160 – Ottakring',
				'1170' => '1170 – Hernals',
				'1180' => '1180 – Währing',
				'1190' => '1190 – Döbling',
				'1200' => '1200 – Brigittenau',
				'1210' => '1210 – Floridsdorf',
				'1220' => '1220 – Donaustadt',
				'1230' => '1230 – Liesing',
			),
		);
	}

	/**
	 * Label eines Bundeslands abrufen.
	 *
	 * @param string $state_key Key.
	 *
	 * @return string Label oder leerer String.
	 */
	public static function get_state_label( string $state_key ): string {
		$states = self::get_states();
		return $states[ $state_key ] ?? '';
	}

	/**
	 * Label eines Bezirks abrufen.
	 *
	 * @param string $state_key    Bundesland-Key.
	 * @param string $district_key Bezirks-Key.
	 *
	 * @return string Label oder leerer String.
	 */
	public static function get_district_label( string $state_key, string $district_key ): string {
		$districts = self::get_districts( $state_key );
		return $districts[ $district_key ] ?? '';
	}

	/**
	 * Prüft, ob ein Bundesland-Key gültig ist.
	 *
	 * @param string $state_key Key.
	 *
	 * @return bool
	 */
	public static function is_valid_state( string $state_key ): bool {
		return array_key_exists( $state_key, self::get_states() );
	}

	/**
	 * Prüft, ob eine Bezirks-Kombination gültig ist.
	 *
	 * @param string $state_key    Bundesland-Key.
	 * @param string $district_key Bezirks-Key.
	 *
	 * @return bool
	 */
	public static function is_valid_district( string $state_key, string $district_key ): bool {
		return array_key_exists( $district_key, self::get_districts( $state_key ) );
	}
}
