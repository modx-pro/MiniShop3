<?php

use MODX\Revolution\Transport\modTransportPackage;
use MODX\Revolution\Transport\modTransportProvider;
use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var  modX $modx */
if (!$transport->xpdo || !($transport instanceof xPDOTransport)) {
    return false;
}

$modx = $transport->xpdo;
$packages = [
    'pdoTools' => [
        'version' => '3.0.2-pl',
        'service_url' => 'modstore.pro',
    ],
];

$downloadPackage = function ($src, $dst) {
    if (ini_get('allow_url_fopen')) {
        $file = @file_get_contents($src);
    } else {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $src);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            $safeMode = @ini_get('safe_mode');
            $openBasedir = @ini_get('open_basedir');
            if (empty($safeMode) && empty($openBasedir)) {
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            }

            $file = curl_exec($ch);
            curl_close($ch);
        } else {
            return false;
        }
    }
    file_put_contents($dst, $file);

    return file_exists($dst);
};

$installPackage = function ($packageName, $options = []) use ($modx, $downloadPackage) {
    /** @var modTransportProvider $provider */
    if (!empty($options['service_url'])) {
        $provider = $modx->getObject(modTransportProvider::class, [
            'service_url:LIKE' => '%' . $options['service_url'] . '%',
        ]);
    }
    if (empty($provider)) {
        $provider = $modx->getObject(modTransportProvider::class, 1);
    }
    $modx->getVersionData();
    $productVersion = $modx->version['code_name'] . '-' . $modx->version['full_version'];

    $response = $provider->request('package', 'GET', [
        'supports' => $productVersion,
        'query' => $packageName,
    ]);

    if (empty($response)) {
        return [
            'success' => 0,
            'message' => "Could not find <b>{$packageName}</b> in MODX repository",
        ];
    }

    $foundPackages = simplexml_load_string($response->getBody()->getContents());
    foreach ($foundPackages as $foundPackage) {
        /** @var modTransportPackage $foundPackage */
        /** @noinspection PhpUndefinedFieldInspection */
        if ((string)$foundPackage->name === $packageName) {
            $sig = explode('-', (string)$foundPackage->signature);
            $versionSignature = explode('.', $sig[1]);
            /** @var modTransportPackage $package */
            $package = $provider->transfer((string)$foundPackage->signature);
            if ($package && $package->install()) {
                return [
                    'success' => 1,
                    'message' => "<b>{$packageName}</b> was successfully installed",
                ];
            }
            return [
                'success' => 0,
                'message' => "Could not save package <b>{$packageName}</b>",
            ];
        }
    }

    return true;
};

$success = false;
switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        foreach ($packages as $name => $data) {
            if (!is_array($data)) {
                $data = ['version' => $data];
            }
            $installed = $modx->getIterator(modTransportPackage::class, ['package_name' => $name]);
            /** @var modTransportPackage $package */
            foreach ($installed as $package) {
                if ($package->compareVersion($data['version'], '<=')) {
                    continue(2);
                }
            }
            $modx->log(modX::LOG_LEVEL_INFO, "Trying to install <b>{$name}</b>. Please wait...");
            $response = $installPackage($name, $data);
            if (is_array($response)) {
                $level = $response['success']
                    ? modX::LOG_LEVEL_INFO
                    : modX::LOG_LEVEL_ERROR;
                $modx->log($level, $response['message']);
            }
        }
        $success = true;
        break;

    case xPDOTransport::ACTION_UNINSTALL:
        $success = true;
        break;
}

return $success;
