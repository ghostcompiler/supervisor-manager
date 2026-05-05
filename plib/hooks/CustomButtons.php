<?php

class Modules_SupervisorManager_CustomButtons extends pm_Hook_CustomButtons
{
    public function getButtons()
    {
        $icon = pm_Context::getBaseUrl() . 'images/icon.svg';
        $link = pm_Context::getBaseUrl() . 'index.php/index/index';
        $domainLink = pm_Context::getBaseUrl() . 'index.php/index/domain';

        $buttons = array();
        $buttons[] = array(
            'place' => self::PLACE_ADMIN_NAVIGATION,
            'section' => self::SECTION_NAV_SERVER_MANAGEMENT,
            'title' => 'Supervisor',
            'description' => 'Manage Supervisor programs',
            'icon' => $icon,
            'link' => $link,
            'newWindow' => false,
        );

        $buttons[] = array(
            'place' => self::PLACE_RESELLER_NAVIGATION,
            'section' => self::SECTION_NAV_ADDITIONAL,
            'title' => 'Supervisor',
            'description' => 'Manage assigned Supervisor programs',
            'icon' => $icon,
            'link' => $link,
            'newWindow' => false,
        );
        $buttons[] = array(
            'place' => self::PLACE_CUSTOMER_HOME,
            'title' => 'Supervisor',
            'description' => 'Manage assigned Supervisor programs',
            'icon' => $icon,
            'link' => $link,
            'newWindow' => false,
        );
        $buttons[] = array(
            'place' => self::PLACE_DOMAIN,
            'title' => 'Supervisor',
            'description' => 'Manage domain Supervisor programs',
            'icon' => $icon,
            'link' => $domainLink,
            'newWindow' => false,
            'contextParams' => true,
        );

        if (
            defined('pm_Hook_CustomButtons::PLACE_DOMAIN_PROPERTIES_DYNAMIC') &&
            defined('pm_Hook_CustomButtons::SECTION_DOMAIN_PROPS_DYNAMIC_DEV_TOOLS')
        ) {
            $buttons[] = array(
                'place' => constant('pm_Hook_CustomButtons::PLACE_DOMAIN_PROPERTIES_DYNAMIC'),
                'section' => constant('pm_Hook_CustomButtons::SECTION_DOMAIN_PROPS_DYNAMIC_DEV_TOOLS'),
                'title' => 'Supervisor',
                'description' => 'Manage Supervisor programs',
                'icon' => $icon,
                'link' => $domainLink,
                'newWindow' => false,
                'contextParams' => true,
            );
        }

        return $buttons;
    }
}
