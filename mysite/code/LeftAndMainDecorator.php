<?php
class LeftAndMainDecorator extends Extension
{
    public function onAfterInit() {
	CMSMenu::remove_menu_item('Help');
	CMSMenu::remove_menu_item('ReportAdmin');
	CMSMenu::remove_menu_item('CMSPagesController');
	CMSMenu::remove_menu_item('CMSSettingsController');
    }
}
