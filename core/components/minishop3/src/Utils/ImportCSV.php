<?php

namespace MiniShop3\Utils;

use MiniShop3\Controllers\Options\Types\msOptionType;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MiniShop3\Services\Option\OptionService;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;
use xPDO\xPDO;

class ImportCSV
{
    /**
     * @var modX $modx
     */
    private modX $modx;
    /**
     * @var MiniShop3 $ms3
     */
    private MiniShop3 $ms3;
    private int $rows = 0;
    private int $created = 0;
    private int $updated = 0;
    private int $errors = 0;
    private int $skipped = 0;

    private array $params = [];
    private string $importId = '';
    private array $tvCache = [];
    private ?string $detectedEncoding = null;

    public function __construct(modX &$modx)
    {
        $this->modx = $modx;
        $this->ms3 = $this->modx->services->get('ms3');
        // Time limit
        set_time_limit(600);
        $tmp = 'Trying to set time limit = 600 sec: ';
        $tmp .= ini_get('max_execution_time') == 600 ? 'done' : 'error';
        $this->modx->log(modX::LOG_LEVEL_INFO, $tmp);
    }

    public function process(array $params): array
    {
        $this->params['file'] = $params['file'] ?? null;
        $this->params['fields'] = $params['fields'] ?? null;
        $this->params['mapping'] = $params['mapping'] ?? null;
        $this->params['update'] = !empty($params['update']);
        $this->params['key'] = $params['key'] ?? null;
        $this->params['skip_header'] = $params['skip_header'] ?? false;
        $this->params['is_debug'] = !empty($params['debug']);
        $this->params['delimiter'] = $params['delimiter'] ?? ';';
        $this->params['keys'] = [];
        $this->params['tv_enabled'] = false;
        $this->params['option_enabled'] = false;

        // Generate import ID for progress tracking
        $this->importId = $params['import_id'] ?? uniqid('import_');

        // Check required options - use mapping if provided, otherwise fields string
        if (!empty($this->params['mapping']) && is_array($this->params['mapping'])) {
            // New mapping format: [0 => 'pagetitle', 1 => 'parent', 2 => 'price', ...]
            $this->params['keys'] = $this->params['mapping'];
        } elseif (!empty($this->params['fields'])) {
            // Legacy format: comma-separated string
            $this->params['keys'] = array_map('trim', explode(',', $this->params['fields']));
        } else {
            $error = $this->modx->lexicon('ms3_utilities_import_fields_ns');
            $this->modx->log(modX::LOG_LEVEL_ERROR, $error);
            return $this->ms3->utils->error($error);
        }

        if (empty($this->params['key'])) {
            $error = $this->modx->lexicon('ms3_utilities_import_key_ns');
            $this->modx->log(modX::LOG_LEVEL_ERROR, $error);
            return $this->ms3->utils->error($error);
        }

        // Check for TV and Option prefixes
        foreach ($this->params['keys'] as $v) {
            if ($v === null || $v === '' || $v === '-') {
                continue;
            }
            // Legacy TV format: tv1, tv2, etc.
            if (preg_match('/^tv\d+$/', $v)) {
                $this->params['tv_enabled'] = true;
            }
            // New TV format: tv.fieldname
            if (str_starts_with($v, 'tv.')) {
                $this->params['tv_enabled'] = true;
            }
            // Option format: option.keyname
            if (str_starts_with($v, 'option.')) {
                $this->params['option_enabled'] = true;
            }
        }

        // Validate and secure file path
        $fileValidation = $this->validateFilePath($this->params['file']);
        if ($fileValidation !== true) {
            return $fileValidation;
        }

        // Check required fields
        $requiredFields = ['parent', 'pagetitle'];
        foreach ($requiredFields as $rf) {
            $found = false;
            foreach ($this->params['keys'] as $key) {
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

        // Fire before import event.
        //
        // Two propagation paths supported:
        //   1) by-ref mutation of $this->params in the plugin scope — preserved
        //      for plugins that mutate $scriptProperties['params'] directly.
        //   2) $modx->event->returnedValues['params'] — explicit channel from
        //      #219/#245 for plugins that prefer the returned-values contract.
        EventGate::clearReturnedValues($this->modx);
        $eventResult = $this->modx->invokeEvent('msOnBeforeImport', [
            'file' => $this->params['file'],
            'params' => &$this->params,
        ]);
        $returnedValues = EventGate::getReturnedValues($this->modx);
        $this->params = EventGate::applyReturnedArray($this->params, $returnedValues, 'params');
        if (EventGate::isCancelled($eventResult)) {
            $error = $this->modx->lexicon('ms3_utilities_import_cancelled');
            return $this->ms3->utils->error($error);
        }

        $this->import();

        // Fire after import event
        $this->modx->invokeEvent('msOnAfterImport', [
            'stats' => [
                'total' => $this->rows,
                'created' => $this->created,
                'updated' => $this->updated,
                'errors' => $this->errors,
                'skipped' => $this->skipped,
            ],
        ]);

        $message = $this->modx->lexicon('ms3_utilities_import_success', [
            'total' => $this->rows,
            'created' => $this->created,
            'updated' => $this->updated
        ]);

        return $this->ms3->utils->success($message, [
            'total' => $this->rows,
            'created' => $this->created,
            'updated' => $this->updated,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
        ]);
    }

    /**
     * Validate file path and protect against path traversal
     */
    private function validateFilePath(?string $file): array|bool
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

        // Build full path
        $fullPath = str_replace('//', '/', MODX_BASE_PATH . $file);

        // Path traversal protection: resolve real path and check it's within MODX_BASE_PATH
        $realPath = realpath($fullPath);
        $realBasePath = realpath(MODX_BASE_PATH);

        if ($realPath === false) {
            $error = $this->modx->lexicon('ms3_utilities_import_file_nf', ['path' => $fullPath]);
            $this->modx->log(modX::LOG_LEVEL_ERROR, $error);
            return $this->ms3->utils->error($error);
        }

        // Ensure file is within MODX base path (path traversal protection)
        if (!str_starts_with($realPath, $realBasePath)) {
            $error = $this->modx->lexicon('ms3_utilities_import_file_outside');
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Import Security] Path traversal attempt: ' . $file);
            return $this->ms3->utils->error($error);
        }

        $this->params['file'] = $realPath;
        return true;
    }

    private function import(): bool
    {
        // Prepare file: detect encoding, remove BOM, convert to UTF-8 if needed
        $preparedFile = $this->prepareFile($this->params['file']);

        $handle = fopen($preparedFile, 'r');

        // Count total rows for progress
        $totalRows = 0;
        while (fgetcsv($handle, 0, $this->params['delimiter']) !== false) {
            $totalRows++;
        }
        rewind($handle);

        // Adjust for header if skipping
        $rowsToProcess = $this->params['skip_header'] ? $totalRows - 1 : $totalRows;
        $this->saveProgress(0, $rowsToProcess);

        while (($csv = fgetcsv($handle, 0, $this->params['delimiter'])) !== false) {
            $this->rows++;

            if (!empty($this->params['skip_header']) && $this->rows === 1) {
                continue;
            }

            $this->processRow($csv);
            $this->saveProgress($this->rows, $rowsToProcess);

            if ($this->params['is_debug'] && $this->rows === 1) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    'You in debug mode, so we process only 1 row. Time: ' . number_format(
                        microtime(true) - $this->modx->startTime,
                        7
                    ) . ' s'
                );
                break;
            }
        }
        fclose($handle);

        // Mark import as completed
        $this->saveProgress($this->rows, $rowsToProcess, true);

        return true;
    }

    /**
     * Save import progress to cache for polling
     */
    private function saveProgress(int $current, int $total, bool $completed = false): void
    {
        $progress = [
            'import_id' => $this->importId,
            'current' => $current,
            'total' => $total,
            'created' => $this->created,
            'updated' => $this->updated,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
            'completed' => $completed,
            'percent' => $total > 0 ? round(($current / $total) * 100) : 0,
            'timestamp' => time(),
        ];

        $cacheKey = 'import_progress_' . $this->importId;
        $this->modx->cacheManager->set($cacheKey, $progress, 3600, [
            xPDO::OPT_CACHE_KEY => 'minishop3',
        ]);
    }

    /**
     * Get current import progress
     */
    public static function getProgress(modX $modx, string $importId): ?array
    {
        $cacheKey = 'import_progress_' . $importId;
        return $modx->cacheManager->get($cacheKey, [
            xPDO::OPT_CACHE_KEY => 'minishop3',
        ]);
    }

    private function processRow(array $csv): bool
    {
        $data = [];
        $gallery = [];
        $tvData = [];
        $optionData = [];

        $this->modx->log(modX::LOG_LEVEL_INFO, "Raw data for import: \n" . print_r($csv, 1));

        foreach ($this->params['keys'] as $k => $v) {
            // Skip empty mappings (user chose to skip this column)
            if ($v === null || $v === '' || $v === '-') {
                continue;
            }

            if (!isset($csv[$k])) {
                $error = $this->modx->lexicon('ms3_utilities_file_field_nf', ['field' => $v, 'row' => $this->rows]);
                $this->modx->log(modX::LOG_LEVEL_ERROR, $error);
                $this->errors++;
                return false;
            }

            $value = trim($csv[$k]);

            // Handle gallery field
            if ($v === 'gallery') {
                if (!empty($value)) {
                    $gallery[] = $value;
                }
                continue;
            }

            // Handle TV fields (tv.fieldname format)
            if (str_starts_with($v, 'tv.')) {
                $tvName = substr($v, 3);
                $tvData[$tvName] = $value;
                continue;
            }

            // Handle Option fields (option.keyname format)
            if (str_starts_with($v, 'option.')) {
                $optionKey = substr($v, 7);
                $optionData[$optionKey] = $value;
                continue;
            }

            // Handle vendor by name (resolve to vendor_id)
            if ($v === 'vendor') {
                $data['vendor_id'] = $this->resolveVendor($value);
                continue;
            }

            // MS2 compatibility: remains -> stock
            if ($v === 'remains') {
                $data['stock'] = $value;
                continue;
            }

            // Handle multiple values for same field
            if (isset($data[$v]) && !is_array($data[$v])) {
                $data[$v] = [$data[$v], $value];
            } elseif (isset($data[$v])) {
                $data[$v][] = $value;
            } else {
                $data[$v] = $value;
            }
        }

        // Fire event to allow modification of row data.
        //
        // Two propagation paths supported:
        //   1) by-ref mutation of $data/$tvData/$optionData/$gallery in the
        //      plugin scope — preserved for plugins that mutate $scriptProperties
        //      directly (long-standing extension contract).
        //   2) $modx->event->returnedValues['data'|'tvData'|'optionData'|'gallery']
        //      — explicit channel from #219/#245 for plugins that prefer the
        //      returned-values contract.
        EventGate::clearReturnedValues($this->modx);
        $eventResult = $this->modx->invokeEvent('msOnImportRow', [
            'row' => $this->rows,
            'csv' => $csv,
            'data' => &$data,
            'tvData' => &$tvData,
            'optionData' => &$optionData,
            'gallery' => &$gallery,
        ]);
        $returnedValues = EventGate::getReturnedValues($this->modx);
        $data = EventGate::applyReturnedArray($data, $returnedValues, 'data');
        $tvData = EventGate::applyReturnedArray($tvData, $returnedValues, 'tvData');
        $optionData = EventGate::applyReturnedArray($optionData, $returnedValues, 'optionData');
        $gallery = EventGate::applyReturnedArray($gallery, $returnedValues, 'gallery');
        if (EventGate::isCancelled($eventResult)) {
            $this->skipped++;
            return true;
        }

        // Validate required fields
        if (empty($data['pagetitle'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[Import] Row {$this->rows}: Missing required field 'pagetitle'");
            $this->errors++;
            return false;
        }

        if (empty($data['parent'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[Import] Row {$this->rows}: Missing required field 'parent'");
            $this->errors++;
            return false;
        }

        // Validate parent exists
        $parentId = (int)$data['parent'];
        $parent = $this->modx->getObject(modResource::class, ['id' => $parentId]);
        if (!$parent) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,
                "[Import] Row {$this->rows}: Parent resource with id={$parentId} not found");
            $this->errors++;
            return false;
        }

        // Set default values
        if (empty($data['class_key'])) {
            $data['class_key'] = msProduct::class;
        }
        if (empty($data['context_key'])) {
            $data['context_key'] = $parent->get('context_key');
        }

        // Enable TV processing if we have TV data
        $data['tvs'] = $this->params['tv_enabled'] || !empty($tvData);

        // Add TV data to main data array for MODX processor
        // MODX expects format tv{ID}, so we need to resolve TV name to ID
        foreach ($tvData as $tvName => $tvValue) {
            $tvId = $this->resolveTvId($tvName);
            if ($tvId) {
                $data['tv' . $tvId] = $tvValue;
            } else {
                $this->modx->log(modX::LOG_LEVEL_WARN,
                    "[Import] Row {$this->rows}: TV '$tvName' not found, skipping");
            }
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "Array with importing data: \n" . print_r($data, 1));

        // Duplicate check
        $exists = $this->findExistingProduct($data);

        $action = 'Create';
        if ($exists) {
            $key = $this->params['key'];
            $keyValue = $data[$key] ?? 'N/A';
            $this->modx->log(modX::LOG_LEVEL_INFO, "Key $key = $keyValue has duplicate.");

            if (!$this->params['update']) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "Skipping line with $key = \"$keyValue\" because update is disabled."
                );
                $this->skipped++;
                return true;
            }
            $action = 'Update';
            $data['id'] = $exists->id;
        }

        $this->runAction($action, $data, $gallery, $optionData);
        return true;
    }

    /**
     * Find existing product by key field
     */
    private function findExistingProduct(array $data): ?modResource
    {
        $isProduct = strtolower($data['class_key']) === strtolower(msProduct::class);

        $q = $this->modx->newQuery($data['class_key']);
        $classAlias = $isProduct ? 'msProduct' : 'modResource';
        $q->setClassAlias($classAlias);
        $q->where([
            'deleted' => 0,
            'class_key' => $data['class_key']
        ]);
        $q->select($classAlias . '.id');

        if ($isProduct) {
            $q->innerJoin(msProductData::class, 'Data', $classAlias . '.id = Data.id');
        }

        $key = $this->params['key'];
        $keyValue = $data[$key] ?? null;

        if ($keyValue === null) {
            return null;
        }

        $resourceFields = $this->modx->getFields($data['class_key']);
        if (isset($resourceFields[$key])) {
            $q->where([$key => $keyValue]);
        } elseif ($isProduct) {
            $q->where(['Data.' . $key => $keyValue]);
        }

        $q->prepare();
        $this->modx->log(modX::LOG_LEVEL_INFO, "SQL query for check for duplicate: \n" . $q->toSql());

        return $this->modx->getObject($data['class_key'], $q);
    }

    /**
     * Resolve vendor name to vendor_id
     */
    private function resolveVendor(string $vendorName): int
    {
        if (empty($vendorName)) {
            return 0;
        }

        // If it's already a numeric ID, return it
        if (is_numeric($vendorName)) {
            return (int)$vendorName;
        }

        // Try to find vendor by name
        $vendor = $this->modx->getObject(msVendor::class, ['name' => $vendorName]);
        if ($vendor) {
            return $vendor->get('id');
        }

        // Create new vendor if not found
        $vendor = $this->modx->newObject(msVendor::class);
        $vendor->set('name', $vendorName);
        if ($vendor->save()) {
            $this->modx->log(modX::LOG_LEVEL_INFO, "Created new vendor: $vendorName");
            return $vendor->get('id');
        }

        return 0;
    }

    /**
     * Resolve TV name or ID to TV ID
     * Supports: tv.5 (by ID) or tv.fieldname (by name)
     * Caches results for performance
     */
    private function resolveTvId(string $tvNameOrId): int
    {
        // Check cache first
        if (isset($this->tvCache[$tvNameOrId])) {
            return $this->tvCache[$tvNameOrId];
        }

        // If it's a numeric ID, verify it exists and return
        if (is_numeric($tvNameOrId)) {
            $tvId = (int)$tvNameOrId;
            $tv = $this->modx->getObject(\MODX\Revolution\modTemplateVar::class, ['id' => $tvId]);
            if ($tv) {
                $this->tvCache[$tvNameOrId] = $tvId;
                return $tvId;
            }
            $this->tvCache[$tvNameOrId] = 0;
            return 0;
        }

        // Find TV by name
        $tv = $this->modx->getObject(\MODX\Revolution\modTemplateVar::class, ['name' => $tvNameOrId]);
        if ($tv) {
            $tvId = $tv->get('id');
            $this->tvCache[$tvNameOrId] = $tvId;
            return $tvId;
        }

        // Not found - cache as 0
        $this->tvCache[$tvNameOrId] = 0;
        return 0;
    }

    private function runAction(string $action, array $data, array $gallery = [], array $optionData = []): void
    {
        $this->modx->error->reset();
        /** @var \MODX\Revolution\Processors\ProcessorResponse $response */
        $response = $this->modx->runProcessor('MODX\\Revolution\\Processors\\Resource\\' . $action, $data);

        if ($response->isError()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "Error on $action: \n" . print_r($response->getAllErrors(), 1));
            $this->errors++;
            return;
        }

        if ($action === 'Update') {
            $this->updated++;
        } else {
            $this->created++;
        }

        $resource = $response->getObject();
        $this->modx->log(modX::LOG_LEVEL_INFO, "Successful $action: \n" . print_r($resource, 1));

        $productId = $resource['id'] ?? null;
        if (!$productId) {
            return;
        }

        // Process option data
        if (!empty($optionData)) {
            $this->processOptions($productId, $optionData);
        }

        // Process gallery images
        if (!empty($gallery)) {
            $this->processGallery($resource, $gallery);
        }
    }

    private function processOptions(int $productId, array $optionData): void
    {
        $keys = array_values(array_filter(array_keys($optionData), static fn($key): bool => $key !== ''));
        if ($keys === []) {
            return;
        }

        $typesByKey = $this->loadOptionTypesByKey($keys);
        $parsedOptions = [];

        foreach ($optionData as $key => $value) {
            if ($key === '') {
                continue;
            }

            $parsedOptions[$key] = Utils::parseImportedOptionValue(
                $value,
                msOptionType::isMultiValueType($typesByKey[$key] ?? null)
            );
        }

        /** @var OptionService $optionService */
        $optionService = $this->modx->services->get('ms3_option_service');
        $optionService->saveProductOptions($productId, $parsedOptions, false);
    }

    private function loadOptionTypesByKey(array $keys): array
    {
        $query = $this->modx->newQuery(msOption::class);
        $query->select(['key', 'type']);
        $query->where(['key:IN' => $keys]);

        if (!$query->prepare() || !$query->stmt->execute()) {
            return [];
        }

        $typesByKey = [];
        while ($row = $query->stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (is_string($row['key'] ?? null) && is_string($row['type'] ?? null)) {
                $typesByKey[$row['key']] = $row['type'];
            }
        }

        return $typesByKey;
    }

    private function processGallery(array $resource, array $gallery): void
    {
        if (empty($gallery)) {
            return;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, "Importing images: \n" . print_r($gallery, 1));

        foreach ($gallery as $v) {
            if (empty($v)) {
                continue;
            }
            $image = str_replace('//', '/', MODX_BASE_PATH . $v);
            if (!file_exists($image)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "Could not import image \"$v\" to gallery. File \"$image\" not found on server."
                );
                continue;
            }

            $response = $this->modx->runProcessor(
                'MiniShop3\\Processors\\Gallery\\Upload',
                ['id' => $resource['id'], 'name' => $v, 'file' => $image],
                ['processors_path' => MODX_CORE_PATH . 'components/minishop3/src/Processors/']
            );

            if ($response->isError()) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    "Error on upload \"$v\": \n" . print_r($response->getAllErrors(), 1)
                );
            } else {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    "Successful upload  \"$v\": \n" . print_r($response->getObject(), 1)
                );
            }
        }
    }

    /**
     * Count rows in CSV file
     */
    public static function countRows(string $filePath, string $delimiter = ';'): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }

        $count = 0;
        $handle = fopen($filePath, 'r');
        while (fgetcsv($handle, 0, $delimiter) !== false) {
            $count++;
        }
        fclose($handle);

        return $count;
    }

    /**
     * Get preview of CSV file (first N rows)
     * @return array ['rows' => array, 'encoding' => string]
     */
    public static function getPreview(string $filePath, string $delimiter = ';', int $rows = 5, bool $skipHeader = false): array
    {
        if (!file_exists($filePath)) {
            return ['rows' => [], 'encoding' => null];
        }

        // Read file content for encoding detection and conversion
        $content = file_get_contents($filePath);
        $originalEncoding = self::detectEncoding($content);

        // Remove BOM and convert to UTF-8
        $content = self::removeBom($content);
        if (strtoupper($originalEncoding) !== 'UTF-8') {
            $content = self::convertToUtf8($content, $originalEncoding);
        }

        // Parse CSV from converted content
        $preview = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headerSkipped = false;

        while (($csv = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($skipHeader && !$headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            $preview[] = $csv;

            if (count($preview) >= $rows) {
                break;
            }
        }
        fclose($handle);

        return [
            'rows' => $preview,
            'encoding' => $originalEncoding,
        ];
    }

    /**
     * Detect CSV headers (first row)
     */
    public static function detectHeaders(string $filePath, string $delimiter = ';'): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath, false, null, 0, 8192);
        $content = self::removeBom($content);
        $content = self::convertToUtf8($content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        fclose($handle);

        return $headers ?: [];
    }

    /**
     * Detect file encoding
     * @return string Detected encoding (UTF-8, Windows-1251, etc.)
     */
    public static function detectEncoding(string $content): string
    {
        // Check for UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }

        // Check for UTF-16 BOM
        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            return 'UTF-16';
        }

        // Try to detect encoding
        // Order matters: check UTF-8 first, then Windows-1251
        $encodings = ['UTF-8', 'Windows-1251', 'KOI8-R', 'ISO-8859-5', 'ASCII'];

        // mb_detect_encoding with strict mode
        $detected = mb_detect_encoding($content, $encodings, true);

        if ($detected) {
            return $detected;
        }

        // Additional heuristic for Windows-1251 vs UTF-8
        // Windows-1251 Cyrillic range: 0xC0-0xFF
        // UTF-8 Cyrillic uses 2-byte sequences starting with 0xD0-0xD1
        $hasUtf8Cyrillic = preg_match('/[\xD0-\xD1][\x80-\xBF]/u', $content);
        $hasWin1251Cyrillic = preg_match('/[\xC0-\xFF]/', $content) && !$hasUtf8Cyrillic;

        if ($hasWin1251Cyrillic) {
            return 'Windows-1251';
        }

        if ($hasUtf8Cyrillic) {
            return 'UTF-8';
        }

        // Default to UTF-8
        return 'UTF-8';
    }

    /**
     * Remove BOM (Byte Order Mark) from content
     */
    public static function removeBom(string $content): string
    {
        // UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        // UTF-16 LE BOM
        if (str_starts_with($content, "\xFF\xFE")) {
            return substr($content, 2);
        }

        // UTF-16 BE BOM
        if (str_starts_with($content, "\xFE\xFF")) {
            return substr($content, 2);
        }

        return $content;
    }

    /**
     * Convert content to UTF-8
     */
    public static function convertToUtf8(string $content, ?string $fromEncoding = null): string
    {
        if ($fromEncoding === null) {
            $fromEncoding = self::detectEncoding($content);
        }

        // Already UTF-8
        if (strtoupper($fromEncoding) === 'UTF-8') {
            return $content;
        }

        // Convert using iconv (more reliable for Cyrillic)
        if (function_exists('iconv')) {
            $converted = @iconv($fromEncoding, 'UTF-8//TRANSLIT//IGNORE', $content);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Fallback to mb_convert_encoding
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($content, 'UTF-8', $fromEncoding);
        }

        return $content;
    }

    /**
     * Prepare file for import: detect encoding, remove BOM, convert to UTF-8
     * Creates a temporary file with normalized content
     * @return string Path to prepared file (may be same as original if already UTF-8)
     */
    private function prepareFile(string $filePath): string
    {
        // Read first chunk to detect encoding
        $handle = fopen($filePath, 'r');
        $sample = fread($handle, 8192);
        fclose($handle);

        // Remove BOM from sample for detection
        $sampleClean = self::removeBom($sample);
        $encoding = self::detectEncoding($sampleClean);
        $this->detectedEncoding = $encoding;

        $this->modx->log(modX::LOG_LEVEL_INFO, "[Import] Detected encoding: {$encoding}");

        // Check if file has BOM
        $hasBom = $sample !== $sampleClean;

        // If already UTF-8 without BOM, return original path
        if (strtoupper($encoding) === 'UTF-8' && !$hasBom) {
            return $filePath;
        }

        // Need to convert - create temp file
        $tempPath = sys_get_temp_dir() . '/ms3_import_' . uniqid() . '.csv';

        $this->modx->log(modX::LOG_LEVEL_INFO,
            "[Import] Converting file from {$encoding} to UTF-8" . ($hasBom ? ' (removing BOM)' : ''));

        $sourceHandle = fopen($filePath, 'r');
        $destHandle = fopen($tempPath, 'w');

        $isFirstChunk = true;
        while (!feof($sourceHandle)) {
            $chunk = fread($sourceHandle, 65536); // 64KB chunks

            // Remove BOM from first chunk
            if ($isFirstChunk) {
                $chunk = self::removeBom($chunk);
                $isFirstChunk = false;
            }

            // Convert encoding
            if (strtoupper($encoding) !== 'UTF-8') {
                $chunk = self::convertToUtf8($chunk, $encoding);
            }

            fwrite($destHandle, $chunk);
        }

        fclose($sourceHandle);
        fclose($destHandle);

        // Register temp file for cleanup
        register_shutdown_function(function () use ($tempPath) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        });

        return $tempPath;
    }

    /**
     * Get detected encoding of last processed file
     */
    public function getDetectedEncoding(): ?string
    {
        return $this->detectedEncoding;
    }
}
