<?php

namespace Simi\SimiconnectorGraphQl\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\CacheInterface;

class FlushStoreConfigCache implements ObserverInterface {
	public $simiObjectManager;

	/**
	 * @var CacheInterface
	 */
	private $cache;

	public function __construct(
        CacheInterface $cache
	) {
		$this->cache = $cache;
	}

    public function execute(\Magento\Framework\Event\Observer $observer) {
		$this->cache->clean(['FPC']);
	}
} 
