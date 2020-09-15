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
        $typeID = $this->imageHelper->getVisibilityTypeId('homecategory');
        $categoryCollection = $this->collectionCategory->create()->addFieldToFilter('status', '1')
                ->applyAPICollectionFilter('simiconnector_visibility', $typeID, (int)$context->getExtensionAttributes()->getStore()->getId());
        $returnHomeCategory = $categoryCollection->getData();

        if(sizeof($returnHomeCategory) == 0){
            return null;
        }
        else
        {
            $finalResult = [];
            usort($returnHomeCategory, $this->build_sorter('sort_order'));
            for($i=0; $i<sizeof($returnHomeCategory); $i++)
            {
            $categoriesFileName = str_replace("Simiconnector", "",$returnHomeCategory[$i]["simicategory_filename"]);
            $categoriesFileNameTablet = str_replace("Simiconnector", "",$returnHomeCategory[$i]["simicategory_filename_tablet"]);
            //get the url of the file by helper
            $categoriesUrl = $this->imageHelper->getBaseUrl() . $categoriesFileName;
            //get url of the file tablet by helper
            $categoriesUrlTablet = $this->imageHelper->getBaseUrl() . $categoriesFileNameTablet;
            $category = [
                'name' => $returnHomeCategory[$i]['simicategory_name'],
                'file_url' => $categoriesUrl,
                'filetablet_url' => $categoriesUrlTablet,
                'category_id' => $returnHomeCategory[$i]['category_id'],
                'status' => $returnHomeCategory[$i]['status'],
                'website_id' => $returnHomeCategory[$i]['website_id'],
                'storeview_id' => $returnHomeCategory[$i]['storeview_id'],
                'sort_order' => $returnHomeCategory[$i]['sort_order'],
                'matrix_width_percent' => $returnHomeCategory[$i]['matrix_width_percent'],
                'matrix_height_percent' => $returnHomeCategory[$i]['matrix_height_percent'],
                'matrix_width_percent_tablet' => $returnHomeCategory[$i]['matrix_width_percent_tablet'],
                'matrix_height_percent_tablet' => $returnHomeCategory[$i]['matrix_height_percent_tablet'],
                'matrix_row' => $returnHomeCategory[$i]['matrix_row']
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