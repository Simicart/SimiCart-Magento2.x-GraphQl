<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * StoreConfig field data provider, used for GraphQL request processing.
 */
class Simistoreconfigdataprovider extends DataProviderInterface
{


    public $storeManager;
    public $appScopeConfigInterface;
    public $currencyFactory;
    public $countryModelFactory;
    public $localeResolver;
    public $frmwCurrencyFactory;
    public $serializer;
    public $configArray;
    public $eventManager;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ScopeConfigInterface $appScopeConfigInterface,
        \Magento\Directory\Model\CountryFactory $countryModelFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Framework\CurrencyFactory $frmwCurrencyFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    ) {
        $this->storeManager = $storeManager;
        $this->appScopeConfigInterface = $appScopeConfigInterface;
        $this->countryModelFactory = $countryModelFactory;
        $this->currencyFactory = $currencyFactory;
        $this->localeResolver = $localeResolver;
        $this->customerFactory = $customerFactory;
        $this->frmwCurrencyFactory = $frmwCurrencyFactory;
        $this->eventManager = $eventManager;
        $this->serializer = $serializer;
    }

    /**
     * Get store config data
     *
     * @return array
     */
    public function getSimiStoreConfigData($args){
        $storeManager = $this->storeManager;
        $country_code = $this->getStoreConfig('general/country/default');
        $country      = $this->countryModelFactory->create()->loadByCode($country_code);

        $currencyCode   = $this->storeManager->getStore()->getCurrentCurrencyCode();
        $currency       = $this->currencyFactory->create()->load($currencyCode);
        $currencySymbol = $currency->getCurrencySymbol();

        $genderOptions        = $this->customerFactory->create()
                ->getAttribute('gender')->getSource()->getAllOptions();
        $genderValues = [];
        foreach ($genderOptions as $genderOption) {
            if ($genderOption['value']) {
                $genderValues[] = [
                    'label' => $genderOption['label'],
                    'value' => $genderOption['value'],
                ];
            }
        }

        $configArray = array(
            'base'              => [
                'country_code'           => $country->getId(),
                'country_name'           => $country->getName(),
                'magento_version'        => '2',
                'locale_identifier'      => $this->getStoreConfig('general/locale/code'),
                'store_id'               => $this->storeManager->getStore()->getId(),
                'store_name'             => $this->storeManager->getStore()->getName(),
                'store_code'             => $this->storeManager->getStore()->getCode(),
                'group_id'               => $this->storeManager->getStore()->getGroupId(),
                'base_url'               => $this->getStoreConfig('simiconnector/general/base_url'),
                'use_store'              => $this->getStoreConfig('web/url/use_store'),
                'is_rtl'                 => $this->getStoreConfig('simiconnector/general/is_rtl'),
                'is_show_sample_data'    => $this->getStoreConfig('simiconnector/general/is_show_sample_data'),
                'android_sender'         => $this->getStoreConfig('simi_notifications/notification/android_app_key'),
                'currency_symbol'        => $currencySymbol,
                'currency_code'          => $currencyCode,
                'currency_position'      => $this->getCurrencyPosition(),
                'thousand_separator'     => $this->getStoreConfig('simiconnector/currency/thousand_separator'),
                'decimal_separator'      => $this->getStoreConfig('simiconnector/currency/decimal_separator'),
                'min_number_of_decimals' => $this->getStoreConfig('simiconnector/currency/min_number_of_decimals'),
                'max_number_of_decimals' => $this->getStoreConfig('simiconnector/currency/max_number_of_decimals'),
                'currencies'             => $this->getCurrencies(),
                'is_show_home_title'     => $this->getStoreConfig('simiconnector/general/is_show_home_title'),
                'is_show_in_row_price' => $this->getStoreConfig('simiconnector/config_price/price_one_row'),
                'is_show_price_for_guest' => $this->getStoreConfig('simiconnector/config_price/is_show_price_for_guest'),
                'open_url_in_app' => $this->getStoreConfig('simiconnector/general/open_url_in_app'),
                'image_aspect_ratio' => $this->getStoreConfig('simiconnector/general/image_aspect_ratio'),
                'is_support_put' => $this->getStoreConfig('simiconnector/methods_support/put'),
                'is_support_delete' => $this->getStoreConfig('simiconnector/methods_support/delete'),
                'default_title' => $this->getStoreConfig('design/head/default_title'),
                'default_description' => $this->getStoreConfig('design/head/default_description'),
                'title_prefix' => $this->getStoreConfig('design/head/title_prefix'),
                'title_suffix' => $this->getStoreConfig('design/head/title_suffix'),
                'default_keywords' => $this->getStoreConfig('design/head/default_keywords'),
            ],
            'sales'             => [
                'sales_reorder_allow'           => $this->getStoreConfig('sales/reorder/allow'),
                'sales_totals_sort_subtotal'    => $this->getStoreConfig('sales/totals_sort/subtotal'),
                'sales_totals_sort_discount'    => $this->getStoreConfig('sales/totals_sort/discount'),
                'sales_totals_sort_shipping'    => $this->getStoreConfig('sales/totals_sort/shipping'),
                'sales_totals_sort_weee'        => $this->getStoreConfig('sales/totals_sort/weee'),
                'sales_totals_sort_tax'         => $this->getStoreConfig('sales/totals_sort/tax'),
                'sales_totals_sort_grand_total' => $this->getStoreConfig('sales/totals_sort/grand_total'),
            ],
            'checkout'          => [
                'enable_guest_checkout' => $this->getStoreConfig('checkout/options/guest_checkout'),
                'enable_agreements'     => ($this->getStoreConfig('checkout/options/enable_agreements') === null) ?
                0 : $this->getStoreConfig('checkout/options/enable_agreements'),
                'checkout_webview' => [
                    'enable' => $this->getStoreConfig('simiconnector/checkout_config/enable_checkout_webview'),
                    'checkout_url' => $this->getStoreConfig('simiconnector/checkout_config/checkout_page_url'),
                    'success_url' => $this->getStoreConfig('simiconnector/checkout_config/url_success'),
                    'fail_url' => $this->getStoreConfig('simiconnector/checkout_config/url_failed'),
                ],
            ],
            'tax'               => [
                'tax_display_type'               => $this->getStoreConfig('tax/display/type'),
                'tax_display_shipping'           => $this->getStoreConfig('tax/display/shipping'),
                'tax_cart_display_price'         => $this->getStoreConfig('tax/cart_display/price'),
                'tax_cart_display_subtotal'      => $this->getStoreConfig('tax/cart_display/subtotal'),
                'tax_cart_display_shipping'      => $this->getStoreConfig('tax/cart_display/shipping'),
                'tax_cart_display_grandtotal'    => $this->getStoreConfig('tax/cart_display/grandtotal'),
                'tax_cart_display_full_summary'  => $this->getStoreConfig('tax/cart_display/full_summary'),
                'tax_cart_display_zero_tax'      => $this->getStoreConfig('tax/cart_display/zero_tax'),
                'tax_sales_display_price'        => $this->getStoreConfig('tax/sales_display/price'),
                'tax_sales_display_subtotal'     => $this->getStoreConfig('tax/sales_display/subtotal'),
                'tax_sales_display_shipping'     => $this->getStoreConfig('tax/sales_display/shipping'),
                'tax_sales_display_grandtotal'   => $this->getStoreConfig('tax/sales_display/grandtotal'),
                'tax_sales_display_full_summary' => $this->getStoreConfig('tax/sales_display/full_summary'),
                'tax_sales_display_zero_tax'     => $this->getStoreConfig('tax/sales_display/zero_tax'),
            ],
            'customer'          => [
                'address_option' => [
                    'street_lines'    => $this->getStoreConfig('customer/address/street_lines'),
                    'prefix_show'     => $this->getStoreConfig('customer/address/prefix_show') ?
                $this->getStoreConfig('customer/address/prefix_show') : '',
                    'middlename_show' => $this->getStoreConfig('customer/address/middlename_show') ?
                $this->getStoreConfig('customer/address/middlename_show') : '',
                    'suffix_show'     => $this->getStoreConfig('customer/address/suffix_show') ?
                $this->getStoreConfig('customer/address/suffix_show') : '',
                    'dob_show'        => $this->getStoreConfig('customer/address/dob_show') ?
                $this->getStoreConfig('customer/address/dob_show') : '',
                    'taxvat_show'     => $this->getStoreConfig('customer/address/taxvat_show') ?
                $this->getStoreConfig('customer/address/taxvat_show') : '',
                    'gender_show'     => $this->getStoreConfig('customer/address/gender_show') ?
                $this->getStoreConfig('customer/address/gender_show') : '',
                    'gender_value'    => $genderValues,
                ],
                'account_option' => [
                    'taxvat_show' => $this->getStoreConfig('customer/create_account/vat_frontend_visibility'),
                ],
                'password_validation' => $this->_passwordValidationConfiguration(),
                'address_fields_config' => [
                    "enable" => $this->getStoreConfig('simiconnector/hideaddress/hideaddress_enable'),
                    "company_show"=> $this->getStoreConfig('simiconnector/hideaddress/company'),
                    "street_show"=> $this->getStoreConfig('simiconnector/hideaddress/street'),
                    "country_id_show"=> $this->getStoreConfig('simiconnector/hideaddress/country_id'),
                    "region_id_show"=> $this->getStoreConfig('simiconnector/hideaddress/region_id'),
                    "city_show"=> $this->getStoreConfig('simiconnector/hideaddress/city'),
                    "zipcode_show"=> $this->getStoreConfig('simiconnector/hideaddress/zipcode'),
                    "telephone_show"=> $this->getStoreConfig('simiconnector/hideaddress/telephone'),
                    "fax_show"=> $this->getStoreConfig('simiconnector/hideaddress/fax'),
                    "prefix_show"=> $this->getStoreConfig('simiconnector/hideaddress/prefix'),
                    "suffix_show"=> $this->getStoreConfig('simiconnector/hideaddress/suffix'),
                    "dob_show"=> $this->getStoreConfig('simiconnector/hideaddress/dob'),
                    "gender_show"=> $this->getStoreConfig('simiconnector/hideaddress/gender'),
                    "taxvat_show"=> $this->getStoreConfig('simiconnector/hideaddress/taxvat'),
                    "street_default"=> $this->getStoreConfig('simiconnector/hideaddress/street_default'),
                    "country_id_default"=> $this->getStoreConfig('simiconnector/hideaddress/country_id_default'),
                    "region_id_default"=> $this->getStoreConfig('simiconnector/hideaddress/region_id_default'),
                    "city_default"=> $this->getStoreConfig('simiconnector/hideaddress/city_default'),
                    "zipcode_default"=> $this->getStoreConfig('simiconnector/hideaddress/zipcode_default'),
                    "telephone_default"=> $this->getStoreConfig('simiconnector/hideaddress/telephone_default'),
                ],
            ],
            'catalog'           => [
                'seo' => [
                    'product_url_suffix' => $this
                        ->getStoreConfig('catalog/seo/product_url_suffix'),
                    'category_url_suffix' => $this
                        ->getStoreConfig('catalog/seo/category_url_suffix'),
                    'product_use_categories_inherit' => $this
                        ->getStoreConfig('catalog/seo/product_use_categories_inherit'),
                ],
                'frontend'         => [
                    'view_products_default'                  => $this
                ->getStoreConfig('simiconnector/general/show_product_type'),
                    'is_show_zero_price'                     => $this
                ->getStoreConfig('simiconnector/general/is_show_price_zero'),
                    'is_show_link_all_product'               => $this
                ->getStoreConfig('simiconnector/general/is_show_all_product'),
                    'catalog_frontend_list_mode'             => $this
                ->getStoreConfig('catalog/frontend/list_mode'),
                    'catalog_frontend_grid_per_page_values'  => $this
                ->getStoreConfig('catalog/frontend/grid_per_page_values'),
                    'catalog_frontend_list_per_page'         => $this
                ->getStoreConfig('catalog/frontend/list_per_page'),
                    'catalog_frontend_list_allow_all'        => $this
                ->getStoreConfig('catalog/frontend/list_allow_all'),
                    'catalog_frontend_default_sort_by'       => $this
                ->getStoreConfig('catalog/frontend/default_sort_by'),
                    'catalog_frontend_flat_catalog_category' => $this
                ->getStoreConfig('catalog/frontend/flat_catalog_category'),
                    'catalog_frontend_flat_catalog_product'  => $this
                ->getStoreConfig('catalog/frontend/flat_catalog_product'),
                    'catalog_frontend_parse_url_directives'  => $this
                ->getStoreConfig('catalog/frontend/parse_url_directives'),
                    'show_discount_label_in_product'         => $this
                ->getStoreConfig('simiconnector/general/show_discount_label_in_product'),
                    'show_size_in_compare'                   => $this
                ->getStoreConfig('siminiaconfig/compareconfig/show_size_in_compare'),
                ],
                'cataloginventory' => [
                    'cataloginventory_item_options_manage_stock'          => $this
                ->getStoreConfig('cataloginventory/item_options/manage_stock'),
                    'cataloginventory_item_options_backorders'            => $this
                ->getStoreConfig('cataloginventory/item_options/backorders'),
                    'cataloginventory_item_options_max_sale_qty'          => $this
                ->getStoreConfig('cataloginventory/item_options/max_sale_qty'),
                    'cataloginventory_item_options_min_qty'               => $this
                ->getStoreConfig('cataloginventory/item_options/options_min_qty'),
                    'cataloginventory_item_options_min_sale_qty'          => $this
                ->getStoreConfig('cataloginventory/item_options/min_sale_qty'),
                    'cataloginventory_item_options_notify_stock_qty'      => $this
                ->getStoreConfig('cataloginventory/item_options/notify_stock_qty'),
                    'cataloginventory_item_options_enable_qty_increments' => $this
                ->getStoreConfig('cataloginventory/item_options/enable_qty_increments'),
                    'cataloginventory_item_options_qty_increments'        => $this
                ->getStoreConfig('cataloginventory/item_options/qty_increments'),
                    'cataloginventory_item_options_auto_return'           => $this
                ->getStoreConfig('cataloginventory/item_options/auto_return'),
                ],
                'review'           => [
                    'catalog_review_allow_guest' => $this->getStoreConfig('catalog/review/allow_guest'),
                ]
            ],
            'instant_contact' => [
                'email'       => explode(",", str_replace(' ', '', $this->getStoreConfig("simiconnector/instant_contact/email"))),
                'phone'       => explode(",", str_replace(' ', '', $this->getStoreConfig("simiconnector/instant_contact/phone"))),
                'message'     => explode(",", str_replace(' ', '', $this->getStoreConfig("simiconnector/instant_contact/message"))),
                'website'     => $this->getStoreConfig("simiconnector/instant_contact/website"),
                'style'       => $this->getStoreConfig("simiconnector/instant_contact/style"),
                'activecolor' => $this->getStoreConfig("simiconnector/instant_contact/icon_color"),
            ]
        );


        if ($this->serializer) {
            $contactEmails = $this->getStoreConfig('siminiaconfig/contactus/email');
            if ($contactEmails) {
                $contactEmails = $this->serializer->unserialize($contactEmails);
                $arrayContactEmails = [];
                foreach ($contactEmails as $contactEmail) {
                    $arrayContactEmails[] = $contactEmail;
                }
            }
            $contactHotlines = $this->getStoreConfig('siminiaconfig/contactus/hotline');
            if ($contactHotlines) {
                $contactHotlines = $this->serializer->unserialize($contactHotlines);
                $arrayContactHotlines = [];
                foreach ($contactHotlines as $contactHotline) {
                    $arrayContactHotlines[] = $contactHotline;
                }
            }
            $contactSms = $this->getStoreConfig('siminiaconfig/contactus/sms');
            if ($contactSms) {
                $contactSms = $this->serializer->unserialize($contactSms);
                $arrayContactSms = [];
                foreach ($contactSms as $contactSmsIt) {
                    $arrayContactSms[] = $contactSmsIt;
                }
            }
            $contactWebsites = $this->getStoreConfig('siminiaconfig/contactus/website');
            if ($contactWebsites) {
                $contactWebsites = $this->serializer->unserialize($contactWebsites);
                $arrayContactWebsites = [];
                foreach ($contactWebsites as $contactWebsite) {
                    $arrayContactWebsites[] = $contactWebsite;
                }
            }
            $configArray['pwacontactus'] = [
                'listEmail' => $arrayContactEmails,
                'listHotline' => $arrayContactHotlines,
                'listSms' => $arrayContactSms,
                'listWebsite' => $arrayContactWebsites
            ];
        }
        if ($this->getStoreConfig('simiconnector/terms_conditions/enable_terms')) {
            $configArray['checkout_terms_and_conditions'] = [
                'title' => $this->getStoreConfig('simiconnector/terms_conditions/term_title'),
                'content' => $this->getStoreConfig('simiconnector/terms_conditions/term_html'),
            ];
        }
        $this->configArray = $configArray;
        $this->eventManager
                ->dispatch('simiconnectorgrapqhl_get_storeview_info_after', ['object' => $this]);

        return array(
            'store_id' => (int)$storeManager->getStore()->getId(),
            'currency' => $storeManager->getStore()->getCurrentCurrencyCode(),
            'root_category_id' => (int)$storeManager->getStore()->getRootCategoryId(),
            'pwa_studio_client_ver_number' => $this->appScopeConfigInterface
                ->getValue('simiconnector/general/pwa_studio_client_ver_number'),
            'config' => $this->configArray,
        );
    }

    public function getCurrencyPosition()
    {
        $formated   = $this->storeManager->getStore()->getCurrentCurrency()->formatTxt(0);
        $number     = $this->storeManager->getStore()->getCurrentCurrency()
                ->formatTxt(0, ['display' => \Magento\Framework\Currency::NO_SYMBOL]);
        $ar_curreny = explode($number, $formated);
        if ($ar_curreny['0'] != '') {
            return 'before';
        }
        return 'after';
    }


    public function getCurrencies()
    {
        $currencies = [];
        $codes      = $this->storeManager->getStore()->getAvailableCurrencyCodes(true);
        $locale     = $this->localeResolver->getLocale();
        foreach ($codes as $code) {
            $currencyTitle = '';
            try {
                $options    = $this->frmwCurrencyFactory->create([null, $locale]);
                $currencyTitle = $options->getName($code, $locale);
            } catch (\Exception $e) {
                $currencyTitle = $code;
            }
            $currencies[] = [
                'value' => $code,
                'title' => $currencyTitle,
            ];
        }
        
        return $currencies;
    }


    private function getStoreConfig($path)
    {
        return $this->appScopeConfigInterface
            ->getValue($path,\Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->storeManager->getStore()->getCode());
    }


    private function _passwordValidationConfiguration(){
        $result = [];
        $result['minimum_password_length'] = $this->getStoreConfig('customer/password/minimum_password_length');
        $result['required_character_classes_number'] = $this->getStoreConfig('customer/password/required_character_classes_number');
        return $result;
    }
}
