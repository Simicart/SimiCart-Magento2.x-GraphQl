<?php
namespace Simi\SimiconnectorGraphQl\Plugin;


class StoreCookieManager
{
    private $request;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->request = $request;
    }
    //add session id to continue session with graphql
    public function afterGetStoreCodeFromCookie($subject, $result)
    {
        $request = $this->request;
		$pathUri = $request->getRequestString();

        if(strpos($pathUri, 'graphql') === false && strpos($pathUri, 'rest/V1') === false)
            return $result;

        $contents        	 = $request->getContent();
        $contents_array      = [];
        if ($contents && ($contents != '')) {
            $contents_parser = urldecode($contents);
            $contents_array = json_decode($contents_parser, true);
        }
        $simiStoreId = $request->getParam('simiStoreId');
        if ($contents_array) {
            if (!$simiStoreId && isset($contents_array['variables']['simiStoreId'])) {
                $simiStoreId = $contents_array['variables']['simiStoreId'];
            }
            if (!$simiStoreId && isset($contents_array['variables']['storeId'])) {
                $simiStoreId = $contents_array['variables']['storeId'];
            }
            if (!$simiStoreId && isset($contents_array['simiStoreId'])) {
                $simiStoreId = $contents_array['simiStoreId'];
            }
        }
        //in case of GET graphQL
        $graphQLVariables = $request->getParam('variables');
        if ($graphQLVariables) {
            $graphQLVariables = json_decode($graphQLVariables, true);
            if ($graphQLVariables && is_array($graphQLVariables)) {
                if (isset($graphQLVariables['simiStoreId']))
                    $simiStoreId = $graphQLVariables['simiStoreId'];
            }
        }

        if ($simiStoreId && $simiStoreId != '' && $result != (int)$simiStoreId) {
            return (string)$simiStoreId;
        }
        return $result;
    }
}