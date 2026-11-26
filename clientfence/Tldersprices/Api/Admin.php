<?php

declare(strict_types=1);

namespace ClientFence\Mod\Tldersprices\Api;


/**
 * TLDers Price Monitor admin API (tldersprices_comparison).
 */
class Admin extends \ClientFence\Api\AbstractApi
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
