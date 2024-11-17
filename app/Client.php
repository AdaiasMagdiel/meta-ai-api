<?php

namespace AdaiasMagdiel\MetaAI;

use GuzzleHttp\Client as GuzzleHttpClient;

class Client
{
	private GuzzleHttpClient $client;

	private string $accessToken;
	private bool   $isAuthed;
	private string $externalConversationId;
	private string $offlineThreadingId;
	private array $cookies;

	public function __construct()
	{
		$this->client = new GuzzleHttpClient(config: [
			"headers" => [
				"User-Agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
			]
		]);

		$this->accessToken = "";
		$this->isAuthed = false;
		$this->externalConversationId = "";
		$this->offlineThreadingId = "";

		$this->cookies = $this->getCookies();
	}

	private function getCookies(): array
	{
		$client = new GuzzleHttpClient();
		$response = $client->get("https://www.meta.ai/");
		$text = $response->getBody()->getContents();

		$cookies = [
			"_js_datr" => Utils::extractValue(
				$text,
				start: '_js_datr":{"value":"',
				end: '",'
			),
			"datr" => Utils::extractValue(
				$text,
				start: 'datr":{"value":"',
				end: '",'
			),
			"lsd" => Utils::extractValue(
				$text,
				start: '"LSD",[],{"token":"',
				end: '"}'
			),
			"fb_dtsg" => Utils::extractValue(
				$text,
				start: 'DTSGInitData",[],{"token":"',
				end: '"'
			),
			"abra_csrf" => Utils::extractValue(
				$text,
				start: 'abra_csrf":{"value":"',
				end: '",'
			)
		];

		return $cookies;
	}
}
