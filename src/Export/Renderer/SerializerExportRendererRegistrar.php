<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author      thomas.baier@launch-lab.de
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Export\Renderer;

use Symfony\Component\Serializer\SerializerInterface;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\ExportTemplateNormalizerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers serializer-backed export renderers.
 */
final readonly class SerializerExportRendererRegistrar implements HookRegistrarInterface {
	/**
	 * Initializes the serializer export renderer registrar.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportTemplateNormalizerInterface $template_normalizer Template normalizer.
	 * @param SerializerInterface               $serializer          Serializer.
	 */
	public function __construct(
		private ExportTemplateNormalizerInterface $template_normalizer,
		private SerializerInterface $serializer
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_renderer',
			function ( array $renderers ): array {
				$renderers[ SerializerExportRenderer::RENDERER_ID_JSON ] = new SerializerExportRenderer(
					$this->template_normalizer,
					$this->serializer,
					SerializerExportRenderer::RENDERER_ID_JSON,
					SerializerExportRenderer::FORMAT_JSON,
					'json',
					'application/json'
				);

				// XML support can be enabled once the XML template shape is defined.

				return $renderers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}
}
