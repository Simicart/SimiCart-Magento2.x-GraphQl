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
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Deferred\Product as ProductDataProvider;


class QRproduct implements ResolverInterface
{

    protected $imageHelper;
    protected $qrcollection;
    protected $productModel;
    protected $simibarcodeModel;
    protected $_productRepository;
    protected $searchApiCriteriaBuilder;
    private $searchQuery;

    public function __construct(
        \Simi\Simiconnector\Helper\Data $helper,
        \Magento\Catalog\Model\Product $productModel,
        \Simi\Simiconnector\Model\ResourceModel\Simibarcode\CollectionFactory $QRcollection,
        \Simi\Simiconnector\Model\Simibarcode $simibarcodeModel,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        SearchCriteriaBuilder $searchApiCriteriaBuilder = null,
        Search $searchQuery
    ) {
        $this->imageHelper = $helper;
        $this->productModel = $productModel;
        $this->qrcollection = $QRcollection;
        $this->simibarcodeModel = $simibarcodeModel;
        $this->_productRepository = $productRepository;
        $this->searchApiCriteriaBuilder = $searchApiCriteriaBuilder ??    
        \Magento\Framework\App\ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
        $this->searchQuery = $searchQuery;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if(isset($args['barcode'])){
            // die(var_dump($args['barcode']));
            $collection = $this->qrcollection->create();
            $result = $this->simibarcodeModel->getCollection()->addFieldToFilter('barcode_status', '1')->addFieldToFilter('barcode', $args['barcode'])->getData()[0];
        }
        else if(isset($args['qrcode'])){
            $collection = $this->qrcollection->create();
            $result = $this->simibarcodeModel->getCollection()->addFieldToFilter('barcode_status', '1')->addFieldToFilter('qrcode', $args['qrcode'])->getData()[0];
        }
         // die(var_dump($result));

        /* $productInfo = $product->loadByAttribute('sku', $result['product_sku'])->getData();
        $_product = $this->getProductBySku($result['product_sku']);
        $attributes = $_product->getAttributes();// All Product Attributes */
        // die(var_dump(json_decode(json_encode($attributes))));
        $name = [
            'filter'=> [
                'name'=>[
                    'match' => $result['product_name']
                ]
            ],
            'pageSize'=>20,
            'currentPage' =>1
        ];
        $includeAggregations = false;
        
        $searchCriteria = $this->searchApiCriteriaBuilder->build($name, $includeAggregations);
        $searchResult = $this->searchQuery->getResult($searchCriteria, $info, $name);
        $products = $searchResult->getProductsSearchResult();
        foreach ($products as $product){
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

        
    }
}
