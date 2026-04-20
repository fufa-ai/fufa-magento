<?php
declare(strict_types=1);

namespace Fufa\Webhook\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'fufa_webhook/general/enabled';
    private const XML_PATH_ENDPOINT_URL = 'fufa_webhook/general/endpoint_url';
    private const XML_PATH_HMAC_SECRET = 'fufa_webhook/general/hmac_secret';
    private const XML_PATH_ABANDON_THRESHOLD_MINUTES = 'fufa_webhook/general/abandon_threshold_minutes';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(int|string|null $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getEndpointUrl(int|string|null $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_ENDPOINT_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function getHmacSecret(int|string|null $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_HMAC_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value === '') {
            return '';
        }

        return (string) $this->encryptor->decrypt($value);
    }

    public function getAbandonThresholdMinutes(int|string|null $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_ABANDON_THRESHOLD_MINUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value > 0 ? $value : 60;
    }
}
