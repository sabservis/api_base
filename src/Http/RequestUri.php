<?php declare(strict_types = 1);

namespace Sabservis\Api\Http;

use function is_int;
use function is_string;
use function parse_url;
use function str_starts_with;
use function strcspn;
use function strpos;
use function substr;
use const PHP_URL_FRAGMENT;
use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_PORT;
use const PHP_URL_QUERY;
use const PHP_URL_SCHEME;

/**
 * Simple URI value object for backwards compatibility with PSR-7 style getUri()->getPath().
 */
final class RequestUri
{

	public function __construct(private string $uri)
	{
	}

	public function getScheme(): string
	{
		$scheme = parse_url($this->uri, PHP_URL_SCHEME);

		return is_string($scheme) ? $scheme : '';
	}

	public function getHost(): string
	{
		$host = parse_url($this->uri, PHP_URL_HOST);

		return is_string($host) ? $host : '';
	}

	public function getPort(): int|null
	{
		$port = parse_url($this->uri, PHP_URL_PORT);

		return is_int($port) ? $port : null;
	}

	/**
	 * Origin-form URIs (starting with "/") are split literally: parse_url() would read
	 * "//evil.example/users/1" as host + "/users/1" and route it like "/users/1".
	 */
	public function getPath(): string
	{
		if (str_starts_with($this->uri, '/')) {
			return substr($this->uri, 0, strcspn($this->uri, '?#'));
		}

		$path = parse_url($this->uri, PHP_URL_PATH);

		return is_string($path) ? $path : '/';
	}

	public function getQuery(): string
	{
		if (str_starts_with($this->uri, '/')) {
			$start = strpos($this->uri, '?');

			if ($start === false) {
				return '';
			}

			$query = substr($this->uri, $start + 1);

			return substr($query, 0, strcspn($query, '#'));
		}

		$query = parse_url($this->uri, PHP_URL_QUERY);

		return is_string($query) ? $query : '';
	}

	public function getFragment(): string
	{
		$fragment = parse_url($this->uri, PHP_URL_FRAGMENT);

		return is_string($fragment) ? $fragment : '';
	}

	public function __toString(): string
	{
		return $this->uri;
	}

}
