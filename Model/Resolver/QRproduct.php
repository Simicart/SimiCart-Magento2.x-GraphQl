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
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;
use Simi\SimiconnectorGraphQl\Model\Resolver\Products\Query\Search;


class QRproduct implements ResolverInterface
{

	protected $storeManager;
	protected $collectionVisibility;
    protected $collectionBanner;
    protected $imageHelper;
    protected $qrcollection;
    protected $categoryModel;
    protected $productModel;
    protected $simibarcodeModel;
    protected $_productRepository;
    protected $productFactory;
    protected $searchApiCriteriaBuilder;

    public function __construct(
    	\Magento\Store\Model\StoreManagerInterface $storeManager,
    	\Simi\Simiconnector\Model\ResourceModel\Visibility\CollectionFactory $collectionVisibility,
        \Simi\Simiconnector\Helper\Data $helper,
        \Magento\Catalog\Model\Category $categoryModel,
        \Magento\Catalog\Model\Product $productModel,
        \Simi\Simiconnector\Model\ResourceModel\Simibarcode\CollectionFactory $QRcollection,
        \Simi\Simiconnector\Model\Simibarcode $simibarcodeModel,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        SearchCriteriaBuilder $searchApiCriteriaBuilder = null,
        Search $searchQuery
    ) {
    	$this->storeManager = $storeManager;
    	$this->collectionVisibility = $collectionVisibility;
        $this->imageHelper = $helper;
        $this->productModel = $productModel;
        $this->categoryModel = $categoryModel;
        $this->qrcollection = $QRcollection;
        $this->simibarcodeModel = $simibarcodeModel;
        $this->_productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->searchApiCriteriaBuilder = $searchApiCriteriaBuilder ??    
        \Magento\Framework\App\ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
        $this->searchQuery = $searchQuery;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if($args['barcode']){
            // die(var_dump($args['barcode']));
            $collection = $this->qrcollection->create();
            $result = $this->simibarcodeModel->getCollection()->addFieldToFilter('barcode_status', '1')->addFieldToFilter('barcode', $args['barcode'])->getData()[0];
        }
        else if($args['qrcode']){
            $collection = $this->qrcollection->create();
            $result = $this->simibarcodeModel->getCollection()->addFieldToFilter('barcode_status', '1')->addFieldToFilter('qrcode', $args['qrcode'])->getData()[0];
        }
        // die(var_dump(get_class_methods($info)));
        //data to search for the product
        $sku = [
            'filter' => [
                'sku' => [
                    'eq' => $result['product_sku']
                ] 
            ],
            'pageSize' => 20,
            'currentPage' => 1
        ];


        $product = $this->productFactory->create();
        //all information of the product

        /* $productInfo = $product->loadByAttribute('sku', $result['product_sku'])->getData();
        $_product = $this->getProductBySku($result['product_sku']);
        $attributes = $_product->getAttributes();// All Product Attributes */
        // die(var_dump(json_decode(json_encode($attributes))));

        $productFields = (array)$info->getFieldSelection(1);
        $includeAggregations = isset($productFields['filters']) || isset($productFields['aggregations']);
        $searchCriteria = $this->searchApiCriteriaBuilder->build($sku, $includeAggregations);
        $searchResult = $this->searchQuery->getResult($searchCriteria, $info, $sku);
        $product = $searchResult->getProductsSearchResult();
        // die(var_dump(json_decode(json_encode($product))));

        $finalResult =
        [
            'barcode_id' => $result['barcode_id'],
            'barcode' => $result['barcode'],
            'qrcode' => $result['qrcode'],
            'barcode_status' => $result['barcode_status'],
            'product_entity_id' => $result['product_entity_id'],
            'product_name' => $result['product_name'],
            'product_sku' => $result['product_sku'],
            'created_date' =>$result['created_date'],
            'product_info' => $product
        ];
        return $finalResult;
    }
    public function getProductBySku($sku)
    {
        return $this->_productRepository->get($sku);
    }
}
