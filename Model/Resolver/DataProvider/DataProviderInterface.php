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
        \Magento\Framework\ObjectManagerInterface $simiObjectManager
    )
    {
        $this->simiObjectManager = $simiObjectManager;
        $this->scopeConfig = $this->simiObjectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $this->storeManager = $this->simiObjectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $this->storeRepository = $this->simiObjectManager->get('\Magento\Store\Api\StoreRepositoryInterface');
        $this->storeCookieManager = $this->simiObjectManager->get('\Magento\Store\Api\StoreCookieManagerInterface');
        $this->resource = $this->simiObjectManager->get('\Magento\Framework\App\ResourceConnection');
        $this->eventManager = $this->simiObjectManager->get('\Magento\Framework\Event\ManagerInterface');
        return $this;
    }
}
