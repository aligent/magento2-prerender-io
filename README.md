# magento2-prerender-io
Provides integration between Magento 2 and [Prerender.io](https://prerender.io), giving the ability for product pages to be automatically recached when a product is updated.

## Overview
This module provides a new indexer, `prerender_io_product`, which will send URL recache requests to Prerender.io (in batches of up to 1000) when changes are made to products.
This will ensure that the cache product pages are kept up-to-date at all times.

## Installation
To install via composer, simply run:

```bash
composer require aligent/magento2-prerender-io
```

Then, ensure the module is installed and the index is set to `Schedule`:

```bash
bin/magento module:enable Aligent_PrerenderIo
bin/magento setup:upgrade
bin/magento indexer:set-mode schedule prerender_io_product
```

## Configuration
The extension can be configured via `Stores -> Configuration -> System -> Prerender.io`
