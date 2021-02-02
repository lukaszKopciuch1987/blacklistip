<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Foundation\Database\EntityManager;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class blacklistip extends Module
{
    public function __construct($name = null, Context $context = null)
    {
        $this->name = 'blacklistip';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'Lukasz Kopciuch';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Blacklist IP');
        $this->description = $this->l('List of banned IPs');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('Blacklist_IP_URL')) {
            $this->warning = $this->l('Module instalation error');
        }
    }

    public function install(){
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install()
            && $this->installDB()
            && $this->registerHook('displayHeader')
            && Configuration::updateValue('Blacklist_IP_URL', 'url')
            ;

    }


    public function installDB(){

        $return = Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'blockedip` (
                `id_ip`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip`            VARCHAR(64) UNIQUE,
                `date_add`      DATETIME NOT NULL  DEFAULT CURRENT_TIMESTAMP,
                `quantity`      INT (11), 
                PRIMARY KEY (`id_ip`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;');


        return $return;
    }

    public function uninstall(){
        return Configuration::deleteByName('Blacklist_IP_URL') &&
            $this->uninstallDB() &&
            parent::uninstall();
    }

    public function uninstallDB(){
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'blockedip`');
    }

    public function hookDisplayHeader($params){

        if($ipValues = $this->checkIfExists($this->getUserIP())){
            $this->updateQuantity($ipValues);

            $values = unserialize(Configuration::get('Blacklist_IP_URL'));

            $id_lang = Context::getContext()->language->id;
            $url = null;
            if($values['CUSTOM_SITE_URL_'.$id_lang])
                $url = $values['CUSTOM_SITE_URL_'.$id_lang];
            else{
                $url = $this->context->link->getCMSLink($values['CMS_SITES']);
            }
            if($url){
                Tools::redirectLink($url);

            }

        }
    }

    public function  getUserIP() {
        // Get real visitor IP behind CloudFlare network
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                  $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
                  $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];

        if(filter_var($client, FILTER_VALIDATE_IP))
        {
            $ip = $client;
        }
        elseif(filter_var($forward, FILTER_VALIDATE_IP))
        {
            $ip = $forward;
        }
        else
        {
            $ip = $remote;
        }

        return $ip;
    }


    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitUpdate')) {

            $languages = Language::getLanguages(false);
            $valid = false;
            foreach($languages as $lang){
                $values['BIP_PAGE_TITLE_'.$lang['id_lang']] = Tools::getValue('BIP_PAGE_TITLE_'.$lang['id_lang']);
                $values['BIP_PAGE_DESCRIPTION_'.$lang['id_lang']] = Tools::getValue('BIP_PAGE_DESCRIPTION_'.$lang['id_lang']);
                $values['CUSTOM_SITE_URL_'.$lang['id_lang']] = Tools::getValue('CUSTOM_SITE_URL_'.$lang['id_lang']);
                if($values['CUSTOM_SITE_URL_'.$lang['id_lang']] )
                    $valid = true;
            }
            $values['CMS_SITES'] = Tools::getValue('CMS_SITES');
            if (!$valid && !$values['CMS_SITES'] ) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {

                Configuration::updateValue('Blacklist_IP_URL', serialize($values));
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        else if (Tools::isSubmit('submitBan')) {
           if(filter_var(Tools::getValue('BIP_IP'), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
               $output .= $this->displayError($this->l('Invalid IP address'));
           if($this->checkIfExists(Tools::getValue('BIP_IP')))
                $output .= $this->displayError($this->l('IP address already exists'));
            elseif(!$this->addIP(Tools::getValue('BIP_IP')))
                $output .= $this->displayError($this->l('Invalid IP address'));
            else
                $output .= $this->displayConfirmation($this->l('IP address added'));
        }
        elseif(Tools::getValue('id_ip')){
            $this->removeIP(Tools::getValue('id_ip'));
        }

        $this->_html .= $output;
        $this->_html .= $this->renderForm();
        $this->_html .=  $this->renderList();
        $this->_html .= $this->renderAddIPForm();

        return $this->_html;
    }

    public function getCMSNames(){
        $names = CMSCore::listCms();

        $sites_list = array(array('id' => 0, 'name' => $this->l('None')));
        foreach ($names as $name) {
            $sites_list[] = array('id' => $name['id_cms'], 'name' => $name['meta_title']);
        }

        return $sites_list;
    }

    public function getConfigFieldsValues()
    {
        $values = unserialize(Configuration::get('Blacklist_IP_URL'));

        $languages = Language::getLanguages(false);
        $titles = [];
        $descriptions = [];
        $customSites = [];

        foreach ($languages as $lang) {
            $titles[$lang['id_lang']] = $values['BIP_PAGE_TITLE_'.$lang['id_lang']] ;
            $descriptions[$lang['id_lang']] = $values['BIP_PAGE_DESCRIPTION_'.$lang['id_lang']];
            $customSites[$lang['id_lang']] = $values['Custom_Site_URL_'.$lang['id_lang']];
        }

        return [
            'CMS_SITES'             => $values['CMS_SITES'],
            'BIP_PAGE_TITLE'        => $titles,
            'BIP_PAGE_DESCRIPTION'  => $descriptions,
            'CUSTOM_SITE_URL'       => $customSites

        ];
    }

    public function renderForm()
    {
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));

        $cmsList = $this->getCMSNames();
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'name' => 'BIP_PAGE_TITLE',
                        'lang' => true,
                        'class' => 'fixed-width-xxl',
                        'hint' => $this->l('Page title'),
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Page description'),
                        'lang' => true,
                        'name' => 'BIP_PAGE_DESCRIPTION',
                        'cols' => 40,
                        'rows' => 100,
                        'hint' => $this->l('Page description'),

                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('CMS Sites'),
                        'desc' => $this->l('CMS Sites'),
                        'name' => 'CMS_SITES',
                        'required' => false,
                        'default_value' => (int) $this->context->country->id,
                        'options' => array(
                            'query' => $cmsList,
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Custom Site addres '),
                        'lang' => true,
                        'name' => 'CUSTOM_SITE_URL',
                        'value'=>Configuration::get('CUSTOM_SITE_URL_'.$lang->id),
                        'cols' => 40,
                        'rows' => 100,
                        'hint' => $this->l('Custom Site URL' ),
                        'desc' => $this->l('Leave blank to disable by default.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = true;
        $helper->table = $this->table;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitUpdate';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }


    public function renderList()
    {
        $fields_list = array(

            'id_ip' => array(
                'title' => $this->l('ID'),
                'search' => false,
            ),
            'ip' => array(
                'title' => $this->l('IP'),
                'search' => false,
            ),

            'date_add' => array(
                'title' => $this->l('Added on'),
                'type' => 'date',
                'search' => false,
            ),
            'quantity' => array(
                'title' => $this->l('Quantity'),
                'search' => false,
            ),
        );



        if (!Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            unset($fields_list['shop_name']);
        }

        $helper_list = new HelperList();
        $helper_list->module = $this;
        $helper_list->title = $this->l('Blocked IPs');
        $helper_list->shopLinkType = '';
        $helper_list->no_link = true;
        $helper_list->show_toolbar = true;
        $helper_list->simple_header = false;
        $helper_list->identifier = 'id_ip';
        $helper_list->table = 'blockedip';
        $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->actions = array( 'delete');

        $this->_helperlist = $helper_list;

        $ips = $this->getIps();
        $helper_list->listTotal = count($ips);


        $page = ($page = Tools::getValue('submitFilter' . $helper_list->table)) ? $page : 1;
        $pagination = ($pagination = Tools::getValue($helper_list->table . '_pagination')) ? $pagination : 50;
        $ips = $this->paginateSubscribers($ips, $page, $pagination);

        return $helper_list->generateList($ips, $fields_list);

    }

    public function getIps()
    {
        $dbquery = new DbQuery();
        $dbquery->select('*');
        $dbquery->from('blockedip');

        $ips = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());

        return $ips;
    }

    public function paginateSubscribers($ips, $page = 1, $pagination = 50)
    {
        if (count($ips) > $pagination) {
            $ips = array_slice($ips, $pagination * ($page - 1), $pagination);
        }

        return $ips;
    }

    public function renderAddIPForm(){

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Add IP'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('IP'),
                        'name' => 'BIP_IP',
                        'class' => 'fixed-width-xxl',
                        'hint' => $this->l('Ip Address to block'),
                    ),

                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = true;
        $helper->table = $this->table;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBan';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');


        return $helper->generateForm(array($fields_form));
    }

    private function removeIP($id){
        return(Db::getInstance()->delete('blockedip', 'id_ip ='.$id));
    }

    public function checkIfExists($ip_address){

        return Db::getInstance()->getRow('SELECT id_ip, quantity FROM '._DB_PREFIX_.'blockedip WHERE ip ="'.$ip_address.'"');

    }

    private function addIP($ip_address){

        return Db::getInstance()->insert('blockedip', [
            'ip'    => $ip_address
        ]);
    }
    private function updateQuantity($ip){
        $oldValue = Db::getInstance()->getValue('SELECT quantity FROM '._DB_PREFIX_.'blockedip WHERE ip = "'.$ip.'"');

        Db::getInstance()->update('blockedip', [
            'quantity'  => $oldValue +1
        ],
            'ip = '.$ip);
    }


}