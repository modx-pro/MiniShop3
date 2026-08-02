<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MODX\Revolution\modX;

final class ImportCsvGalleryHandler
{
    public function __construct(
        private modX $modx,
    ) {
    }

    /**
     * @param array<string, mixed> $resource
     * @param list<string>         $gallery
     */
    public function importForResource(array $resource, array $gallery): void
    {
        if ($gallery === []) {
            return;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "Importing images: \n" . print_r($gallery, true));

        $basePath = (string) $this->modx->getOption('base_path', null, '');
        $processorsPath = (string) $this->modx->getOption('core_path', null, '')
            . 'components/minishop3/src/Processors/';

        foreach ($gallery as $v) {
            if (!is_string($v) || $v === '') {
                continue;
            }

            $image = ImportCsvPathGuard::resolveAssetUnderBase($v, $basePath);
            if ($image === null) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "[Import Security] Could not import gallery image \"$v\": path outside base or not found."
                );
                continue;
            }

            $response = $this->modx->runProcessor(
                'MiniShop3\\Processors\\Gallery\\Upload',
                ['id' => $resource['id'], 'name' => $v, 'file' => $image],
                ['processors_path' => $processorsPath]
            );

            if ($response->isError()) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "Error on upload \"$v\": \n" . print_r($response->getAllErrors(), true)
                );
            } else {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "Successful upload  \"$v\": \n" . print_r($response->getObject(), true)
                );
            }
        }
    }
}
