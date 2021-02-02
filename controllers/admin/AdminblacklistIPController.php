<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class blacklistIP extends Module
{
    /**
     * Name of your ModuleAdminController
     */
    const MODULE_ADMIN_CONTROLLER = 'AdminbannedIP';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'banedIP';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Lukasz Kopciuch';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('bannedIP');
        $this->description = $this->l('PBanned IP');
    }

    /**
     * Install Module
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook($this->hooks)
            && $this->installTabs()
            ;
    }

    /**
     * Install Tabs
     *
     * @return bool
     */
    public function installTabs()
    {

        $tab = new Tab();
        $tab->class_name = static::MODULE_ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = -1;
        $tab->name = array_fill_keys(
            Language::getIDs(false),
            $this->displayName
        );

        return $tab->add();
    }

    /**
     * Uninstall Module
     *
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTabs()
            ;
    }

    /**
     * Uninstall Tabs
     *
     * @return bool
     */
    public function uninstallTabs()
    {
        $id_tab = (int) Tab::getIdFromClassName(static::MODULE_ADMIN_CONTROLLER);

        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }

        return true;
    }

    /**
     * Redirect to your ModuleAdminController when click on Configure button
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(static::MODULE_ADMIN_CONTROLLER));
    }

    /**
     * Display something on homepage
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayHome($params)
    {
        $this->smarty->assign(array(
            'hook_name' => 'hookDisplayHome',
        ));

        return $this->display(__FILE__, 'displayHome.tpl');
    }
}
