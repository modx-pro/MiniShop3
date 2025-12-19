<?php

namespace MiniShop3\Processors\Utilities\Import;

use MODX\Revolution\Processors\Processor;

/**
 * Upload CSV file for import
 */
class Upload extends Processor
{
    public $languageTopics = ['minishop3:default', 'minishop3:manager'];
    public $permission = 'msproduct_save';

    public function checkPermissions(): bool
    {
        return !empty($this->permission) ? $this->modx->hasPermission($this->permission) : true;
    }

    public function getLanguageTopics(): array
    {
        return $this->languageTopics;
    }

    public function process(): array
    {
        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            return $this->failure($this->modx->lexicon('ms3_import_upload_no_file'));
        }

        $file = $_FILES['file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => $this->modx->lexicon('ms3_import_upload_err_ini_size'),
                UPLOAD_ERR_FORM_SIZE => $this->modx->lexicon('ms3_import_upload_err_form_size'),
                UPLOAD_ERR_PARTIAL => $this->modx->lexicon('ms3_import_upload_err_partial'),
                UPLOAD_ERR_NO_FILE => $this->modx->lexicon('ms3_import_upload_no_file'),
                UPLOAD_ERR_NO_TMP_DIR => $this->modx->lexicon('ms3_import_upload_err_no_tmp'),
                UPLOAD_ERR_CANT_WRITE => $this->modx->lexicon('ms3_import_upload_err_cant_write'),
            ];
            $message = $errorMessages[$file['error']] ?? $this->modx->lexicon('ms3_import_upload_error');
            return $this->failure($message);
        }

        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            return $this->failure($this->modx->lexicon('ms3_utilities_import_file_ext_err'));
        }

        // Validate MIME type (allow common CSV types)
        $allowedMimes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'text/x-csv',
        ];
        if (!in_array($file['type'], $allowedMimes) && !empty($file['type'])) {
            // Some systems don't send proper MIME, so we only reject if type is sent and wrong
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_WARN,
                "[Import Upload] Unexpected MIME type: {$file['type']} for file: {$file['name']}");
        }

        // Determine upload directory
        $uploadDir = $this->modx->getOption('ms3_import_upload_path', null, 'assets/import/');
        if (!str_starts_with($uploadDir, '/')) {
            $uploadDir = MODX_BASE_PATH . $uploadDir;
        }

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return $this->failure($this->modx->lexicon('ms3_import_upload_dir_error'));
            }
        }

        // Generate unique filename
        $timestamp = date('Y-m-d_H-i-s');
        $randomSuffix = bin2hex(random_bytes(4));
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $newFileName = "{$timestamp}_{$safeOriginalName}_{$randomSuffix}.csv";
        $targetPath = rtrim($uploadDir, '/') . '/' . $newFileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $this->failure($this->modx->lexicon('ms3_import_upload_move_error'));
        }

        // Calculate relative path from MODX_BASE_PATH
        $relativePath = str_replace(MODX_BASE_PATH, '', $targetPath);
        $relativePath = ltrim($relativePath, '/');

        $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_INFO,
            "[Import Upload] File uploaded successfully: {$relativePath}");

        return $this->success('', [
            'file' => $relativePath,
            'original_name' => $file['name'],
            'size' => $file['size'],
        ]);
    }
}
