<?php

namespace Fondy\Fondy\Builder;

use Fondy\Fondy\Configuration\ConfigurationService;
use Fondy\Fondy\Service\FondyService;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Exception;

final class RequestBuilder
{
    const PRECISION = 2;

    /**
     * @var FondyService
     */
    private $fondyService;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var LocaleResolver
     */
    private $localeResolver;

    /**
     * @var CountryInformationAcquirerInterface
     */
    private $countryInformationAcquirerInterface;

    /**
     * @var @string
     */
    private $errorMessage;

    /**
     * @var int
     */
    private $orderId;

    /**
     * @var \Fondy\Fondy\Configuration\ConfigurationService
     */
    private $configurationService;

    /**
     * @var PriceHelper
     */
    private $priceHelper;

    /**
     * @var string
     */
    private $paymentMethod;

    public function __construct(
        FondyService $fondyService,
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder,
        CustomerSession $customerSession,
        LocaleResolver $localeResolver,
        CountryInformationAcquirerInterface $countryInformationAcquirer,
        ConfigurationService $configurationService,
        PriceHelper $priceHelper
    ) {
        $this->fondyService = $fondyService;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
        $this->customerSession = $customerSession;
        $this->localeResolver = $localeResolver;
        $this->countryInformationAcquirerInterface = $countryInformationAcquirer;
        $this->configurationService = $configurationService;
        $this->priceHelper = $priceHelper;
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * @param $paymentMethod
     * @return void
     */
    public function setPaymentMethod($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * @param OrderInterface|Order $order
     * @return string
     */
    public function prepare(OrderInterface $order)
    {
        if ($this->configurationService->isConfigurationTestModeEnabled()) {
            $this->fondyService->testModeEnable();
        }

        if ($this->configurationService->isConfigurationCardsPaymentEnabled()) {
            $this->fondyService->withPaymentMethodCards();
        }

        if ($this->configurationService->isConfigurationBankLinksPaymentEnabled()) {
            $this->fondyService->withPaymentMethodBankLinks();
            $this->fondyService->setRequestParameterDefaultPaymentSystem(FondyService::PAYMENT_METHOD_BANK_LINKS);
        }

        if ($this->configurationService->isConfigurationWalletsPaymentEnabled()) {
            $this->fondyService->withPaymentMethodWallets();
        }

        if ($order->getExtOrderId()) {
            $this->fondyService->setRequestParameterOrderId($order->getExtOrderId());
        } else {
            $this->fondyService->generateRequestParameterOrderId($order->getId());
        }

        $successPageUrl = $this->urlBuilder->getUrl('checkout/onepage/success/');
        $callbackUrl = $this->urlBuilder->getUrl('fondy/url/callback');

        $this->fondyService->setMerchantId($this->configurationService->getOptionMerchantId());
        $this->fondyService->setSecretKey($this->configurationService->getOptionSecretKey());
        $this->fondyService->setRequestParameterOrderDescription($this->generateOrderDescription($order));
        $this->fondyService->setRequestParameterAmount($this->getAmount($order));
        $this->fondyService->setRequestParameterCurrency($order->getOrderCurrencyCode());
        $this->fondyService->setRequestParameterServerCallbackUrl($callbackUrl);
        $this->fondyService->setRequestParameterResponseUrl($successPageUrl);
        $this->fondyService->setRequestParameterLanguage($this->getLanguageCode());
        $this->fondyService->setRequestParameterSenderEmail($order->getCustomerEmail());
        $this->fondyService->setRequestParameterReservationData($this->generateReservationData($order));
        $this->fondyService->setRequestParameterMerchantData($this->generateMerchantData($order));
        $this->fondyService->setRequestUserAgent('Magento 2 CMS');

        if ($this->configurationService->isConfigurationPreAuthEnabled()) {
            $this->fondyService->enablePreAuthorization();
        }

        if (isset($this->paymentMethod)) {
            $this->fondyService->setStrategyByType($this->paymentMethod);
        } elseif ($this->configurationService->isConfigurationPaymentTypeEmbedded()) {
            $this->fondyService->useStrategyToken();
        } else {
            $this->fondyService->useStrategyUrl();
        }

        return $this->fondyService->getRequestParameterOrderId();
    }

    /**
     * @return bool|false|mixed
     */
    public function retrieveCheckoutCredentials()
    {
        try {
            $this->fondyService->setRequestParameterLifetime(FondyService::CREDENTIALS_LIFETIME);
            $credentials = $this->fondyService->retrieveCheckoutCredentials($this->orderId);

            if ($credentials) {
                return $credentials;
            }

            $this->setErrorMessage($this->fondyService->getStatusMessage());
        } catch (Exception $exception) {
            $this->setErrorMessage($exception->getMessage());
        }

        return false;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param string $extOrderId
     * @param float $amount
     * @return bool|object
     */
    public function refund($order, $extOrderId, $amount)
    {
        try {
            if ($this->configurationService->isConfigurationTestModeEnabled()) {
                $this->fondyService->testModeEnable();
            }

            $this->fondyService->setRequestParameterOrderId($extOrderId);
            $this->fondyService->setRequestParameterCurrency($order->getOrderCurrencyCode());
            $this->fondyService->setRequestParameterAmount($amount);
            $this->fondyService->setMerchantId($this->configurationService->getOptionMerchantId());
            $this->fondyService->setSecretKey($this->configurationService->getOptionSecretKey());

            $response = $this->fondyService->reverse();

            if ($response) {
                return $response;
            }

            $this->setErrorMessage($this->fondyService->getStatusMessage());
        } catch (Exception $exception) {
            $this->setErrorMessage($exception->getMessage());
        }

        return false;
    }

    /**
     * @param $errorMessage
     * @return void
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->fondyService->getStatusMessage();
    }

    /**
     * @param \Magento\Sales\Model\Order|OrderInterface $order
     * @return string
     */
    public function generateOrderDescription(Order $order)
    {
        $description = '';

        /**
         * @var \Magento\Sales\Model\Order\Item $item
         */
        foreach ($order->getItemsCollection() as $item) {
            $description .= sprintf('Name: %s ', $item->getName());
            $description .= sprintf('Price: %s ', $this->formatNumberPrecision($item->getPrice()));
            $description .= sprintf('Qty: %s ', $this->formatNumberPrecision($item->getQtyOrdered()));
            $description .= sprintf("Amount: %s\n", $this->formatNumberPrecision($item->getBaseRowTotal()));
        }

        return $description;
    }

    /**
     * @param \Magento\Sales\Model\Order|OrderInterface $order
     * @return array|string
     */
    public function generateReservationData(Order $order)
    {
        $billingAddress = $order->getBillingAddress();

        if (!$billingAddress) {
            return '';
        }

        $street = $billingAddress->getStreet();

        if (is_array($street)) {
            $street = implode(' ', $street);
        }

        $countryId = $billingAddress->getCountryId();
        $countryCode = '';

        if ($countryId) {
            try {
                $countryInfo = $this->countryInformationAcquirerInterface->getCountryInfo($countryId);
                $countryCode = $countryInfo->getThreeLetterAbbreviation();
            } catch (NoSuchEntityException $noSuchEntityException) {}
        }

        $objectManager = ObjectManager::getInstance();
        $productMetadata = $objectManager->get(ProductMetadataInterface::class);
        $version = $productMetadata->getVersion();

        $reservationData = array(
            'phonemobile' => $billingAddress->getTelephone(),
            'customer_address' => $street,
            'customer_country' => $countryCode,
            'customer_state' => $billingAddress->getRegion(),
            'customer_name' => $order->getCustomerName(),
            'customer_city' => $billingAddress->getCity(),
            'customer_zip' => $billingAddress->getPostcode(),
            'account' => $order->getCustomerId(),
            'products' => $this->generateProductsParameter($order),
            'cms_name' => 'Magento',
            'cms_version' => $version,
            'shop_domain' => $_SERVER['SERVER_NAME'] ?: $_SERVER['HTTP_HOST'],
            'path' => $_SERVER['REQUEST_URI']
        );

        $reservationData['uuid'] = sprintf('%s_%s', $reservationData['shop_domain'], $reservationData['cms_name']);

        return $reservationData;
    }

    /**
     * @param \Magento\Sales\Model\Order|OrderInterface $order
     * @return array
     */
    public function generateProductsParameter(Order $order)
    {
        $products = [];

        /**
         * @var \Magento\Sales\Model\Order\Item $item
         */
        foreach ($order->getItemsCollection() as $item) {
            $products[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'price' => number_format((float) $item->getPrice(), self::PRECISION),
                'total_amount' => number_format((float) $item->getBaseRowTotal(), self::PRECISION),
                'quantity' => number_format((float) $item->getQtyOrdered(), self::PRECISION),
            ];
        }

        return $products;
    }

    /**
     * @param \Magento\Sales\Model\Order|OrderInterface $order
     * @return array|null
     */
    public function generateMerchantData(Order $order)
    {
        $addData = $order->getBillingAddress()->getData();

        if (!$addData){
            $addData = $order->getShippigAddress()->getData();
        }

        if ($addData){
            $addInfo = [
                'Fullname' => $addData['firstname'] . ' ' . $addData['middlename'] . ' ' . $addData['lastname']
            ];

            return $addInfo;
        }

        return null;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        $orderId = $this->checkoutSession->getLastOrderId();

        if (!$orderId) {
            $orderId = $this->checkoutSession->getLastRealOrderId();
        }

        if (!$orderId && !empty($_REQUEST['orderId'])) {
            $orderId = (int) $_REQUEST['orderId'];
        }

        return $orderId;
    }

    /**
     * @param $token
     * @return string
     * @throws \Fondy\Fondy\Service\Exception\Json\EncodeJsonException
     */
    public function generateCheckoutOptions($token)
    {
        $bankLinksDefaultCountry = $this->configurationService->getConfigurationValue(['bank_links_payment_method', 'bank_links_default_country']);
        $countries = $this->configurationService->getConfigurationValue(['bank_links_payment_method', 'specificcountry']);
        $titleCards = $this->configurationService->getConfigurationValue(['cards_payment_method', 'cards_payment_title']);
        $titleBankLinks = $this->configurationService->getConfigurationValue(['bank_links_payment_method', 'bank_links_payment_title']);
        $titleWallets = $this->configurationService->getConfigurationValue(['wallets_payment_method', 'wallets_payment_title']);
        $title = $this->configurationService->getConfigurationValue('title');
        $languageCode = $this->getLanguageCode();

        if ($titleCards && $this->configurationService->isConfigurationCardsPaymentEnabled()) {
            $this->fondyService->setCardsTitle($languageCode, $titleCards);
        }

        if ($this->configurationService->isConfigurationBankLinksPaymentEnabled()) {
            if ($titleBankLinks) {
                $this->fondyService->setBankLinksTitle($languageCode, $titleBankLinks);
            }

            if ($countries) {
                if (!is_array($countries)) {
                    if (strpos($countries, ',') !== false) {
                        $countries = explode(',', $countries);
                    } else {
                        $countries = [$countries];
                    }
                }

                $this->fondyService->setRequestParameterCountries($countries);
            }

            if ($bankLinksDefaultCountry) {
                $this->fondyService->setRequestParameterDefaultCountry($bankLinksDefaultCountry);
            }
        }

        if ($titleWallets && $this->configurationService->isConfigurationWalletsPaymentEnabled()) {
            $this->fondyService->setWalletsTitle($languageCode, $titleWallets);
        }

        if ($title) {
            $this->fondyService->setRequestParameterTitle($title);
        }

        return $this->fondyService->getCheckoutOptions();
    }

    /**
     * @param \Magento\Sales\Model\Order|OrderInterface $order
     * @return float
     */
    private function getAmount(Order $order)
    {
        return $order->getGrandTotal();
    }

    /**
     * @param $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface|Order
     */
    private function getOrder($orderId)
    {
        return $this->orderRepository->get($orderId);
    }

    /**
     * @return string
     */
    private function getLanguageCode()
    {
        return strstr($this->localeResolver->getLocale(), '_', true);
    }

    /**
     * @param int $number
     * @param int $precision
     * @return string
     */
    private function formatNumberPrecision($number, $precision = self::PRECISION)
    {
        return number_format($number, $precision);
    }
}
