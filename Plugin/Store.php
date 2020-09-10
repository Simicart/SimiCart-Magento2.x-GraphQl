<?php
namespace Simi\SimiconnectorGraphQl\Plugin;
use \Magento\Store\Model\StoreManagerInterface;

class Store
{
    private $storeManager;
    private $request;

    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->storeManager = $storeManager;
        $this->request = $request;
    }


    public function aroundGetCurrentCurrencyCode($subject, $proceed)
    {
        $request = $this->request;
        $pathUri = $request->getRequestString();
        if(strpos($pathUri, 'graphql') === false && strpos($pathUri, 'rest/V1') === false)
            return $proceed();
        
        $contents            = $request->getContent();
        $contents_array      = [];
        if ($contents && ($contents != '')) {
            $contents_parser = urldecode($contents);
            $contents_array = json_decode($contents_parser, true);
        }
        $simiCurrency = $request->getParam('simiCurrency');
        if ($contents_array) {
            if (!$simiCurrency && isset($contents_array['variables']['simiCurrency'])) {
                $simiCurrency = $contents_array['variables']['simiCurrency'];
            }
            if (!$simiCurrency && isset($contents_array['variables']['currency'])) {
                $simiCurrency = $contents_array['variables']['currency'];
            }
            if (!$simiCurrency && isset($contents_array['simiCurrency'])) {
                $simiCurrency = $contents_array['simiCurrency'];
            }
        }
        //in case of GET graphQL
        $graphQLVariables = $request->getParam('variables');
        if ($graphQLVariables) {
            $graphQLVariables = json_decode($graphQLVariables, true);
            if ($graphQLVariables && is_array($graphQLVariables)) {
                if (isset($graphQLVariables['simiCurrency']))
                    $simiCurrency = $graphQLVariables['simiCurrency'];
            }
        }

        if ($simiCurrency && $simiCurrency != '') {
            return $simiCurrency;
        }
        return $proceed();
    }
}