<?php
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\Cart;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\CatalogInventory\Api\StockStateInterface;

class Cartitemstock implements ResolverInterface
{
    private $stockState;

    /**
     * @param StockStateInterface $stockState
     */
    public function __construct(
        StockStateInterface $stockState
    )
    {
        $this->stockState = $stockState;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        if (!isset($value['model'])) {
            return null;
        }
        $quoteItem = $value['model'];
        $qtyForCheck = $quoteItem->getData('qty');
        $product = $quoteItem->getProduct();
        $productId = $product->getId();
        $stockResult = true;
        $stockErrorMessage = '';
        try {
            $stockMessage = $this->stockState->checkQuoteItemQty(
                $productId,
                $qtyForCheck,
                $qtyForCheck,
                $qtyForCheck
            );
            $stockMessage = $stockMessage->getData();
            if ($stockMessage['has_error']) {
                if ($stockMessage['message'])
                    $stockErrorMessage = $stockMessage['message'];
                $stockResult = false;
            }
        } catch (\Exception $e) {

        }

        return [
            'stock_status' => $stockResult,
            'stock_error_message' => $stockErrorMessage,
            'child_product_sku' => $quoteItem->getData('sku'),
        ];
    }
}
