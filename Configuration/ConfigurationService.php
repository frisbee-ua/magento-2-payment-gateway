<?php

namespace Fondy\Fondy\Configuration;

use Fondy\Fondy\Model\Config\Source\Payment\PaymentType;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

final class ConfigurationService
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var PaymentType
     */
    private $paymentType;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PaymentType $paymentType,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->paymentType = $paymentType;
        $this->encryptor = $encryptor;
    }

    /**
     * @return bool
     */
    public function isConfigurationTestModeEnabled()
    {
        return (bool) $this->getConfigurationValue('test_mode');
    }

    /**
     * @return bool
     */
    public function isConfigurationCardsPaymentEnabled()
    {
        return (bool) $this->getConfigurationValue(['cards_payment_method', 'cards_payment_status']);
    }

    /**
     * @return bool
     */
    public function isConfigurationBankLinksPaymentEnabled()
    {
        return (bool) $this->getConfigurationValue(['bank_links_payment_method', 'bank_links_payment_status']);
    }

    /**
     * @return bool
     */
    public function isConfigurationWalletsPaymentEnabled()
    {
        return (bool) $this->getConfigurationValue(['wallets_payment_method', 'wallets_payment_status']);
    }

    /**
     * @return bool
     */
    public function isConfigurationPreAuthEnabled()
    {
        return (bool) $this->getConfigurationValue('invoice_before_fraud_review');
    }

    /**
     * @return bool
     */
    public function isConfigurationPaymentTypeEmbedded()
    {
        $paymentType = $this->getConfigurationValue('payment_type');

        return (bool) $this->paymentType->isTypeEmbedded($paymentType);
    }

    /**
     * @return bool
     */
    public function isConfigurationPaymentTypeRedirect()
    {
        $paymentType = $this->getConfigurationValue('payment_type');

        return (bool) $this->paymentType->isTypeRedirect($paymentType);
    }

    /**
     * @return string
     */
    public function getOptionMerchantId()
    {
        return trim($this->getPaymentConfigMerchantId());
    }

    /**
     * @return string
     */
    public function getOptionSecretKey()
    {
        $secretKey = trim($this->getPaymentConfigSecretKey());

        return $this->encryptor->decrypt($secretKey);
    }

    /**
     * @return string
     */
    public function getOptionOrderStatusProcessing()
    {
        return $this->getConfigurationValue('order_status_in_progress');
    }

    /**
     * @return string
     */
    public function getOptionOrderStatusCanceled()
    {
        return $this->getConfigurationValue('order_status_if_canceled');
    }

    /**
     * @return string
     */
    public function getOptionOrderStatusPaid()
    {
        return $this->getConfigurationValue('order_status');
    }

    /**
     * @return mixed
     */
    public function getPaymentConfigMerchantId()
    {
        return $this->getConfigurationValue('FONDY_MERCHANT_ID');
    }

    /**
     * @return mixed
     */
    public function getPaymentConfigSecretKey()
    {
        return $this->getConfigurationValue('FONDY_SECRET_KEY');
    }

    /**
     * @param string|array $field
     * @param \Magento\Store\Model\Store|null $store
     * @return mixed
     */
    public function getConfigurationValue($field, $store = null)
    {
        $objectManager = ObjectManager::getInstance();
        $storeManager = $objectManager->get(StoreManagerInterface::class);

        if (null === $store) {
            $store = $storeManager->getStore();
        }

        if (is_array($field)) {
            $field = implode('/', $field);
        }

        $path = sprintf('payment/fondy/%s', $field);

        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $store->getStoreId());
    }
}
