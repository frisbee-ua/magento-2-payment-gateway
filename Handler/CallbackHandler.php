<?php

namespace Fondy\Fondy\Handler;

use Fondy\Fondy\Builder\ResponseBuilder;
use Fondy\Fondy\Configuration\ConfigurationService;
use Fondy\Fondy\Order\OrderManager;
use Fondy\Fondy\Service\FondyService;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status as OrderStatusModel;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Zend\Log\Writer\Stream as LogWriterStream;
use Zend\Log\Logger;
use Exception;

final class CallbackHandler
{
    /**
     * @var FondyService
     */
    private $fondyService;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var OrderManager
     */
    private $orderManager;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var ResponseBuilder
     */
    private $responseBuilder;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        FondyService $fondyService,
        ConfigurationService $configurationService,
        OrderManager $orderManager,
        OrderRepository $orderRepository,
        ResponseBuilder $responseBuilder,
        TransactionRepositoryInterface $transactionRepository,
        TransactionBuilder $transactionBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Logger $logger
    ) {
        $this->fondyService = $fondyService;
        $this->configurationService = $configurationService;
        $this->orderManager = $orderManager;
        $this->orderRepository = $orderRepository;
        $this->responseBuilder = $responseBuilder;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->objectManager = ObjectManager::getInstance();
        $writer = new LogWriterStream(sprintf('%s/var/log/fondy.log', BP));
        $this->logger = $logger;
        $this->logger->addWriter($writer);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function execute()
    {
        $this->checkIfShouldCreateOrderStatuses();

        $orderStatusProcessing = $this->configurationService->getOptionOrderStatusProcessing();
        $notified = false;

        try {
            $data = $this->fondyService->getCallbackData();
            $orderId = $this->fondyService->parseOrderId($data);

            /**
             * @var \Magento\Sales\Model\Order $order
             */
            $order = $this->orderRepository->get($orderId);

            $this->fondyService->setMerchantId($this->configurationService->getOptionMerchantId());
            $this->fondyService->setSecretKey($this->configurationService->getOptionSecretKey());

            $this->fondyService->handleCallbackData($data);

            if ($this->fondyService->isOrderDeclined()) {
                $orderStatus = $this->configurationService->getOptionOrderStatusCanceled();
            } elseif ($this->fondyService->isOrderExpired()) {
                exit;
            } elseif ($this->fondyService->isOrderApproved()) {
                $this->createTransaction($order, $data, $order->getGrandTotal(), Transaction::TYPE_ORDER);
                $orderStatus = $this->configurationService->getOptionOrderStatusPaid();
                $this->sendSuccessEmail($order);
                $notified = true;
            } elseif ($this->fondyService->isOrderFullyReversed()) {
                $this->createTransaction($order, $data, $order->getGrandTotal(), Transaction::TYPE_REFUND);
                $orderStatus = $this->orderManager->getOrderStatusTotallyRefunded();
            } elseif ($this->fondyService->isOrderPartiallyReversed()) {
                $this->createTransaction($order, $data, $data['reversal_amount'], Transaction::TYPE_REFUND);
                $orderStatus = $this->orderManager->getOrderStatusPartiallyRefunded();
            } else {
                exit;
            }

            $message = $this->fondyService->getStatusMessage();
        } catch (Exception $exception) {
            $orderStatus = $orderStatusProcessing;
            $message = $exception->getMessage();
            $this->logger->debug($message, $exception->getTrace());
            http_response_code(500);
        }

        $comment = sprintf(
            'Message: %s Frisbee ID: %s Payment ID: %s',
            $message,
            isset($data['order_id']) ? $data['order_id'] : '',
            isset($data['payment_id']) ? $data['payment_id'] : ''
        );

        $this->orderManager->setStatus($order, $orderStatus);
        $history = $this->orderManager->addCommentToStatusHistory($order, $comment);
        $history->setIsCustomerNotified($notified);
        $this->orderRepository->save($order);

        echo $this->responseBuilder->json([
            'orderId' => $order->getId(),
            'message' => isset($exception) ? $exception->getMessage() : $orderStatus
        ]);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param array $paymentData
     * @param float $amount
     * @param string $type
     * @return void
     */
    private function createTransaction(Order $order, array $paymentData, $amount, $type)
    {
        try {
            $payment = $order->getPayment();

            if ($type === Transaction::TYPE_REFUND) {
                $payment->setAmountRefunded($amount);
            } else {
                if (isset($paymentData['preauth']) && $paymentData['preauth'] === 'Y') {
                    $type = Transaction::TYPE_AUTH;
                    $payment->setAmountAuthorized($amount);
                    $order->setPaymentAuthorizationAmount($amount);
                } else {
                    $payment->setAmountPaid($amount);
                    $payment->setBaseAmountPaidOnline($amount);
                    $order->setTotalPaid($amount);
                }
            }

            $payment->setLastTransId($paymentData['payment_id']);
            $payment->setTransactionId($paymentData['payment_id']);
            $payment->setAdditionalInformation(
                [Transaction::RAW_DETAILS => $paymentData]
            );

            $transaction = $this->transactionBuilder
                ->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['payment_id'])
                ->setAdditionalInformation(
                    [Transaction::RAW_DETAILS => $paymentData]
                )
                ->setFailSafe(true)
                ->build($type);

            $formattedAmount = $order->getBaseCurrency()->formatTxt($amount);
            $payment->addTransactionCommentsToOrder(
                $transaction,
                sprintf('%s: %s', __('Amount'), $formattedAmount)
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();
            $transaction->save();
        } catch (Exception $exception) {
            $this->logger->debug($exception->getMessage(), $exception->getTrace());
        }
    }

    /**
     * Sending order confirm email
     * @param $order
     */
    private function sendSuccessEmail($order)
    {
        $orderSender = $this->objectManager->get(OrderSender::class);
        $orderSender->send($order, false, true);
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function checkIfShouldCreateOrderStatuses()
    {
        /**
         * @var OrderStatusModel $orderStatusResourceModel
         */
        $orderStatusResourceModel = $this->objectManager->get(OrderStatusModel::class);

        /**
         * @var OrderStatusCollection $orderStatusCollection
         */
        $orderStatusCollection = $this->objectManager->get(OrderStatusCollection::class);
        $orderStatuses = $orderStatusCollection->toOptionArray();
        $orderStatuses = array_column($orderStatuses, 'label', 'value');
        $orderStatusTotallyRefunded = $this->orderManager->getOrderStatusTotallyRefunded();
        $orderStatusPartiallyRefunded = $this->orderManager->getOrderStatusPartiallyRefunded();

        if (!isset($orderStatuses[$orderStatusTotallyRefunded])) {
            $orderStatusResourceModel->setData([
                'label' => __('Totally Refunded'),
                'status' => $orderStatusTotallyRefunded,
                'state' => $orderStatusTotallyRefunded,
                'is_default' => true,
                'visible_on_front' => true
            ]);
            $orderStatusResourceModel->save();
            $orderStatusResourceModel->assignState($orderStatusTotallyRefunded, true);
        }

        if (!isset($orderStatuses[$orderStatusPartiallyRefunded])) {
            $orderStatusResourceModel->setData([
                'label' => __('Partially Refunded'),
                'status' => $orderStatusPartiallyRefunded,
                'state' => $orderStatusPartiallyRefunded,
                'is_default' => true,
                'visible_on_front' => true
            ]);
            $orderStatusResourceModel->save();
            $orderStatusResourceModel->assignState($orderStatusPartiallyRefunded, true);
        }
    }
}
