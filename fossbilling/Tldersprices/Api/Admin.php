<?php

declare(strict_types=1);

namespace Box\Mod\Tldersprices\Api;

// FOSSBilling 0.8.9 uses \FOSSBilling\Api\AbstractApi; the development branch renamed it.
if (!class_exists('\FOSSBilling\Api\AbstractApi') && class_exists('\FOSSBilling\Core\Api\AbstractApi')) {
    class_alias('\FOSSBilling\Core\Api\AbstractApi', '\FOSSBilling\Api\AbstractApi');
}

/**
 * TLDers Price Monitor admin API (tldersprices_comparison).
 */
class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Your domain prices vs the cheapest market price for each TLD.
     *
     * @optional bool $refresh - ignore the 24 hour cache
     */
    public function comparison($data = []): array
    {
        $this->checkPermissions('tldersprices', 'view');

        return $this->getService()->comparison(!empty($data['refresh']));
    }
}
