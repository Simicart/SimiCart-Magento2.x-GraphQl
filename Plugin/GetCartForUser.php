<?php

namespace Simi\SimiconnectorGraphQl\Plugin;

use \Magento\Quote\Api\CartRepositoryInterface;
use \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use \Magento\Store\Model\StoreManagerInterface;

class GetCartForUser
{
    private $storeManager;
    private $maskedQuoteIdToQuoteId;
    private $cartRepository;

    public function __construct(
        StoreManagerInterface $storeManager,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository
    )
    {
        $this->storeManager = $storeManager;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
    }

    public function beforeExecute($subject, $cartHash, $customerId, $storeId)
    {
        try {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
            $cart = $this->cartRepository->get($cartId);
            $cartChanged = false;
            if ((int)$cart->getStoreId() !== $storeId) {
                $cartChanged = true;
                $cart->setStoreId($storeId);
            }
            $currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
            if ($cart->getData('quote_currency_code') !== $currencyCode) {
                $cartChanged = true;
                $cart->setData('quote_currency_code', $currencyCode);
            }
            if ($cartChanged)
                $cart->save();
        } catch (\Exception $e) {

        }
    }
}