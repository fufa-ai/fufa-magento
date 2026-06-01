<?php
declare(strict_types=1);

namespace Fufa\Webhook\Block;

use Fufa\Webhook\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class LiveChat extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getChannelId(): string
    {
        return $this->config->getLiveChatChannelId();
    }
}
