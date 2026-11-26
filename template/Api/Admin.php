<?php

declare(strict_types=1);

namespace __MOD_NS__\Tldersprices\Api;

__API_COMPAT__
/**
 * TLDers Price Monitor admin API (tldersprices_comparison).
 */
class Admin extends __API_BASE__
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
