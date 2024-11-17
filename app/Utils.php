<?php

namespace AdaiasMagdiel\MetaAI;


class Utils
{
	/**
	 * Helper function to extract a specific value from the given text using a key.
	 *
	 * @param  string $text The text from which to extract the value.
	 * @param  string $start The starting key.
	 * @param  string $end The ending key.
	 * @return string The extracted value.
	 */
	public static function extractValue(string $text, string $start, string $end): string
	{
		$startIdx = strpos($text, $start) + strlen($start);
		$endIdx = strpos($text, $end, $startIdx);

		if ($startIdx === false || $endIdx === false) {
			return '';
		}

		return substr($text, $startIdx, $endIdx - $startIdx);
	}
}
