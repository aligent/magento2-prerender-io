<?php
/*
 * Copyright (c) Aligent Consulting. All rights reserved.
 */

declare(strict_types=1);
namespace Aligent\PrerenderIo\Api;

interface PrerenderClientInterface
{
    public function recacheUrls(array $urls, int $storeId): void;
}
