<?php

declare(strict_types=1);

namespace __MOD_NS__\Tldersprices\Controller;

__CTRL_COMPAT__
class Admin implements __DI_IFACE__
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

    public function register(__APP__ &$app): void
    {
        $app->get('/tldersprices', 'get_index', [], static::class);
    }

    public function get_index(__APP__ $app): string
    {
        $this->di['is_admin_logged'];
        $data = $this->di['api_admin']->tldersprices_comparison(['refresh' => !empty($_GET['refresh'])]);

        return $app->render('mod_tldersprices_index', $data);
    }
}
