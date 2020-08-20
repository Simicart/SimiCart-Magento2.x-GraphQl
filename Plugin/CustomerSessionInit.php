<?php
namespace Simi\SimiconnectorGraphQl\Plugin;
use \Magento\Store\Model\StoreManagerInterface;


class CustomerSessionInit
{
    private $simiObjectManager;
    private $storeManager;
    private $httpContext;
    private $appState;
    private $sessionManager;
    private $storeRepository;
    private $storeCookieManager;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $simiObjectManager,
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Store\Api\StoreCookieManagerInterface $storeCookieManager,
        \Magento\Framework\App\State $appState
    ) {
        $this->simiObjectManager = $simiObjectManager;
        $this->storeManager = $storeManager;
        $this->httpContext = $httpContext;
        $this->storeRepository = $storeRepository;
        $this->storeCookieManager = $storeCookieManager;
        $this->appState = $appState;
    }
    //add session id to continue session with graphql
    public function beforeDispatch($subject, $request)
    {
        $objectManager = $this->simiObjectManager;
        if (
            $this->appState->getAreaCode() !== 'graphql'
        )
            return;
        
        $contents            = $request->getContent();
        $contents_array      = [];
        if ($contents && ($contents != '')) {
            $contents_parser = urldecode($contents);
            $contents_array = json_decode($contents_parser, true);
        }
        $simiStoreId = $request->getParam('simiStoreId');
        $simiCurrency = $request->getParam('simiCurrency');
        if ($contents_array) {
            if (!$simiStoreId && isset($contents_array['variables']['simiStoreId'])) {
                $simiStoreId = $contents_array['variables']['simiStoreId'];
            }
            if (!$simiStoreId && isset($contents_array['variables']['storeId'])) {
                $simiStoreId = $contents_array['variables']['storeId'];
            }
            if (!$simiStoreId && isset($contents_array['simiStoreId'])) {
                $simiStoreId = $contents_array['simiStoreId'];
            }
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
                if (isset($graphQLVariables['simiStoreId']))
                    $simiStoreId = $graphQLVariables['simiStoreId'];
                if (isset($graphQLVariables['simiCurrency']))
                    $simiCurrency = $graphQLVariables['simiCurrency'];
            }
        }

        if ($simiStoreId && $simiStoreId != '' && (int)$this->storeManager->getStore()->getId() != (int)$simiStoreId) {
            try {
                $storeCode = $this->storeManager->getStore($simiStoreId)->getCode();

                $store = $this->storeRepository->getActiveStoreByCode($storeCode);
                $defaultStoreView = $this->storeManager->getDefaultStoreView();
                if ($defaultStoreView->getId() == $store->getId()) {
                    $this->storeCookieManager->deleteStoreCookie($store);
                } else {
                    $this->storeCookieManager->setStoreCookie($store);
                }
                $this->storeManager->setCurrentStore(
                    $this->storeManager->getStore($simiStoreId)
                );

                $storeKey = StoreManagerInterface::CONTEXT_STORE;
                $this->httpContext->setValue($storeKey, $storeCode, $this->storeManager->getDefaultStoreView()->getCode());
            } catch (\Exception $e) {
            }
        }
        if ($simiCurrency && $simiCurrency != '' && $simiCurrency != $this->storeManager->getStore()->getCurrentCurrencyCode()) {
            try {
                $this->storeManager->getStore()->setCurrentCurrencyCode($simiCurrency);
            } catch (\Exception $e) {

            }
        }
    }
}