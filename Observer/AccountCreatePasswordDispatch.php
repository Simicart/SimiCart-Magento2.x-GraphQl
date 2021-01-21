<?php

namespace Simi\SimiconnectorGraphQl\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AccountCreatePasswordDispatch implements ObserverInterface
{

    protected $_redirect;
    protected $_url;

    public function __construct(
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\ObjectManagerInterface $simiObjectManager,
        \Magento\Framework\App\Response\Http $redirect
    )
    {
        $this->simiObjectManager = $simiObjectManager;
        $this->_url = $url;
        $this->_redirect = $redirect;
    }

    public function execute(Observer $observer)
    {
        $request = $observer->getRequest();
        $pathUri = $request->getRequestString();

        $pwa_studio_url = $this->simiObjectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface')->getValue('simiconnector/general/pwa_studio_url');
        if ($pwa_studio_url) {
            if (substr($pwa_studio_url, -1) == '/') {
                $pwa_studio_url = substr_replace($pwa_studio_url, "", -1);
            }
            $targetUrl = $pwa_studio_url . $pathUri;
            header('Location: ' . $targetUrl);
            exit;
            //			$this->_redirect->setRedirect($targetUrl)->sendResponse();;
        }
    }
}