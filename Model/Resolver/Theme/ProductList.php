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
        $typeID = $this->imageHelper->getVisibilityTypeId('productlist');
        $productListCollection = $this->collectionProductList->create()->addFieldToFilter('list_status', '1')
                ->applyAPICollectionFilter('simiconnector_visibility', $typeID, (int)$context->getExtensionAttributes()->getStore()->getId());
        $returnProductList = $productListCollection->getData();

        if(sizeof($returnProductList) == 0){
            return null;
        }
        else
        {
            //reorder the data base on the attribute sort_order
            $finalResult = [];
            usort($returnProductList, $this->build_sorter('sort_order'));
            for($i=0; $i<sizeof($returnProductList); $i++)
            {
            
            $productListFileName = str_replace("Simiconnector", "",$returnProductList[$i]["list_image"]);
            $productListFileNameTablet = str_replace("Simiconnector", "",$returnProductList[$i]["list_image_tablet"]);
            //get the url of the file by helper
            $productListUrl = $this->imageHelper->getBaseUrl() . $productListFileName;
            //get url of the file table by helper
            $productListUrlTablet = $this->imageHelper->getBaseUrl() . $productListFileNameTablet;
            
            $productList = [
                'list_title' => $returnProductList[$i]['list_title'],
                'image_url' => $productListUrl,
                'image_url_tablet' => $productListUrlTablet,
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