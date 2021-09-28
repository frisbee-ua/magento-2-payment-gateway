<?php

namespace Fondy\Fondy\Controller\Api;

use Fondy\Fondy\Builder\RequestBuilder;
use Fondy\Fondy\Builder\ResponseBuilder;
use Fondy\Fondy\Configuration\ConfigurationService;
use Fondy\Fondy\Order\OrderManager;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\App\RequestInterface;
use Exception;

/**
 * Class Api
 *
 * @package Fondy\Fondy\Api
 */
class Api implements ApiInterface
{
    /** @var \Psr\Log\LoggerInterface */
    public $logger;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    public $scopeConfig;

    /** @var \Magento\Quote\Model\QuoteRepository */
    public $quoteRepository;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    public $urlBuilder;

    /**
     * @var RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var OrderManager
     */
    protected $orderManager;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ConfigurationService
     */
    protected $configurationService;

    /**
     * @var ResponseBuilder
     */
    protected $responseBuilder;

    /**
     * Token constructor.
     *
     * @param \Magento\Quote\Model\QuoteRepository $quoteRepository
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\UrlInterface $urlBuilder,
        RequestBuilder $requestBuilder,
        OrderManager $orderManager,
        OrderRepository $orderRepository,
        RequestInterface $request,
        ConfigurationService $configurationService,
        ResponseBuilder $responseBuilder
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->requestBuilder = $requestBuilder;
        $this->orderManager = $orderManager;
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        $this->configurationService = $configurationService;
        $this->responseBuilder = $responseBuilder;
    }

    /**
     * @inheritdoc
     */
    public function payment($cartId, $orderId, $method)
    {
        try {
            $paymentMethod = $this->request->getParam('method');
            $order = $this->getOrder($orderId);

            if ($paymentMethod) {
                $this->requestBuilder->setPaymentMethod($paymentMethod);
            }

            $this->requestBuilder->setOrderId($order->getEntityId());
            $fondyOrderId = $this->requestBuilder->prepare($order);

            $credentials = $this->requestBuilder->retrieveCheckoutCredentials();

            $message = (string) $this->requestBuilder->getErrorMessage();

            $order->setExtOrderId($fondyOrderId);
            $this->orderManager->setOrderStatusProcessing($order);

            if ($this->configurationService->isConfigurationPaymentTypeRedirect()) {
                return $this->responseBuilder->url($credentials, $message);
            }

            $options = $this->requestBuilder->generateCheckoutOptions($credentials);

            return $this->responseBuilder->token($credentials, $options, $message);
        } catch (Exception $exception) {
            return $this->responseBuilder->error($exception->getMessage());
        }
    }

    /**
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Exception
     */
    protected function getOrder($orderId)
    {
        $order = $this->orderManager->getOrderById($orderId);

        if (!$order) {
            throw new Exception('Order not found.');
        }

        return $order;
    }
}
