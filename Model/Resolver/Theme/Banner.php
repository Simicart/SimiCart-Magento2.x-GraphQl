<?php
namespace Simi\SimiconnectorGraphQl\Model\Resolver\Theme;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;


class Banner implements ResolverInterface
{
	protected $pointDataProvider;
	protected $storeManager;
	protected $collectionVisibility;
    protected $collectionBanner;
    protected $imageHelper;
    protected $bannerCollection;
    
    public function __construct(
    	\Api\RewardPoint\Model\Customer\CustomerPoint $pointDataProvider,
    	\Magento\Store\Model\StoreManagerInterface $storeManager,
    	\Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Model\ResourceModel\Banner\CollectionFactory $collectionBanner,
        \Simi\Simiconnector\Helper\Data $helper
    ) {
    	$this->pointDataProvider = $pointDataProvider;
    	$this->storeManager = $storeManager;
    	$this->collectionVisibility = $collectionVisibility;
        $this->collectionBanner = $collectionBanner;
        $this->imageHelper = $helper;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $typeID = $this->imageHelper->getVisibilityTypeId('banner');
        $bannerCollection = $this->collectionBanner->create()->addFieldToFilter('status', '1')
                ->applyAPICollectionFilter('simiconnector_visibility', $typeID, (int)$context->getExtensionAttributes()->getStore()->getId());
        $returnbanner = $bannerCollection->getData();
        //if no data is stored -> return null
        if(sizeof($returnbanner) == 0){
            return null;
        }
        else
        {
            $finalResult = [];
            //reorder the data base on the sort_order attribute
            usort($returnbanner, $this->build_sorter('sort_order'));

            for($i=0; $i<sizeof($returnbanner); $i++)
            {
                $bannername = str_replace("Simiconnector", "",$returnbanner[$i]["banner_name"]);
                $bannernametablet = str_replace("Simiconnector", "",$returnbanner[$i]["banner_name_tablet"]);
            // barnner url return by storeManager->getStore()->getBaseUrl(). name of the file
            /* 
            $bannerUrl = $storeManager->getStore()->
            getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).$returnbanner[$i]["banner_name"];
            // banner tablet url
            $bannerUrlTablet = $storeManager->getStore()->
            getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).$returnbanner[$i]["banner_name_tablet"];
            */
            //get the url of the file by helper
            $bannerUrl = $this->imageHelper->getBaseUrl() . $bannername;
            //get url of the file table by helper
            $bannernametablet = $this->imageHelper->getBaseUrl() . $bannernametablet;
            $banner = [
                'banner_name' => $bannername,
                'banner_url' => $bannerUrl,
                'banner_name_tablet' => $bannernametablet,
                'banner_title' => $returnbanner[$i]['banner_title'],
                'status' => $returnbanner[$i]['status'],
                'category_id' => $returnbanner[$i]['category_id'],
                'product_id' => $returnbanner[$i]['product_id'],
                'sort_order' => $returnbanner[$i]['sort_order'],
                'type' => (int)$returnbanner[$i]['type']
            ];
            array_push($finalResult, $banner);
            }
        
        return $finalResult;
        }


    }
    //this function soer the array by atribute key
    private function build_sorter($key) 
    {
        return function ($a, $b) use ($key) {
            return strnatcmp((int)$a[$key], (int)$b[$key]);
        };
    }
}