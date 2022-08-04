<?php

class msResourceCreateController extends ResourceCreateManagerController
{
    /** @var MiniShop3\MiniShop3 $ms3 */
    public $ms3;

    /**
     *
     */
    public function initialize()
    {
        $this->ms3 = $this->modx->services->get('ms3');
        $this->setContext();
        $this->modx->getUser($this->ctx, true);

        parent::initialize();
    }

    /**
     * @param string $script
     */
    public function addCss($script)
    {
        $script = $script . '?v=' . $this->ms3->version;
        parent::addCss($script);
    }

    /**
     * @param string $script
     */
    public function addJavascript($script)
    {
        $script = $script . '?v=' . $this->ms3->version;
        parent::addJavascript($script);
    }

    /**
     * @param string $script
     */
    public function addLastJavascript($script)
    {
        $script = $script . '?v=' . $this->ms3->version;
        parent::addLastJavascript($script);
    }

    /**
     * Check if content field is hidden
     * @return bool
     */
    public function isHideContent()
    {
        $userGroups = $this->modx->user->getUserGroups();
        $c = $this->modx->newQuery('modActionDom');
        $c->innerJoin('modFormCustomizationSet', 'FCSet');
        $c->innerJoin('modFormCustomizationProfile', 'Profile', 'FCSet.profile = Profile.id');
        $c->leftJoin('modFormCustomizationProfileUserGroup', 'ProfileUserGroup', 'Profile.id = ProfileUserGroup.profile');
        $c->leftJoin('modFormCustomizationProfile', 'UGProfile', 'UGProfile.id = ProfileUserGroup.profile');
        $c->where([
            'modActionDom.action:IN' => ['resource/*', 'resource/create'],
            'modActionDom.name' => 'modx-resource-content',
            'modActionDom.container' => 'modx-panel-resource',
            'modActionDom.rule' => 'fieldVisible',
            'modActionDom.active' => true,
            'FCSet.template:IN' => [0, $this->resource->template],
            'FCSet.active' => true,
            'Profile.active' => true,
        ]);
        $c->where([
            [
                'ProfileUserGroup.usergroup:IN' => $userGroups,
                [
                    'OR:ProfileUserGroup.usergroup:IS' => null,
                    'AND:UGProfile.active:=' => true,
                ],
            ],
            'OR:ProfileUserGroup.usergroup:=' => null,
        ], xPDOQuery::SQL_AND, null, 2);

        return (bool)$this->modx->getCount('modActionDom', $c);
    }

    /**
     * @param string $key
     * @param array $options
     * @param mixed $default
     * @return mixed
     */
    public function getOption($key, $options = null, $default = null, $skipEmpty = false)
    {
        $option = $default;
        if (!empty($key) and is_string($key)) {
            if (is_array($options) && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif ($options = $this->modx->_userConfig and array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif ($options = $this->context->config and array_key_exists($key, $options)) {
                $option = $options[$key];
            } else {
                $option = $this->modx->getOption($key);
            }
        }
        if ($skipEmpty and empty($option)) {
            $option = $default;
        }

        return $option;
    }
}
