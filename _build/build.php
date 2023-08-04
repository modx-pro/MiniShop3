<?php

use MODX\Revolution\modAccessPermission;
use MODX\Revolution\modAccessPolicy;
use MODX\Revolution\modAccessPolicyTemplate;
use MODX\Revolution\modCategory;
use MODX\Revolution\modChunk;
use MODX\Revolution\modEvent;
use MODX\Revolution\modMenu;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modSnippet;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use MODX\Revolution\Transport\modTransportPackage;
use xPDO\Transport\xPDOTransport;

class MiniShop3Package
{
    private $modx;
    private $config = [];
    private $category;
    private $category_attributes = [];

    /**
     * @var modPackageBuilder;
     */
    public $builder;

    /**
     * MiniShop3Package constructor.
     *
     * @param modX $modx
     * @param array $config
     */
    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $this->modx->initialize('mgr');

        $root = dirname(__FILE__, 2) . '/';
        $core = $root . 'core/components/' . $config['name_lower'] . '/';
        $assets = $root . 'assets/components/' . $config['name_lower'] . '/';

        $this->config = array_merge([
            'log_level' => modX::LOG_LEVEL_INFO,
            'log_target' => XPDO_CLI_MODE ? 'ECHO' : 'HTML',

            'root' => $root,
            'build' => $root . '_build/',
            'elements' => $root . '_build/elements/',
            'resolvers' => $root . '_build/resolvers/',
            'core' => $core,
            'assets' => $assets,
        ], $config);
        $this->modx->setLogLevel($this->config['log_level']);
        $this->modx->setLogTarget($this->config['log_target']);

        $this->initialize();
    }

    /**
     * @return modPackageBuilder
     */
    public function process()
    {
        //$this->buildModel();

        // Add elements
        $elements = scandir($this->config['elements']);
        foreach ($elements as $element) {
            if (in_array($element[0], ['_', '.'])) {
                continue;
            }
            $name = preg_replace('#\.php$#', '', $element);
            if (method_exists($this, $name)) {
                $this->{$name}();
            }
        }

        // Create main vehicle
        $vehicle = $this->builder->createVehicle($this->category, $this->category_attributes);

        // Files resolvers
        $vehicle->resolve('file', [
            'source' => $this->config['core'],
            'target' => "return MODX_CORE_PATH . 'components/';",
        ]);
        $vehicle->resolve('file', [
            'source' => $this->config['assets'],
            'target' => "return MODX_ASSETS_PATH . 'components/';",
        ]);

        // Add resolvers into vehicle
        $resolvers = scandir($this->config['resolvers']);
        foreach ($resolvers as $resolver) {
            if (in_array($resolver[0], ['_', '.'])) {
                continue;
            }
            if ($vehicle->resolve('php', ['source' => $this->config['resolvers'] . $resolver])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Added resolver ' . preg_replace('#\.php$#', '', $resolver));
            }
        }

        $this->builder->putVehicle($vehicle);

        $this->builder->setPackageAttributes([
            'changelog' => file_get_contents($this->config['core'] . 'docs/changelog.txt'),
            'license' => file_get_contents($this->config['core'] . 'docs/license.txt'),
            'readme' => file_get_contents($this->config['core'] . 'docs/readme.txt'),
            'requires' => [
                'php' => '>=8.1.0',
                'modx' => '>=3.0.3',
                //'pdoTools' => '>=3.0.0'
            ],
        ]);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added package attributes and setup options.');

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
        $this->builder->pack();

        if (!empty($this->config['install'])) {
            $this->install();
        }

        return $this->builder;
    }

    /**
     * Initialize package builder
     */
    private function initialize()
    {
        $this->builder = new modPackageBuilder($this->modx);
        $this->builder->createPackage($this->config['name_lower'], $this->config['version'], $this->config['release']);
        $this->builder->registerNamespace($this->config['name_lower'], false, true, '{core_path}components/' . $this->config['name_lower'] . '/');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created Transport Package and Namespace.');

        $this->category = $this->modx->newObject(modCategory::class);
        $this->category->set('category', $this->config['name']);
        $this->category_attributes = [
            xPDOTransport::UNIQUE_KEY => 'category',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [],
        ];
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created main Category.');
    }

    /**
     * Update the model
     */
    private function buildModel()
    {
        $schemaFile = $this->config['core'] . 'schema/' . $this->config['name_lower'] . '.mysql.schema.xml';
        $outputDir = $this->config['core'] . 'src/';
        if (!file_exists($schemaFile) || empty(file_get_contents($schemaFile))) {
            return;
        }

        $manager = $this->modx->getManager();
        $generator = $manager->getGenerator();
        $generator->parseSchema(
            $schemaFile,
            $outputDir,
            [
                "compile" => 0,
                "update" => 0,
                "regenerate" => 1,
                "namespacePrefix" => "MiniShop3\\"
            ]
        );
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Model updated');
    }

    /**
     *  Install package
     */
    private function install()
    {
        $signature = $this->builder->getSignature();
        $sig = explode('-', $signature);
        $versionSignature = explode('.', $sig[1]);

        $this->modx->log(3, 'signature: ' . $signature);

        /** @var modTransportPackage $package */
        $package = $this->modx->getObject(modTransportPackage::class, ['signature' => $signature]);
        if (!$package) {
            $package = $this->modx->newObject(modTransportPackage::class);
            $package->set('signature', $signature);
            $package->fromArray([
                'created' => date('Y-m-d h:i:s'),
                'updated' => null,
                'state' => 1,
                'workspace' => 1,
                'provider' => 0,
                'source' => $signature . '.transport.zip',
                'package_name' => $this->config['name'],
                'version_major' => $versionSignature[0],
                'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
                'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
            ]);
            if (!empty($sig[2])) {
                $r = preg_split('#([0-9]+)#', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($r) && !empty($r)) {
                    $package->set('release', $r[0]);
                    $package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
                } else {
                    $package->set('release', $sig[2]);
                }
            }
            $package->save();
        }

        //$package->xpdo->packages['MODX\Revolution\\'] = $package->xpdo->packages['Revolution'];
        if ($package->install()) {
            $this->modx->runProcessor('System/ClearCache');
        }
    }

    /**
     * Add settings
     */
    private function settings()
    {
        /** @noinspection PhpIncludeInspection */
        $settings = include($this->config['elements'] . 'settings.php');
        if (!is_array($settings)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in System Settings');
            return;
        }
        $attributes = [
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['settings']),
            xPDOTransport::RELATED_OBJECTS => false,
        ];
        foreach ($settings as $name => $data) {
            /** @var modSystemSetting $setting */
            $setting = $this->modx->newObject(modSystemSetting::class);
            $setting->fromArray(array_merge([
                'key' => $name,
                'namespace' => $this->config['name_lower'],
            ], $data), '', true, true);
            $vehicle = $this->builder->createVehicle($setting, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($settings) . ' System Settings');
    }

    /**
     * Add menus
     */
    private function menus()
    {
        /** @noinspection PhpIncludeInspection */
        $menus = include($this->config['elements'] . 'menus.php');
        if (!is_array($menus)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Menus');

            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['menus']),
            xPDOTransport::UNIQUE_KEY => 'text',
            xPDOTransport::RELATED_OBJECTS => true,
        ];
        foreach ($menus as $name => $data) {
            /** @var modMenu $menu */
            $menu = $this->modx->newObject(modMenu::class);
            $menu->fromArray(array_merge([
                'text' => $name,
                'parent' => 'components',
                'namespace' => $this->config['name_lower'],
                'icon' => '',
                'menuindex' => 0,
                'params' => '',
                'handler' => '',
            ], $data), '', true, true);
            $vehicle = $this->builder->createVehicle($menu, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($menus) . ' Menus');
    }

    /**
     * Add plugins
     */
    private function plugins()
    {
        /** @noinspection PhpIncludeInspection */
        $plugins = include($this->config['elements'] . 'plugins.php');
        if (!is_array($plugins)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Plugins');

            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Plugins'] = [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['plugins']),
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                ],
            ],
        ];
        $objects = [];
        foreach ($plugins as $name => $data) {
            /** @var modPlugin $plugin */
            $plugin = $this->modx->newObject(modPlugin::class);
            $filepath = $this->config['core'] . '/elements/plugins/' . $data['file'] . '.php';
            $plugin->fromArray(array_merge([
                'name' => $name,
                'category' => 0,
                'description' => @$data['description'],
                'plugincode' => $this::getFileContent($filepath),
                'static' => !empty($this->config['static']['plugins']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/plugins/' . $data['file'] . '.php',
            ], $data), '', true, true);

            $events = [];
            if (!empty($data['events'])) {
                foreach ($data['events'] as $event_name) {
                    /** @var modPluginEvent $event */
                    $event = $this->modx->newObject(modPluginEvent::class);
                    $event->fromArray([
                        'event' => $event_name,
                        'priority' => 0,
                        'propertyset' => 0,
                    ], '', true, true);
                    $events[] = $event;
                }
            }
            if (!empty($events)) {
                $plugin->addMany($events);
            }
            $objects[] = $plugin;
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Plugins');
    }

    /**
     * Add Events
     */
    public function events()
    {
        $events = include($this->config['elements'] . 'events.php');
        if (!is_array($events)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Events');
            return;
        }

        $attributes = [
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => $this->config['update']['events'],
        ];
        foreach ($events as $name) {
            $event = $this->modx->newObject(modEvent::class);
            $event->fromArray([
                'name' => $name,
                'service' => 6,
                'groupname' => $this->config['name'],
            ], '', true, true);
            //$objects[] = $event;
            $vehicle = $this->builder->createVehicle($event, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($events) . ' Events');
    }

    /**
     * Add snippets
     */
    private function snippets()
    {
        /** @noinspection PhpIncludeInspection */
        $snippets = include($this->config['elements'] . 'snippets.php');
        if (!is_array($snippets)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Snippets');
            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Snippets'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['snippets']),
            xPDOTransport::UNIQUE_KEY => 'name',
        ];
        $objects = [];
        foreach ($snippets as $name => $data) {
            /** @var modSnippet $snippet */
            $objects[$name] = $this->modx->newObject(modSnippet::class);
            $filepath = $this->config['core'] . '/elements/snippets/' . $data['file'] . '.php';
            $objects[$name]->fromArray(array_merge([
                'id' => 0,
                'name' => $name,
                'description' => @$data['description'],
                'snippet' => $this::getFileContent($filepath),
                'static' => !empty($this->config['static']['snippets']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/snippets/' . $data['file'] . '.php',
            ], $data), '', true, true);
            $properties = [];
            foreach (@$data['properties'] as $k => $v) {
                $properties[] = array_merge([
                    'name' => $k,
                    'desc' => 'ms_prop_' . $k,
                    'lexicon' => 'minishop3:properties',
                ], $v);
            }
            $objects[$name]->setProperties($properties);
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Snippets');
    }

    /**
     * Add chunks
     */
    private function chunks()
    {
        /** @noinspection PhpIncludeInspection */
        $chunks = include($this->config['elements'] . 'chunks.php');
        if (!is_array($chunks)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Chunks');

            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Chunks'] = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['chunks']),
            xPDOTransport::UNIQUE_KEY => 'name',
        ];
        $objects = [];
        foreach ($chunks as $name => $file) {
            /** @var modChunk[] $objects */
            $objects[$name] = $this->modx->newObject(modChunk::class);
            $filepath = $this->config['core'] . '/elements/chunks/' . $file . '.tpl';
            $objects[$name]->fromArray([
                'id' => 0,
                'name' => $name,
                'description' => '',
                'snippet' => $this::getFileContent($filepath),
                'static' => !empty($this->config['static']['chunks']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/chunks/' . $file . '.tpl',
            ], '', true, true);
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Chunks');
    }

    /**
     * Add access policy
     */
    private function policies()
    {
        /** @noinspection PhpIncludeInspection */
        $policies = include($this->config['elements'] . 'policies.php');
        if (!is_array($policies)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Access Policies');
            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UNIQUE_KEY => ['name'],
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['policies']),
        ];
        foreach ($policies as $name => $data) {
            if (isset($data['data'])) {
                $data['data'] = json_encode($data['data']);
            }
            /** @var $policy modAccessPolicy */
            $policy = $this->modx->newObject(modAccessPolicy::class);
            $policy->fromArray(array_merge([
                    'name' => $name,
                    'lexicon' => $this->config['name_lower'] . ':permissions',
                ], $data)
                , '', true, true);
            $vehicle = $this->builder->createVehicle($policy, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($policies) . ' Access Policies');
    }

    /**
     * Add policy templates
     */
    private function policyTemplates()
    {
        /** @noinspection PhpIncludeInspection */
        $policy_templates = include($this->config['elements'] . 'policyTemplates.php');
        if (!is_array($policy_templates)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Policy Templates');
            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UNIQUE_KEY => ['name'],
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['policy_templates']),
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'Permissions' => [
                    xPDOTransport::PRESERVE_KEYS => false,
                    xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['permission']),
                    xPDOTransport::UNIQUE_KEY => ['template', 'name'],
                ],
            ],
        ];
        foreach ($policy_templates as $name => $data) {
            $permissions = [];
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                foreach ($data['permissions'] as $name2 => $data2) {
                    /** @var $permission modAccessPermission */
                    $permission = $this->modx->newObject(modAccessPermission::class);
                    $permission->fromArray(array_merge([
                            'name' => $name2,
                            'description' => $name2,
                            'value' => true,
                        ], $data2)
                        , '', true, true);
                    $permissions[] = $permission;
                }
            }
            /** @var $permission modAccessPolicyTemplate */
            $permission = $this->modx->newObject(modAccessPolicyTemplate::class);
            $permission->fromArray(array_merge([
                    'name' => $name,
                    'lexicon' => $this->config['name_lower'] . ':permissions',
                ], $data)
                , '', true, true);
            if (!empty($permissions)) {
                $permission->addMany($permissions);
            }
            $vehicle = $this->builder->createVehicle($permission, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($policy_templates) . ' Access Policy Templates');
    }

    /**
     * @param $filename
     *
     * @return string
     */
    private function getFileContent($filename)
    {
        if (file_exists($filename)) {
            $file = trim(file_get_contents($filename));

            return preg_match('#\<\?php(.*)#is', $file, $data)
                ? rtrim(rtrim(trim(@$data[1]), '?>'))
                : $file;
        }

        return '';
    }
}

/** @var array $config */
if (!file_exists(dirname(__FILE__) . '/config.inc.php')) {
    exit('Could not load MODX config. Please specify correct MODX_CORE_PATH constant in config file!');
}
$config = require(dirname(__FILE__) . '/config.inc.php');
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$modx = new modX();
$install = new MiniShop3Package($modx, $config);
$builder = $install->process();

if (!empty($config['download'])) {
    $name = $builder->getSignature() . '.transport.zip';
    if ($content = file_get_contents(MODX_CORE_PATH . '/packages/' . $name)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        exit($content);
    }
}
