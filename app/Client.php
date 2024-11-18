<?php

namespace AdaiasMagdiel\MetaAI;

use Exception;
use JsonException;
use GuzzleHttp\Client as GuzzleHttpClient;
use stdClass;

define("MAX_RETRIES", 3);

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
				"user-agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
			]
		]);

		$this->isAuthed = false;
		$this->externalConversationId = "";
		$this->offlineThreadingId = "";

		$this->cookies = $this->getCookies();
		$this->accessToken = $this->getAccessToken();
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

	private function getAccessToken(): string
	{
		if (!empty($this->accessToken)) {
			return $this->accessToken;
		}

		$url = "https://www.meta.ai/api/graphql/";
		$payload = [
			"lsd" => $this->cookies["lsd"],
			"fb_api_caller_class" => "RelayModern",
			"fb_api_req_friendly_name" => "useAbraAcceptTOSForTempUserMutation",
			"variables" => json_encode([
				"dob" => "2002-01-01",
				"icebreaker_type" => "TEXT",
				"__relay_internal__pv__WebPixelRatiorelayprovider" => 1,
			]),
			"doc_id" => "7604648749596940",
		];

		$_js_datr = $this->cookies["_js_datr"] ?? "";
		$abra_csrf = $this->cookies["abra_csrf"] ?? "";
		$datr = $this->cookies["datr"] ?? "";

		foreach ([$_js_datr, $abra_csrf, $datr] as $cookie) {
			if (empty($cookie)) {
				throw new Exception("Problem to loading cookies.");
			}
		}

		$headers = [
			"content-type" => "application/x-www-form-urlencoded",
			"cookie" => "_js_datr=$_js_datr; abra_csrf=$abra_csrf; datr=$datr;",
			"sec-fetch-site" => "same-origin",
			"x-fb-friendly-name" => "useAbraAcceptTOSForTempUserMutation",
		];

		$response = $this->client->post($url, [
			'headers' => $headers,
			'form_params' => $payload
		]);

		try {
			$body = $response->getBody()->getContents();
			$authJson = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new Exception(
				"Unable to receive a valid response from Meta AI. This is likely due to your region being blocked. Try manually accessing https://www.meta.ai/ to confirm."
			);
		}

		$data = $authJson["data"] ?? [];
		$terms = $data["xab_abra_accept_terms_of_service"] ?? [];
		$newTempUserAuth = $terms["new_temp_user_auth"] ?? [];
		$accessToken = $newTempUserAuth["access_token"] ?? "";

		# Need to sleep for a bit, for some reason the API doesn't like it when we send request too quickly
		# (maybe Meta needs to register Cookies on their side?)
		sleep(1);

		return $accessToken;
	}

	private function extractLastResponse(string $response): ?array
	{
		$lastStreamedResponse = null;
		$lines = explode("\n", $response);

		foreach ($lines as $line) {
			try {
				$jsonLine = json_decode($line, associative: true, flags: JSON_THROW_ON_ERROR);
			} catch (JsonException) {
				continue;
			}

			$data = $jsonLine["data"] ?? [];
			$node = $data["node"] ?? [];
			$botResponseMessage = $node["bot_response_message"] ?? [];
			$chatId = $botResponseMessage["id"] ?? null;

			if (!is_null($chatId)) {
				list($externalConversationId, $offlineThreadingId, $_) = explode("_", $chatId, 3);

				$this->externalConversationId = $externalConversationId;
				$this->offlineThreadingId = $offlineThreadingId;
			}

			$streamingState = $botResponseMessage["streaming_state"] ?? "";
			if ($streamingState === "OVERALL_DONE") {
				$lastStreamedResponse = $jsonLine;
			}
		}

		return $lastStreamedResponse;
	}

	private function retry(string $message, bool $stream = false, int $attempts = 0)
	{
		if ($attempts <= MAX_RETRIES) {
			echo  "Was unable to obtain a valid response from Meta AI. Retrying...";
			sleep(3);

			return $this->prompt($message, stream: $stream, attempts: $attempts + 1);
		} else {
			throw new Exception(
				"Unable to obtain a valid response from Meta AI. Try again later."
			);
		}
	}

	private function extractData(array $jsonLine): stdClass
	{
		$data = $jsonLine["data"] ?? [];
		$node = $data["node"] ?? [];
		$botResponseMessage = $node["bot_response_message"] ?? [];

		$response = Utils::formatResponse(response: $jsonLine);

		/*
		TODO: Implements extract sources and medias
        	$fetchId = $botResponseMessage["fetch_id"] ?? null;
        	$sources = !is_null($fetchId) ? $this->fetchSources($fetchId) : [];
        	$medias = $this->extractMedia($botResponseMessage);
        */

		$obj = new stdClass();

		$obj->message = $response;
		$obj->sources = [];
		$obj->media = [];

		return $obj;
	}

	private function streamResponse($lines)
	{
		foreach ($lines as $line) {
			if (!empty($line)) {
				$jsonLine = json_decode($line, true);
				$extractedData = $this->extractData($jsonLine);

				if (empty($extractedData->message)) {
					continue;
				}

				yield $extractedData;
			}
		}
	}

	public function prompt(
		string $message,
		bool $stream = False,
		int $attempts = 0,
		bool $newConversation = False,
	): mixed {
		if (!$this->isAuthed) {
			$this->accessToken = $this->getAccessToken();
			$authPayload = ["access_token" => $this->accessToken];
			$url = "https://graph.meta.ai/graphql?locale=user";
		} else {
			$authPayload = ["fb_dtsg" => $this->cookies["fb_dtsg"]];
			$url = "https://www.meta.ai/api/graphql/";
		}

		if (empty($this->externalConversationId) || $newConversation) {
			$this->externalConversationId = Utils::uuid4();
		}

		$payload = array_merge($authPayload, [
			"fb_api_caller_class" => "RelayModern",
			"fb_api_req_friendly_name" => "useAbraSendMessageMutation",
			"variables" => '{"message": {"sensitive_string_value": "' . $message . '"}, "externalConversationId": "' . $this->externalConversationId . '", "offlineThreadingId": "' . Utils::generateOfflineThreadingId() . '", "suggestedPromptIndex": null, "flashVideoRecapInput": {"images": []}, "flashPreviewInput": null, "promptPrefix": null, "entrypoint": "ABRA__CHAT__TEXT", "icebreaker_type": "TEXT", "__relay_internal__pv__AbraDebugDevOnlyrelayprovider": false, "__relay_internal__pv__WebPixelRatiorelayprovider": 1}',
			"server_timestamps" => "true",
			"doc_id" => "7783822248314888",
		]);

		$_js_datr = $this->cookies["_js_datr"] ?? "";
		$abra_csrf = $this->cookies["abra_csrf"] ?? "";
		$datr = $this->cookies["datr"] ?? "";

		foreach ([$_js_datr, $abra_csrf, $datr] as $cookie) {
			if (empty($cookie)) {
				throw new Exception("Problem to loading cookies.");
			}
		}

		$headers = [
			"content-type" => "application/x-www-form-urlencoded",
			"x-fb-friendly-name" => "useAbraSendMessageMutation",
			"cookie" => "_js_datr=$_js_datr; abra_csrf=$abra_csrf; datr=$datr;",
			"sec-fetch-site" => "same-origin",
		];

		if ($this->isAuthed) {
			$headers["cookie"] = 'abra_sess=' . $this->cookies["abra_sess"];
		}

		$response = $this->client->post($url, [
			'headers' => $headers,
			'form_params' => $payload,
			"stream" => $stream
		]);

		if (!$stream) {
			$rawResponse = $response->getBody()->getContents();

			$lastStreamedResponse = $this->extractLastResponse($rawResponse);

			if (is_null($lastStreamedResponse)) {
				return $this->retry($message, stream: $stream, attempts: $attempts);
			}

			$extractedData = $this->extractData($lastStreamedResponse);
			return $extractedData;
		} else {
			$lines = Utils::iterLines($response->getBody());

			$isError = json_decode(next($lines), true);
			if (!empty($isError['errors'])) {
				return $this->retry($message, $stream, $attempts);
			}

			return $this->streamResponse($lines);
		}
	}
}
