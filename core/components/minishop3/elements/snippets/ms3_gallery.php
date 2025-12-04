    <?php

    use MiniShop3\Model\msProduct;
    use MiniShop3\Model\msProductData;
    use MiniShop3\Model\msProductFile;
    use MODX\Revolution\modX;
    use MiniShop3\MiniShop3;
    use ModxPro\PdoTools\Fetch;

    /** @var modX $modx */
    /** @var array $scriptProperties */
    /** @var MiniShop3 $ms3 */

    $ms3 = $modx->services->get('ms3');
    $ms3->initialize($modx->context->key);
    $pdoFetch = $modx->services->get(Fetch::class);
    $pdoFetch->addTime('pdoTools loaded.');

    $extensionsDir = $modx->getOption('extensionsDir', $scriptProperties, 'components/minishop3/img/mgr/extensions/', true);
    $limit = $modx->getOption('limit', $scriptProperties, 0);
    $tpl = $modx->getOption('tpl', $scriptProperties, 'tpl.msGallery');
    $thumbnailsFilter = $modx->getOption('thumbnails', $scriptProperties, '');

    /** @var msProduct $product */
    $product = !empty($product) && $product != $modx->resource->id
        ? $modx->getObject(msProduct::class, ['id' => $product])
        : $modx->resource;
    if (!($product instanceof msProduct)) {
        $modx->log(modX::LOG_LEVEL_ERROR, "[msGallery] Resource {$product->id} is not msProduct");
        return '';
    }

    $where = [
        'product_id' => $product->id,
        'parent_id' => 0,
    ];
    if (!empty($filetype)) {
        $where['type:IN'] = array_map('trim', explode(',', $filetype));
    }
    if (empty($showInactive)) {
        $where['active'] = 1;
    }
    $select = [
        'msProductFile' => $modx->getSelectColumns(msProductFile::class, 'msProductFile')
    ];

    // Add user parameters
    foreach (['where'] as $v) {
        if (!empty($scriptProperties[$v])) {
            $tmp = $scriptProperties[$v];
            if (!is_array($tmp)) {
                $tmp = json_decode($tmp, true);
            }
            if (is_array($tmp)) {
                $$v = array_merge($$v, $tmp);
            }
        }
        unset($scriptProperties[$v]);
    }
    $pdoFetch->addTime('Conditions prepared');
    $default = [
        'class' => msProductFile::class,
        'where' => $where,
        'select' => $select,
        'limit' => $limit,
        'sortby' => '`position`',
        'sortdir' => 'ASC',
        'fastMode' => false,
        'return' => 'data',
        'nestedChunkPrefix' => 'minishop3_',
    ];
    $returnType = !empty($scriptProperties['return']) ? $scriptProperties['return'] : 'data';
    if ($returnType === 'tpl') {
        unset($scriptProperties['return']);
    }
    // Merge all properties and run!
    $pdoFetch->setConfig(array_merge($default, $scriptProperties), false);
    $rows = $pdoFetch->run();
    if ($returnType === 'sql' || $returnType === 'json') {
        return $rows;
    }
    $pdoFetch->addTime('Fetching thumbnails');

    $resolution = [];
    /** @var msProductData $data */
    if ($data = $product->getOne('Data')) {
        /** @var \MiniShop3\Services\Product\ProductImageService $imageService */
        $imageService = $modx->services->get('ms3_product_image');
        if ($imageService && $source = $imageService->initializeMediaSource($data, $modx->context->key)) {
            $properties = $source->getProperties();
            if (isset($properties['thumbnails']['value'])) {
                $fileTypes = json_decode($properties['thumbnails']['value'], true);
                foreach ($fileTypes as $k => $v) {
                    if (!is_numeric($k)) {
                        $resolution[] = $k;
                    }
                    elseif (!empty($v['name'])) {
                        $resolution[] = $v['name'];
                    }
                    elseif (isset($v['width']) || isset($v['height'])) {
                        $resolution[] = ($v['width'] ?? 0) . 'x' . ($v['height'] ?? 0);
                    }
                    elseif (isset($v['w']) || isset($v['h'])) {
                        $resolution[] = ($v['w'] ?? 0) . 'x' . ($v['h'] ?? 0);
                    }
                }
            }
        }
    }

    $imageIds = [];
    foreach ($rows as $row) {
        if (isset($row['type']) && $row['type'] == 'image') {
            $imageIds[] = $row['id'];
        }
    }

    $requestedSizes = [];
    if (!empty($thumbnailsFilter)) {
        $requestedSizes = array_map('trim', explode(',', $thumbnailsFilter));
    }

    $thumbnails = [];
    if (!empty($imageIds)) {
        $c = $modx->newQuery(msProductFile::class, ['parent_id:IN' => $imageIds]);
        $c->select('parent_id,product_id,url,path');
        $tstart = microtime(true);
        if ($c->prepare() && $c->stmt->execute()) {
            $modx->queryTime += microtime(true) - $tstart;
            $modx->executedQueries++;
            while ($thumb = $c->stmt->fetch(PDO::FETCH_ASSOC)) {
                if (preg_match("#/{$thumb['product_id']}/(.*?)/#", $thumb['url'], $size)) {
                    $thumbSize = $size[1];

                    if (!empty($requestedSizes) && !in_array($thumbSize, $requestedSizes)) {
                        continue;
                    }

                    if (!isset($thumbnails[$thumb['parent_id']])) {
                        $thumbnails[$thumb['parent_id']] = [];
                    }
                    $thumbnails[$thumb['parent_id']][$thumbSize] = $thumb['url'];
                }
            }
        }
    }
    $pdoFetch->addTime('Thumbnails loaded');

    $files = [];
    foreach ($rows as $row) {
        if (isset($row['type']) && $row['type'] == 'image') {
            if (isset($thumbnails[$row['id']])) {
                $row = array_merge($row, $thumbnails[$row['id']]);
            }
        } elseif (isset($row['type'])) {
            $row['thumbnail'] = file_exists(MODX_ASSETS_PATH . $extensionsDir . $row['type'] . '.png')
                ? MODX_ASSETS_URL . $extensionsDir . $row['type'] . '.png'
                : MODX_ASSETS_URL . $extensionsDir . 'other.png';
            foreach ($resolution as $v) {
                $row[$v] = $row['thumbnail'];
            }
        }

        $files[] = $row;
    }

    if ($returnType === 'data') {
        return $files;
    }

    $output = $pdoFetch->getChunk($tpl, [
        'files' => $files,
        'scriptProperties' => $scriptProperties
    ]);

    if ($modx->user->hasSessionContext('mgr') && !empty($showLog)) {
        $output .= '<pre class="msGalleryLog">' . print_r($pdoFetch->getTime(), 1) . '</pre>';
    }

    if (!empty($toPlaceholder)) {
        $modx->setPlaceholder($toPlaceholder, $output);
    } else {
        return $output;
    }
