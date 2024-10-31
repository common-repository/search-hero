<?php
namespace searchHero;

class tokenize {
	static public function getTokens(&$text, $hash = true){
		$symbols = '\x{0000}-\x{001F}';
		$symbols .= '\x{007F}-\x{009F}';
		$symbols .= '\x{0021}-\x{002B}\x{002D}\x{002F}';
		$symbols .= '\x{003A}-\x{0040}';
		$symbols .= '\x{005B}-\x{0060}';
		$symbols .= '\x{007B}-\x{007E}';
		$symbols .= '\x{00A1}-\x{00BF}\x{00D7}\x{00F7}';
		$symbols .= '\x{02B0}-\x{02FF}';
		$symbols .= '\x{0300}-\x{036F}';
		$symbols .= '\x{0374}-\x{0375}\x{037A}\x{037E}\x{0384}\x{0385}\x{0387}';
		$symbols .= '\x{2013}-\x{204A}';
		$symbols .= '\x{2000}-\x{206F}';
		$symbols .= '\x{2070}-\x{209F}';
		$symbols .= '\x{20A0}-\x{20CF}';
		$symbols .= '\x{2100}-\x{27FF}';
		$symbols .= '\x{3000}-\x{303F}';
		$symbols .= '\x{FEFF}';
		$symbols .= '\x{1F700}-\x{1F77F}';
		$symbols .= '\x{1F030}-\x{1FA6F}';

		$numbers = '\x{0030}-\x{0039}';
		$letters = '^' . $symbols;

		$text = preg_replace_callback('/[\x{FF01}-\x{FF60}]/uS',
			function($char){
				$ord = mb_ord($char[0]);
				if($ord == 65375) return '(';
				if($ord == 65376) return ')';
				return mb_chr($ord - 65248);
			}, $text);

		$searches = array();
		$searches[] = '/([' . $letters . '])[’\']([' . $letters . ']{1,2})/uS';
		$searches[] = '/([0-9]{15,15})/uS';
		$searches[] = '/([' . $letters . '\s]{35,35})/uS';
		$searches[] = '/[' . $symbols . ']/uS';
		$searches[] = '/[\.,]{2,}/uS';
		$searches[] = '/([^' . $symbols . '])[\.,]+(\s|$)/uS';
		$searches[] = '/(^|\s)[\.,]+([^' . $symbols . '])/uS';
		$searches[] = '/\s+/uS';

		$replaces = array();
		$replaces[] = '\1\2';
		$replaces[] = '\1 ';
		$replaces[] = '\1 ';
		$replaces[] = ' ';
		$replaces[] = ' ';
		$replaces[] = '\1\2';
		$replaces[] = '\1\2';
		$replaces[] = ' ';
		$text = preg_replace($searches, $replaces, $text);

/*
		$text = preg_replace('/([' . $letters . '])[’\']([' . $letters . ']{1,2})/u', '\1\2', $text);
		$text = preg_replace('/([0-9]{15,15})/u', '\1 ', $text);
		$text = preg_replace('/([' . $letters . '\s]{35,35})/u', '\1 ', $text);
		$text = preg_replace('/[' . $symbols . ']/u', ' ', $text);
		$text = preg_replace('/[\.,]{2,}/u', ' ', $text);
		$text = preg_replace('/([^' . $symbols . '])[\.,]+(\s|$)/u', '\1\2', $text);
		$text = preg_replace('/(^|\s)[\.,]+([^' . $symbols . '])/u', '\1\2', $text);
*/

		$lastText = mb_strtolower($text);
		$lastText = preg_split('/\s/uS', trim($lastText), 0, PREG_SPLIT_NO_EMPTY);

		$results = array();
	
		foreach($lastText as $k => &$value) {
			if(mb_strlen($value) < 2){
				continue;
			} else {
				if($hash){
					$hashed = crc32($value);

				
					if ($hashed & 0x80000000) $hashed = $hashed - 0x100000000;

					if(PHP_INT_SIZE == 8){
						$hash2 = crc32(strrev($value));
						$hashed = (int) ( ($hashed << 32) | $hash2);
					}

				}
			}

			$results[] = $hashed;
		}

		return $results;
	}
}

