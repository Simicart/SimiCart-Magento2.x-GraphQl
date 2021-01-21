<?php
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\DataProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\StoreConfigManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\StoreGraphQl\Model\Resolver\Store\StoreConfigDataProvider;

class Simigroupdataprovider extends DataProviderInterface
{
    public $storeManager;
    public $groupCollectionFactory;
    public $storeCollectionFactory;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\ResourceModel\Group\CollectionFactory $groupCollectionFactory,
        \Magento\Store\Model\ResourceModel\Store\CollectionFactory $storeCollectionFactory
    )
    {
        $this->storeManager = $storeManager;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->storeCollectionFactory = $storeCollectionFactory;
    }

    public function getSimiGroupData($args)
    {
        $storeData = $this->groupCollectionFactory->create()
            ->addFieldToFilter('website_id', $this->storeManager->getStore()->getWebsiteId())
            ->getData();
        foreach ($storeData as $index => $store) {
            $storeviewCollection = $this->storeCollectionFactory->create()
                ->addFieldToFilter('group_id', $store['group_id']);
            $storeData[$index]['storeviews'] = $storeviewCollection->getData();
        }
        return $storeData;
    }
}
