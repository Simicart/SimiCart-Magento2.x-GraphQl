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


class SimiCMSpage implements ResolverInterface
{
	protected $pointDataProvider;
	protected $storeManager;
	protected $collectionVisibility;
    protected $imageHelper;
    protected $collectionCMS;
    /**
     * @param DataProvider\Faq $faqRepository
     */
    public function __construct(
    	\Api\RewardPoint\Model\Customer\CustomerPoint $pointDataProvider,
    	\Magento\Store\Model\StoreManagerInterface $storeManager,
    	\Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Helper\Data $helper,
        \Simi\Simiconnector\Model\ResourceModel\Cms\CollectionFactory $collectionCMS
    ) {
    	$this->pointDataProvider = $pointDataProvider;
    	$this->storeManager = $storeManager;
    	$this->collectionVisibility = $collectionVisibility;
        $this->imageHelper = $helper;
        $this->collectionCMS = $collectionCMS;

    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {

        $content_type = $this->imageHelper->getVisibilityTypeId('cms');
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();        
        $storeManager  = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        //get current store view id
        $storeID = (int)$context->getExtensionAttributes()->getStore()->getId();
        
        $data = [];//the array return visibility information of the ProductList
        $cmsID = [];//the array return the product list id which meet the requirements of current the storeview
        $returnCMS = [];//the array return the final productList set we need about the information of the choosen ProductList

        //get all information of visibility about product list type
        $cms_StoreView =  $this->collectionVisibility->create()->addFieldToFilter('content_type', ['eq' => $content_type])->getData();
        for ($i=0; $i <sizeof($cms_StoreView) ; $i++) 
        { 
            if($cms_StoreView[$i]['store_view_id'] == $storeID)
            {
                array_push($data,$cms_StoreView[$i]);
            }  
        }
        //if no data is stored(which mean no product list are the right to show in the current store view) -> return null
        if(sizeof($data) == 0){
            return null;
        }
        else
        {
            // get all id of all product list which meet the requirements of the current storeview 
            for($i=0; $i<sizeof($data); $i++){
                array_push($cmsID, (int)$data[$i]['item_id']);
            }

            //get the information about the categories base on the id given above
            for($i=0; $i<sizeof($cmsID); $i++){
                $cmsCollection = $this->collectionCMS->create();
                array_push($returnCMS, $cmsCollection->addFieldToFilter('cms_id', ['eq' => $cmsID[$i]])->getData()[0]);
            }
            //sort the return Categories base on the sort order attribute
            usort($returnCMS, $this->build_sorter('sort_order'));
            //push the return categiries into the array
            $finalResult = [];
           
            for($i=0; $i<sizeof($returnCMS); $i++)
            {
            $cms = [
                'cms_title' => $returnCMS[$i]['cms_title'],
                'cms_content' => $returnCMS[$i]['cms_content'],
                'cms_status' => $returnCMS[$i]['cms_status'],
                'website_id' => $returnCMS[$i]['website_id'],
                'type' => $returnCMS[$i]['type'],
                'category_id' => $returnCMS[$i]['category_id'],
                'sort_order' => $returnCMS[$i]['sort_order'],
                'cms_script' => $returnCMS[$i]['cms_script'],
                'cms_url' => $returnCMS[$i]['cms_url'],
                'cms_meta_title' => $returnCMS[$i]['cms_meta_title'],
                'cms_meta_desc' => $returnCMS[$i]['cms_meta_desc']
            ];
            array_push($finalResult, $cms);
            }
        return $finalResult;
        }

    }
    //function to sort the array by it's attributes
    private function build_sorter($key) 
    {
        return function ($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
    }

}