<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Model\Resolver\Cart;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;

/**
 * @inheritdoc
 */
class SimiTotals implements ResolverInterface
{
    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        $quote = $value['model'];
        $currency = $quote->getData('quote_currency_code');
        return [
            [
                'code' => 'grand_total',
                'value' => $quote->getGrandTotal(),
                'currency' => $currency,
            ],
            [
                'code' => 'subtotal',
                'value' => $quote->getSubtotal(),
                'currency' => $currency,
            ],
            [
                'code' => 'subtotal_with_discount',
                'value' => $quote->getSubtotalWithDiscount(),
                'currency' => $currency,
            ],
        ];
        return null;
    }
}
