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


class ProductList implements ResolverInterface
{
	protected $pointDataProvider;
	protected $storeManager;
	protected $collectionVisibility;
    protected $imageHelper;
    protected $collectionProductList;
    /**
     * @param DataProvider\Faq $faqRepository
     */
    public function __construct(
    	\Api\RewardPoint\Model\Customer\CustomerPoint $pointDataProvider,
    	\Magento\Store\Model\StoreManagerInterface $storeManager,
    	\Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Helper\Data $helper,
        \Simi\Simiconnector\Model\ResourceModel\Productlist\CollectionFactory $collectionProductList
    ) {
    	$this->pointDataProvider = $pointDataProvider;
    	$this->storeManager = $storeManager;
    	$this->collectionVisibility = $collectionVisibility;
        $this->imageHelper = $helper;
        $this->collectionProductList = $collectionProductList;

    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $content_type = $this->imageHelper->getVisibilityTypeId('productlist');
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();        
        $storeManager  = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        //get current store view id
        $storeID = (int)$context->getExtensionAttributes()->getStore()->getId();
        
        $data = [];//the array return visibility information of the ProductList
        $productListId = [];//the array return the product list id which meet the requirements of current the storeview
        $returnProductList = [];//the array return the final productList set we need about the information of the choosen ProductList

        //get all information of visibility about product list type
        $productList_StoreView =  $this->collectionVisibility->create()->addFieldToFilter('content_type', ['eq' => $content_type])->getData();
        for ($i=0; $i <sizeof($productList_StoreView) ; $i++) 
        { 
            if($productList_StoreView[$i]['store_view_id'] == $storeID)
            {
                array_push($data,$productList_StoreView[$i]);
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
                array_push($productListId, (int)$data[$i]['item_id']);
            }

            //get the information about the categories base on the id given above
            for($i=0; $i<sizeof($productListId); $i++){
                $productListCollection = $this->collectionProductList->create();
                array_push($returnProductList, $productListCollection->addFieldToFilter('productlist_id', ['eq' => $productListId[$i]])->getData()[0]);
            }
            //sort the return Categories base on the sort order attribute
            usort($returnProductList, $this->build_sorter('sort_order'));
            //push the return categiries into the array
            $finalResult = [];
           
            for($i=0; $i<sizeof($returnProductList); $i++)
            {
                $productListFileName = str_replace("Simiconnector", "",$returnProductList[$i]["list_image"]);
                $productListFileNameTablet = str_replace("Simiconnector", "",$returnProductList[$i]["list_image_tablet"]);
            //get the url of the file by helper
            $productListUrl = $this->imageHelper->getBaseUrl() . $productListFileName;
            //get url of the file table by helper
            $productListUrlTablet = $this->imageHelper->getBaseUrl() . $productListFileNameTablet;
            
            $productList = [
                'listTitle' => $returnProductList[$i]['list_title'],
                'ImageUrl' => $productListUrl,
                'ImageUrlTablet' => $productListUrlTablet,
                'type' => $returnProductList[$i]['list_type'],
                'list_Product' => $returnProductList[$i]['list_products'],
                'status' => $returnProductList[$i]['list_status'],
                'sort_order' => $returnProductList[$i]['sort_order'],
                'matrix_width_percent' => $returnProductList[$i]['matrix_width_percent'],
                'matrix_height_percent' => $returnProductList[$i]['matrix_height_percent'],
                'matrix_width_percent_tablet' => $returnProductList[$i]['matrix_width_percent_tablet'],
                'matrix_height_percent_tablet' => $returnProductList[$i]['matrix_height_percent_tablet'],
                'matrix_row' => $returnProductList[$i]['matrix_height_percent_tablet'],
                'category_id' => $returnProductList[$i]['category_id']
            ];
            array_push($finalResult, $productList);
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