<?php

class Modules_SupervisorManager_CustomButtons extends pm_Hook_CustomButtons
{
    public function getButtons()
    {
        $icon = pm_Context::getBaseUrl() . 'images/icon.svg';
        $link = pm_Context::getBaseUrl() . 'index.php/index/index';

        return array(
            array(
                'place' => self::PLACE_ADMIN_NAVIGATION,
                'section' => self::SECTION_NAV_SERVER_MANAGEMENT,
                'title' => 'Supervisor',
                'description' => 'Manage Supervisor programs',
                'icon' => $icon,
                'link' => $link,
                'newWindow' => false,
            ),
            array(
                'place' => self::PLACE_RESELLER_NAVIGATION,
                'section' => self::SECTION_NAV_ADDITIONAL,
                'title' => 'Supervisor',
                'description' => 'Manage assigned Supervisor programs',
                'icon' => $icon,
                'link' => $link,
                'newWindow' => false,
            ),
            array(
                'place' => self::PLACE_CUSTOMER_HOME,
                'title' => 'Supervisor',
                'description' => 'Manage assigned Supervisor programs',
                'icon' => $icon,
                'link' => $link,
                'newWindow' => false,
            ),
            array(
                'place' => self::PLACE_DOMAIN,
                'title' => 'Supervisor',
                'description' => 'Manage domain Supervisor programs',
                'icon' => $icon,
                'link' => $link,
                'newWindow' => false,
                'contextParams' => true,
            ),
        );
    }
}
