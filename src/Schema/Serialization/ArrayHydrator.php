<?php declare(strict_types = 1);

namespace Sabservis\Api\Schema\Serialization;

use Sabservis\Api\Attribute\OpenApi\FileUpload;
use Sabservis\Api\Exception\Logical\InvalidArgumentException;
use Sabservis\Api\Exception\Logical\InvalidStateException;
use Sabservis\Api\Schema\Endpoint;
use Sabservis\Api\Schema\EndpointParameter;
use Sabservis\Api\Schema\EndpointRequestBody;
use Sabservis\Api\Schema\EndpointResponse;
use Sabservis\Api\Schema\Schema;
use function is_array;
use function sprintf;

class ArrayHydrator implements Hydrator
{

	public function hydrate(mixed $data): Schema
	{
		if (!is_array($data)) {
			throw new InvalidArgumentException(sprintf('%s support only arrays hydration.', self::class));
		}

		$schema = new Schema();

		// Support both old format (array of endpoints) and new format (with tags)
		$endpoints = $data['endpoints'] ?? $data;
		$tags = $data['tags'] ?? [];

		foreach ($endpoints as $endpoint) {
			if (!is_array($endpoint)) {
				continue;
			}

			$endpoint = $this->hydrateEndpoint($endpoint);
			$schema->addEndpoint($endpoint);
		}

		// Hydrate tags
		foreach ($tags as $name => $tagData) {
			$schema->addTag(
				$tagData['name'] ?? $name,
				$tagData['description'] ?? null,
			);
		}

		return $schema;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function hydrateEndpoint(array $data): Endpoint
	{
		if (!isset($data['handler'])) {
			throw new InvalidStateException("Schema route 'handler' is required");
		}

		$endpoint = new Endpoint(
			$data['handler']['class'],
			$data['handler']['method'],
		);

		$endpoint->setMethods($data['methods']);
		$endpoint->setMask($data['mask']);

		if (isset($data['tags'])) {
			foreach ($data['tags'] as $name => $value) {
				$endpoint->addTag($name, $value);
			}
		}

		if (isset($data['id'])) {
			$endpoint->addTag(Endpoint::TAG_ID, $data['id']);
		}

		if (isset($data['summary'])) {
			$endpoint->setSummary($data['summary']);
		}

		if (isset($data['deprecated']) && $data['deprecated'] === true) {
			$endpoint->setDeprecated(true);
		}

		if (isset($data['attributes']['pattern'])) {
			$endpoint->getRouteDefinition()->setRawPattern($data['attributes']['pattern']);
		}

		if (isset($data['parameters'])) {
			foreach ($data['parameters'] as $param) {
				$parameter = new EndpointParameter(
					$param['name'],
					$param['type'],
				);
				$parameter->setDescription($param['description']);
				$parameter->setIn($param['in']);
				$parameter->setRequired($param['required']);
				$parameter->setDeprecated($param['deprecated']);
				$parameter->setAllowEmptyValue($param['allowEmptyValue'] ?? $param['allowEmpty'] ?? false);

				$endpoint->addParameter($parameter);
			}
		}

		if (isset($data['requestBody'])) {
			$requestData = $data['requestBody'];

			$request = new EndpointRequestBody();
			$request->setDescription($requestData['description']);
			$request->setEntity($requestData['entity']);
			$request->setRequired($requestData['required']);
			$request->setContentSpec($requestData['contentSpec'] ?? null);
			$request->setAllowedContentTypes($requestData['allowedContentTypes'] ?? null);

			// Hydrate file uploads
			if (isset($requestData['fileUploads']) && is_array($requestData['fileUploads'])) {
				$fileUploads = [];

				foreach ($requestData['fileUploads'] as $uploadData) {
					// FileUpload can be either already an object (from direct cache)
					// or an array (from serialized cache)
					if ($uploadData instanceof FileUpload) {
						$fileUploads[] = $uploadData;
					} elseif (is_array($uploadData)) {
						$fileUploads[] = new FileUpload(
							name: $uploadData['name'],
							multiple: $uploadData['multiple'] ?? false,
							required: $uploadData['required'] ?? true,
							description: $uploadData['description'] ?? null,
							allowedTypes: $uploadData['allowedTypes'] ?? null,
						);
					}
				}

				$request->setFileUploads($fileUploads);
			}

			$endpoint->setRequestBody($request);
		}

		if (isset($data['responses'])) {
			foreach ($data['responses'] as $res) {
				$response = new EndpointResponse(
					$res['code'],
					$res['description'],
				);

				if (isset($res['entity'])) {
					$response->setEntity($res['entity']);
				}

				if (isset($res['wrapperType'])) {
					$response->setWrapperType($res['wrapperType']);
				}

				if (isset($res['fileResponse']['contentType'])) {
					$response->setFileContentType($res['fileResponse']['contentType']);
				}

				if (isset($res['contentSpec'])) {
					$response->setContentSpec($res['contentSpec']);
				}

				if (isset($res['referencedSchemas'])) {
					$response->setReferencedSchemas($res['referencedSchemas']);
				}

				$endpoint->addResponse($response);
			}
		}

		if (isset($data['openApi'])) {
			$endpoint->setOpenApi($data['openApi']);
		}

		return $endpoint;
	}

}
