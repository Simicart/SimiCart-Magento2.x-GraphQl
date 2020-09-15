<?php
namespace Simi\SimiconnectorGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\ResourceModel\Report\Bestsellers\CollectionFactory as BestSellersCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;


class SimiCategory implements ResolverInterface
{
	protected $pointDataProvider;
	protected $storeManager;
	protected $collectionVisibility;
    protected $imageHelper;
    protected $collectionCategory;
    /**
     * @param DataProvider\Faq $faqRepository
     */
    public function __construct(
    	\Api\RewardPoint\Model\Customer\CustomerPoint $pointDataProvider,
    	\Magento\Store\Model\StoreManagerInterface $storeManager,
    	\Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Helper\Data $helper,
        \Simi\Simiconnector\Model\ResourceModel\Simicategory\CollectionFactory $collectionCategory
    ) {
    	$this->pointDataProvider = $pointDataProvider;
    	$this->storeManager = $storeManager;
    	$this->collectionVisibility = $collectionVisibility;
        $this->imageHelper = $helper;
        $this->collectionCategory = $collectionCategory;

    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $content_type = $this->imageHelper->getVisibilityTypeId('homecategory');
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();        
        $storeManager  = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        //get current store view id
        $storeID = (int)$context->getExtensionAttributes()->getStore()->getId();
        
        $data = [];//the array return visibility information of the categories
        $categoriesId = [];//the array return the categories id which meet the requirements of the storeview
        $returnCategories = [];//the array return the final categories set we need about the information of the choosen categories

        //get all information of visibility about categories type
        $categoriesStoreView =  $this->collectionVisibility->create()->addFieldToFilter('content_type', ['eq' => $content_type])->getData();
        for ($i=0; $i <sizeof($categoriesStoreView) ; $i++) 
        { 
            if($categoriesStoreView[$i]['store_view_id'] == $storeID)
            {
                array_push($data,$categoriesStoreView[$i]);
            }  
        }
        //if no data is stored(which mean no categories are the right to show in the current store view) -> return null
        if(sizeof($data) == 0){
            return null;
        }
        else
        {
            // get all id of categories which meet the requirements of the current storeview 
            for($i=0; $i<sizeof($data); $i++){
                array_push($categoriesId, (int)$data[$i]['item_id']);
            }

            //get the information about the categories base on the id given above
            for($i=0; $i<sizeof($categoriesId); $i++){
                $categoriesCollection = $this->collectionCategory->create();
                array_push($returnCategories, $categoriesCollection->addFieldToFilter('simicategory_id', ['eq' => $categoriesId[$i]])->getData()[0]);
            }
            //sort the return Categories base on the sort order attribute
            usort($returnCategories, $this->build_sorter('sort_order'));


            //push the return categiries into the array
            $finalResult = [];
            
            for($i=0; $i<sizeof($returnCategories); $i++)
            {
                $categoriesFileName = str_replace("Simiconnector", "",$returnCategories[$i]["simicategory_filename"]);
                $categoriesFileNameTablet = str_replace("Simiconnector", "",$returnCategories[$i]["simicategory_filename_tablet"]);
            //get the url of the file by helper
            $categoriesUrl = $this->imageHelper->getBaseUrl() . $categoriesFileName;
            //get url of the file tablet by helper
            $categoriesUrlTablet = $this->imageHelper->getBaseUrl() . $categoriesFileNameTablet;
            $category = [
                'name' => $returnCategories[$i]['simicategory_name'],
                'file_url' => $categoriesUrl,
                'filetablet_url' => $categoriesUrlTablet,
                'category_id' => $returnCategories[$i]['category_id'],
                'status' => $returnCategories[$i]['status'],
                'website_id' => $returnCategories[$i]['website_id'],
                'storeview_id' => $returnCategories[$i]['storeview_id'],
                'sort_order' => $returnCategories[$i]['sort_order'],
                'matrix_width_percent' => $returnCategories[$i]['matrix_width_percent'],
                'matrix_height_percent' => $returnCategories[$i]['matrix_height_percent'],
                'matrix_width_percent_tablet' => $returnCategories[$i]['matrix_width_percent_tablet'],
                'matrix_height_percent_tablet' => $returnCategories[$i]['matrix_height_percent_tablet'],
                'matrix_row' => $returnCategories[$i]['matrix_row']
            ];
            array_push($finalResult, $category);
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