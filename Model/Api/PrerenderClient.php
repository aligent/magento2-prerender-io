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
     * Call Prerender service API to recache list of URLs
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
            $this->sendRequest($batch, $token, $storeId);
        }
    }

    /**
     * Sends a JSON POST request to Prerender service
     *
     * @param array $urls
     * @param string $token
     * @param int|null $storeId
     * @return void
     */
    private function sendRequest(array $urls, string $token, ?int $storeId = null): void
    {
        $prerenderServiceUrl = $this->prerenderConfigHelper->getPrerenderServiceUrl($storeId);

        if (empty($prerenderServiceUrl)) {
            $this->logger->error(
                __('ERROR: prerender url not found. Store ID: %1', $storeId)
            );
            return;
        }

        $payload = $this->jsonSerializer->serialize(
            [
                'prerenderToken' => $token,
                'urls' => $urls
            ]
        );
        try {
            $this->client->post($prerenderServiceUrl, $payload);
        } catch (\Exception $e) {
            $this->logger->error(
                __('Error sending payload %1 to Prerender service. Store ID: %2', $payload, $storeId),
                ['exception' => $e]
            );
        }
    }
}
