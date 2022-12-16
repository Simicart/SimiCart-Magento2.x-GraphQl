<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Simi\SimiconnectorGraphQl\Override;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\GraphQl\Exception\ExceptionFormatter;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Response;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\GraphQl\Query\Fields as QueryFields;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\CacheInterface;
use Magento\GraphQl\Model\Query\ContextFactoryInterface;
use Magento\GraphQl\Controller\HttpRequestProcessor;

/**
 * Front controller for web API GraphQL area.
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.3.0
 */
class GraphQl implements FrontControllerInterface
{
    /**
     * @var \Magento\Framework\Webapi\Response
     * @deprecated 100.3.2
     */
    private $response;

    /**
     * @var SchemaGeneratorInterface
     */
    private $schemaGenerator;

    /**
     * @var SerializerInterface
     */
    private $jsonSerializer;

    /**
     * @var QueryProcessor
     */
    private $queryProcessor;

    /**
     * @var ExceptionFormatter
     */
    private $graphQlError;

    /**
     * @var ContextInterface
     * @deprecated 100.3.3 $contextFactory is used for creating Context object
     */
    private $resolverContext;

    /**
     * @var HttpRequestProcessor
     */
    private $requestProcessor;

    /**
     * @var QueryFields
     */
    private $queryFields;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var HttpResponse
     */
    private $httpResponse;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var ContextFactoryInterface
     */
    private $contextFactory;

    /**
     * @param Response $response
     * @param SchemaGeneratorInterface $schemaGenerator
     * @param SerializerInterface $jsonSerializer
     * @param QueryProcessor $queryProcessor
     * @param ExceptionFormatter $graphQlError
     * @param ContextInterface $resolverContext Deprecated. $contextFactory is used for creating Context object.
     * @param HttpRequestProcessor $requestProcessor
     * @param QueryFields $queryFields
     * @param JsonFactory|null $jsonFactory
     * @param HttpResponse|null $httpResponse
     * @param ContextFactoryInterface $contextFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Response $response,
        SchemaGeneratorInterface $schemaGenerator,
        SerializerInterface $jsonSerializer,
        QueryProcessor $queryProcessor,
        ExceptionFormatter $graphQlError,
        ContextInterface $resolverContext,
        HttpRequestProcessor $requestProcessor,
        QueryFields $queryFields,
        JsonFactory $jsonFactory = null,
        HttpResponse $httpResponse = null,
        CacheInterface $cache,
        ContextFactoryInterface $contextFactory = null
    ) {
        $this->response = $response;
        $this->schemaGenerator = $schemaGenerator;
        $this->jsonSerializer = $jsonSerializer;
        $this->queryProcessor = $queryProcessor;
        $this->graphQlError = $graphQlError;
        $this->resolverContext = $resolverContext;
        $this->requestProcessor = $requestProcessor;
        $this->queryFields = $queryFields;
        $this->cache = $cache;
        $this->jsonFactory = $jsonFactory ?: ObjectManager::getInstance()->get(JsonFactory::class);
        $this->httpResponse = $httpResponse ?: ObjectManager::getInstance()->get(HttpResponse::class);
        $this->contextFactory = $contextFactory ?: ObjectManager::getInstance()->get(ContextFactoryInterface::class);
    }

    /**
     * Handle GraphQL request
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @since 100.3.0
     */
    public function dispatch(RequestInterface $request) : ResponseInterface
    {
        $statusCode = 200;
        $jsonResult = $this->jsonFactory->create();
        try {
            $cacheId = $this->getTag();
            $result = null;
            $data = $this->cache->load($cacheId);
            if (strpos($_SERVER['REQUEST_URI'], 'storeConfigData') && $data && $arrayData = json_decode($data, true)) {
                $result = $arrayData;
            }else{
            /** @var Http $request */
            $this->requestProcessor->validateRequest($request);

            $data = $this->getDataFromRequest($request);
            $query = $data['query'] ?? '';
            $variables = $data['variables'] ?? null;

            // We must extract queried field names to avoid instantiation of unnecessary fields in webonyx schema
            // Temporal coupling is required for performance optimization
            $this->queryFields->setQuery($query, $variables);
            $schema = $this->schemaGenerator->generate();

            $result = $this->queryProcessor->process(
                $schema,
                $query,
                $this->contextFactory->create(),
                $data['variables'] ?? []
            );
            if (strpos($_SERVER['REQUEST_URI'], 'storeConfigData')){
                $this->cache->save(json_encode($result), $cacheId, ['FPC', 'CONFIG', 'EAV', 'EAV_ATTRIBUTE'], 86400);
           }
	   }
        } catch (\Exception $error) {
            $result['errors'] = isset($result) && isset($result['errors']) ? $result['errors'] : [];
            $result['errors'][] = $this->graphQlError->create($error);
            $statusCode = ExceptionFormatter::HTTP_GRAPH_QL_SCHEMA_ERROR_STATUS;
        }

        $jsonResult->setHttpResponseCode($statusCode);
        $jsonResult->setData($result);
        $jsonResult->renderResult($this->httpResponse);
        return $this->httpResponse;
    }

    /**
     * Get data from request body or query string
     *
     * @param RequestInterface $request
     * @return array
     */
    private function getDataFromRequest(RequestInterface $request) : array
    {
        /** @var Http $request */
        if ($request->isPost()) {
            $data = $this->jsonSerializer->unserialize($request->getContent());
        } elseif ($request->isGet()) {
            $data = $request->getParams();
            $data['variables'] = isset($data['variables']) ?
                $this->jsonSerializer->unserialize($data['variables']) : null;
            $data['variables'] = is_array($data['variables']) ?
                $data['variables'] : null;
        } else {
            return [];
        }

        return $data;
    }

    private function getTag()
    {
        $getAllHeaderFunction = 'getallheaders';
        if (!function_exists($getAllHeaderFunction)) {
            function getallheaders1()
            {
                $head = [];
                //change back to $_SERVER and to get Headers
                foreach ($_SERVER as $name => $value) {
                    if (substr($name, 0, 5) == 'HTTP_') {
                        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                        $head[$name] = $value;
                    } elseif ($name == "CONTENT_TYPE") {
                        $head["Content-Type"] = $value;
                    } elseif ($name == "CONTENT_LENGTH") {
                        $head["Content-Length"] = $value;
                    }
                }
                return $head;
            }

            $head = getallheaders1();
        } else {
            $head = $getAllHeaderFunction();
        }        
        $store = "";
        $curency = "";
        foreach ($head as $k => $h) {
            if ($k == "Store" || $k == "STORE" || $k == "store") {
                $store = $h;
            }
            if ($k == "Content-Currency" || $k == "CONTEN-CURRENCY" || $k == "content-currency") {
                $curency = $h;
            }
        }
        $cacheId = 'simi_store' . '_' . $store . '_' . $curency;
        return $cacheId;
    }
}