<?php namespace Obie\Formatters;

class EnglishNoun {
	public static $irregular = [
		'aircraft'     => 'aircraft',
		'alumnus'      => 'alumni',
		'analysis'     => 'analyses',
		'appendix'     => 'appendices', // appendixes
		'bacterium'    => 'bacteria',
		'buffalo'      => 'buffalo',
		'cactus'       => 'cacti',
		'calf'         => 'calves',
		'child'        => 'children',
		'crisis'       => 'crises',
		'criterion'    => 'criteria',
		'curriculum'   => 'curricula', // curriculums
		'datum'        => 'data',
		'deer'         => 'deer',
		'die'          => 'dice',
		'equipment'    => 'equipment',
		'fish'         => 'fish',
		'foot'         => 'feet',
		'fungus'       => 'fungi',
		'goose'        => 'geese',
		'hero'         => 'heroes',
		'hippopotamus' => 'hippopotami', // hippopotamuses
		'hovercraft'   => 'hovercraft',
		'index'        => 'indices', // indexes
		'information'  => 'information',
		'knife'        => 'knives',
		'leaf'         => 'leaves',
		'life'         => 'lives',
		'man'          => 'men',
		'memorandum'   => 'memoranda',
		'money'        => 'money',
		'moose'        => 'moose',
		'mouse'        => 'mice',
		'move'         => 'moves',
		'nucleus'      => 'nuclei',
		'octopus'      => 'octopuses', // octopi
		'ocus'         => 'foci', // focuses
		'ox'           => 'oxen',
		'person'       => 'people',
		'phenomenon'   => 'phenomena',
		'potato'       => 'potatoes',
		'radius'       => 'radii', // radiuses
		'rice'         => 'rice',
		'series'       => 'series',
		'sex'          => 'sexes',
		'sheep'        => 'sheep',
		'shrimp'       => 'shrimp',
		'spacecraft'   => 'spacecraft',
		'species'      => 'species',
		'stratum'      => 'strata',
		'swine'        => 'swine',
		'thesis'       => 'theses',
		'tomato'       => 'tomatoes',
		'tooth'        => 'teeth',
		'torpedo'      => 'torpedoes',
		'trout'        => 'trout',
		'valve'        => 'valves',
		'veto'         => 'vetoes',
		'vortex'       => 'vortices', // vortexes
		'watercraft'   => 'watercraft',
		'wife'         => 'wives',
		'woman'        => 'women',
	];

	public static $singular_rules = [
		'/(quiz)zes$/i'                                                    => '$1',
		'/(matr)ices$/i'                                                   => '$1ix',
		'/(vert|ind)ices$/i'                                               => '$1ex',
		'/^(ox)en$/i'                                                      => '$1',
		'/(alias)es$/i'                                                    => '$1',
		'/(octop|vir)i$/i'                                                 => '$1us',
		'/(cris|ax|test)es$/i'                                             => '$1is',
		'/(shoe)s$/i'                                                      => '$1',
		'/(o)es$/i'                                                        => '$1',
		'/(bus)es$/i'                                                      => '$1',
		'/([m|l])ice$/i'                                                   => '$1ouse',
		'/(x|ch|ss|sh)es$/i'                                               => '$1',
		'/(m)ovies$/i'                                                     => '$1ovie',
		'/(s)eries$/i'                                                     => '$1eries',
		'/([^aeiouy]|qu)ies$/i'                                            => '$1y',
		'/([lr])ves$/i'                                                    => '$1f',
		'/(tive)s$/i'                                                      => '$1',
		'/(hive)s$/i'                                                      => '$1',
		'/(li|wi|kni)ves$/i'                                               => '$1fe',
		'/(shea|loa|lea|thie)ves$/i'                                       => '$1f',
		'/(^analy)ses$/i'                                                  => '$1sis',
		'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1$2sis',
		'/([ti])a$/i'                                                      => '$1um',
		'/(n)ews$/i'                                                       => '$1ews',
		'/(h|bl)ouses$/i'                                                  => '$1ouse',
		'/(corpse)s$/i'                                                    => '$1',
		'/(us)es$/i'                                                       => '$1',
		'/s$/i'                                                            => '',
	];

	public static $plural_rules = [
		'/(quiz)$/i'                     => '$1zes',
		'/^(ox)$/i'                      => '$1en',
		'/([m|l])ouse$/i'                => '$1ice',
		'/(matr|vert|ind)ix|ex$/i'       => '$1ices',
		'/(x|ch|ss|sh)$/i'               => '$1es',
		'/([^aeiouy]|qu)y$/i'            => '$1ies',
		'/(hive)$/i'                     => '$1s',
		'/(?:([^f])fe|([lr])f)$/i'       => '$1$2ves',
		'/(shea|lea|loa|thie)f$/i'       => '$1ves',
		'/sis$/i'                        => 'ses',
		'/([ti])um$/i'                   => '$1a',
		'/(tomat|potat|ech|her|vet)o$/i' => '$1oes',
		'/(bu)s$/i'                      => '$1ses',
		'/(alias)$/i'                    => '$1es',
		'/(octop)us$/i'                  => '$1i',
		'/(ax|test)is$/i'                => '$1es',
		'/(us)$/i'                       => '$1es',
		'/s$/i'                          => 's',
		'/$/'                            => 's',
	];

	public static function toSingular(string $input): string {
		// check for irregular plural input
		foreach (static::$irregular as $result => $pattern) {
			if (substr($pattern, 0, 1) !== '/') {
				$pattern = '/' . $pattern . '$/i';
			}
			if (preg_match($pattern, $input) === 1) {
				return preg_replace($pattern, $result, $input);
			}
		}

		// match singular regex rules
		foreach (static::$singular_rules as $pattern => $result) {
			if (preg_match($pattern, $input) === 1) {
				return preg_replace($pattern, $result, $input);
			}
		}

		// fallback: return unmodified input
		return $input;
	}

	public static function toPlural(string $input): string {
		// check for irregular singular input
		foreach (static::$irregular as $pattern => $result) {
			if (substr($pattern, 0, 1) !== '/') {
				$pattern = '/' . $pattern . '$/i';
			}
			if (preg_match($pattern, $input) === 1) {
				return preg_replace($pattern, $result, $input);
			}
		}

		// match plural regex rules
		foreach (static::$plural_rules as $pattern => $result) {
			if (preg_match($pattern, $input) === 1) {
				return preg_replace($pattern, $result, $input);
			}
		}

		// fallback: return unmodified input
		return $input;
	}

	protected static function getClassName(string $class_name): string {
		$class_name_ns_pos = strrpos($class_name, '\\');
		if ($class_name_ns_pos !== false) {
			$class_name = substr($class_name, $class_name_ns_pos + 1);
		}
		return $class_name;
	}

	public static function classNameToSingular(string $class_name) {
		return static::toSingular(Casing::camelToSnake(static::getClassName($class_name)));
	}

	public static function classNameToPlural(string $class_name) {
		return static::toPlural(Casing::camelToSnake(static::getClassName($class_name)));
	}
}