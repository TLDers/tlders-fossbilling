<?php

declare(strict_types=1);

namespace Box\Mod\Tldersprices\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function fetchNavigation(): array
    {
        return [
            'subpages' => [
                [
                    'location' => 'system',
                    'index' => 160,
                    'label' => __trans('TLDers Price Monitor'),
                    'uri' => $this->di['url']->adminLink('tldersprices'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app): void
    {
        $app->get('/tldersprices', 'get_index', [], static::class);
    }

    public function get_index(\Box_App $app): string
    {
        $this->di['is_admin_logged'];
        $data = $this->di['api_admin']->tldersprices_comparison(['refresh' => !empty($_GET['refresh'])]);

        return $app->render('mod_tldersprices_index', $data);
    }
}
