<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product\Import;

use MiniShop3\MiniShop3;
use MODX\Revolution\modX;

final class ImportCsvRunValidator
{
    public function __construct(
        private modX $modx,
        private MiniShop3 $ms3,
    ) {
    }

    /**
     * @return array<string, mixed>|null Error response or null when valid.
     */
    public function validateParams(ImportCsvContext $ctx): ?array
    {
        if ($ctx->params['keys'] === []) {
            $error = $this->modx->lexicon('ms3_utilities_import_fields_ns');
            $this->modx->log(modX::LOG_LEVEL_ERROR, $error);

            return $this->ms3->utils->error($error);
        }

        if (empty($ctx->params['key'])) {
            $error = $this->modx->lexicon('ms3_utilities_import_key_ns');
            $this->modx->log(modX::LOG_LEVEL_ERROR, $error);

            return $this->ms3->utils->error($error);
        }

        foreach (['parent', 'pagetitle'] as $rf) {
            $found = false;
            foreach ($ctx->params['keys'] as $key) {
                if ($key === $rf) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $error = $this->modx->lexicon('ms3_utilities_import_required_field', ['field' => $rf]);

                return $this->ms3->utils->error($error);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|true
     */
    public function validateFilePath(ImportCsvContext $ctx, ?string $file): array|bool
    {
        if (empty($file)) {
            $error = $this->modx->lexicon('ms3_utilities_import_file_ns');
            $this->modx->log(modX::LOG_LEVEL_ERROR, $error);

            return $this->ms3->utils->error($error);
        }

        if (!preg_match('/\.csv$/i', $file)) {
            $error = $this->modx->lexicon('ms3_utilities_import_file_ext_err');
            $this->modx->log(modX::LOG_LEVEL_ERROR, $error);

            return $this->ms3->utils->error($error);
        }

        $basePath = (string) $this->modx->getOption('base_path', null, '');
        $realPath = ImportCsvPathGuard::resolveCsvFileUnderBase($file, $basePath);

        if ($realPath === null) {
            $fullPath = ImportCsvPathGuard::isAbsolutePath($file)
                ? $file
                : str_replace('//', '/', $basePath . $file);

            if (!file_exists($fullPath) && !file_exists($file)) {
                $error = $this->modx->lexicon('ms3_utilities_import_file_nf', ['path' => $fullPath]);
                $this->modx->log(modX::LOG_LEVEL_ERROR, $error);

                return $this->ms3->utils->error($error);
            }

            $error = $this->modx->lexicon('ms3_utilities_import_file_outside');
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Import Security] Path traversal attempt: ' . $file);

            return $this->ms3->utils->error($error);
        }

        $ctx->params['file'] = $realPath;

        return true;
    }
}
