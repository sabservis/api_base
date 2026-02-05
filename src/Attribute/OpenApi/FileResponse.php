<?php declare(strict_types = 1);

namespace Sabservis\Api\Attribute\OpenApi;

use Attribute;

/**
 * Marks endpoint as returning a file download.
 *
 * Usage:
 * ```php
 * #[Get(path: '/reports/{id}/pdf')]
 * #[FileResponse(contentType: 'application/pdf', filename: 'report.pdf')]
 * public function downloadPdf(ApiRequest $request): FileResponse
 * {
 *     return FileResponse::fromPath('/path/to/report.pdf');
 * }
 * ```
 *
 * Generates OpenAPI schema:
 * ```yaml
 * responses:
 *   200:
 *     description: File download
 *     content:
 *       application/pdf:
 *         schema:
 *           type: string
 *           format: binary
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class FileResponse implements OpenApiAttributeInterface
{

	/**
	 * @param string $contentType MIME type of the file (e.g., 'application/pdf', 'image/png')
	 * @param string|null $filename Example filename for documentation
	 * @param string|null $description Response description
	 * @param int $response HTTP status code (default 200)
	 */
	public function __construct(
		public string $contentType = 'application/octet-stream',
		public string|null $filename = null,
		public string|null $description = null,
		public int $response = 200,
	)
	{
	}

	public function getStatusCode(): string
	{
		return (string) $this->response;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toOpenApiSpec(): array
	{
		$description = $this->description ?? 'File download';

		if ($this->filename !== null) {
			$description .= ' (' . $this->filename . ')';
		}

		return [
			'description' => $description,
			'content' => [
				$this->contentType => [
					'schema' => [
						'type' => 'string',
						'format' => 'binary',
					],
				],
			],
		];
	}

}
