<?php
/**
 * Created by PhpStorm.
 * User: codynguyen
 * Date: 8/16/18
 * Time: 9:02 AM
 */

namespace Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider;

abstract class DataProviderInterface
{
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $simiObjectManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Store\Api\StoreCookieManagerInterface $storeCookieManager,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Event\ManagerInterface $eventManager
    )
    {
        $this->simiObjectManager = $simiObjectManager;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->storeRepository = $storeRepository;
        $this->storeCookieManager = $storeCookieManager;
        $this->resource = $resource;
        $this->eventManager = $eventManager;
        return $this;
    }
}
