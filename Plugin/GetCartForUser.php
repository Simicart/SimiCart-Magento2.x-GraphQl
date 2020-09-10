<?php
namespace Simi\SimiconnectorGraphQl\Plugin;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GetCartForUser
{
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
    }

    public function beforeExecute($subject, $cartHash, $customerId, $storeId)
    {
        try {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
            $cart = $this->cartRepository->get($cartId);
        	if ((int)$cart->getStoreId() !== $storeId) {
        		$cart->setStoreId($storeId)->save();
        	}
        } catch (\Exception $e) {
            
        }
    }
}