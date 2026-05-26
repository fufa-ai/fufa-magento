<?php
/**
 * Copyright © 2026 Fufa All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Fufa\Webhook\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Integration\Model\ConfigBasedIntegrationManager;

final class CreateFufaAiIntegration implements DataPatchInterface
{
    public const INTEGRATION_NAME = 'Fufa AI';

    public function __construct(
        private readonly ConfigBasedIntegrationManager $integrationManager
    ) {
    }

    public function apply(): self
    {
        $this->integrationManager->processIntegrationConfig([self::INTEGRATION_NAME]);
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
