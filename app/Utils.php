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

	public static function uuid4(): string
	{
		$data = random_bytes(16);

		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

		return sprintf(
			'%08s-%04s-%04s-%04s-%12s',
			bin2hex(substr($data, 0, 4)),
			bin2hex(substr($data, 4, 2)),
			bin2hex(substr($data, 6, 2)),
			bin2hex(substr($data, 8, 2)),
			bin2hex(substr($data, 10, 6))
		);
	}

	public static function generateOfflineThreadingId()
	{
		$max_int = PHP_INT_MAX;
		$mask22_bits = (1 << 22) - 1;

		function get_current_timestamp()
		{
			return floor(microtime(true) * 1000);
		}

		function get_random_64bit_int()
		{
			$bytes = openssl_random_pseudo_bytes(8);
			$value = 0;
			for ($i = 0; $i < 8; $i++) {
				$value = ($value << 8) | ord($bytes[$i]);
			}
			return $value;
		}

		function combine_and_mask($timestamp, $random_value, $mask22_bits, $max_int)
		{
			$shifted_timestamp = $timestamp << 22;
			$masked_random = $random_value & $mask22_bits;
			return ($shifted_timestamp | $masked_random) & $max_int;
		}

		$timestamp = get_current_timestamp();
		$random_value = get_random_64bit_int();
		$threading_id = combine_and_mask($timestamp, $random_value, $mask22_bits, $max_int);

		return strval($threading_id);
	}

	public static function formatResponse(array $response): string
	{
		$text = "";

		$data = $response["data"] ?? [];
		$node = $data["node"] ?? [];
		$brm = $node["bot_response_message"] ?? [];
		$ct = $brm["composed_text"] ?? [];
		$contents = $ct["content"] ?? [];

		foreach ($contents as $content) {
			$text .= ($content["text"] ?? "") . PHP_EOL;
		}

		return $text;
	}

	public static function iterLines($stream, int $chunkSize = 512, string $delimiter = null)
	{
		$pending = null;

		while (!$stream->eof()) {
			$chunk = $stream->read($chunkSize);

			if ($pending !== null) {
				$chunk = $pending . $chunk;
			}

			if ($delimiter !== null) {
				$lines = explode($delimiter, $chunk);
			} else {
				$lines = explode("\n", $chunk);
			}

			if (!empty($lines) && substr($chunk, -1) === substr($lines[count($lines) - 1], -1)) {
				$pending = array_pop($lines);
			} else {
				$pending = null;
			}

			foreach ($lines as $line) {
				yield $line;
			}
		}

		if ($pending !== null) {
			yield $pending;
		}
	}
}
