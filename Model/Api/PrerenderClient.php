<?php
/*
 * Copyright (c) Aligent Consulting. All rights reserved.
 */

declare(strict_types=1);
namespace Aligent\PrerenderIo\Model\Api;

use Aligent\PrerenderIo\Api\PrerenderClientInterface;
use Aligent\PrerenderIo\Helper\Config;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class PrerenderClient implements PrerenderClientInterface
{

    private const MAX_URLS = 1000;
    private const PRERENDER_RECACHE_URL = 'https://api.prerender.io/recache';

    /** @var Config  */
    private Config $prerenderConfigHelper;
    /** @var ClientInterface  */
    private ClientInterface $client;
    /** @var SerializerInterface  */
    private SerializerInterface $jsonSerializer;
    /** @var LoggerInterface  */
    private LoggerInterface $logger;

    /**
     *
     * @param Config $prerenderConfigHelper
     * @param ClientInterface $client
     * @param SerializerInterface $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $prerenderConfigHelper,
        ClientInterface $client,
        SerializerInterface $jsonSerializer,
        LoggerInterface $logger
    ) {
        $this->prerenderConfigHelper = $prerenderConfigHelper;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->client = $client;
        $this->client->addHeader('content-type', 'application/json');
    }

    /**
     * Call Prerender.io API to recache list of URLs
     *
     * @param array $urls
     * @param int $storeId
     * @return void
     */
    public function recacheUrls(array $urls, int $storeId): void
    {
        if (!$this->prerenderConfigHelper->isRecacheEnabled($storeId)) {
            return;
        }
        $token = $this->prerenderConfigHelper->getToken($storeId);
        if (empty($token)) {
            $this->logger->info(__('Prerender token is not set'));
            return;
        }

        $batches = array_chunk($urls, self::MAX_URLS);
        foreach ($batches as $batch) {
            $this->sendRequest($batch, $token);
        }
    }

    /**
     * Sends a JSON POST request to Prerender.io
     *
     * @param array $urls
     * @param string $token
     * @return void
     */
    private function sendRequest(array $urls, string $token): void
    {
        $payload = $this->jsonSerializer->serialize(
            [
                'prerenderToken' => $token,
                'urls' => $urls
            ]
        );
        try {
            $this->client->post(self::PRERENDER_RECACHE_URL, $payload);
        } catch (\Exception $e) {
            $this->logger->error(
                __('Error sending payload %1 to prerender.io', $payload),
                ['exception' => $e]
            );
        }
    }
}
