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
    /**
     * @param DataProvider\Faq $faqRepository
     */
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
        $content_type = $this->imageHelper->getVisibilityTypeId('banner');
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();        
        $storeManager  = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        //get current store view id
        $storeID = (int)$context->getExtensionAttributes()->getStore()->getId();
        
        $data = [];//the array return visibility information of the banner
        $bannerid = [];//the array return the banner id which meet the requirements of the storeview
        $returnbanner = [];//the array return the final results about the information of the choosen banner

        //get all information of visibility about banner type
        $bannerStoreView =  $this->collectionVisibility->create()->addFieldToFilter('content_type', ['eq' => $content_type])->getData();
        for ($i=0; $i <sizeof($bannerStoreView) ; $i++) 
        { 
            if($bannerStoreView[$i]['store_view_id'] ==   $storeID)
            {
                array_push($data,$bannerStoreView[$i]);
            }  
        }

        //if no data is stored -> return null
        if(sizeof($data) == 0){
            return null;
        }
        else
        {
            // get all id of banner which meet the requirements of the storeview 
            for($i=0; $i<sizeof($data); $i++){
                array_push($bannerid, (int)$data[$i]['item_id']);
            }

            //get the information about banner base on the id given above
            for($i=0; $i<sizeof($bannerid); $i++){
                $bannercollection = $this->collectionBanner->create();
                array_push($returnbanner, $bannercollection->addFieldToFilter('banner_id', ['eq' => $bannerid[$i]])->getData()[0]);
            }
            
            usort($returnbanner, $this->build_sorter('sort_order'));
        

            //push the return banners into the array
            $finalResult = [];

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
                'categoryID' => $returnbanner[$i]['category_id'],
                'productID' => $returnbanner[$i]['product_id'],
                'sortOder' => $returnbanner[$i]['sort_order']
            ];
            array_push($finalResult, $banner);
            }
        
        return $finalResult;
        }


    }
    private function build_sorter($key) 
    {
        return function ($a, $b) use ($key) {
            return strnatcmp((int)$a[$key], (int)$b[$key]);
        };
    }
}