<?php
// this seems to be the preferred way to handle an uninstall
if ( !defined('WP_UNINSTALL_PLUGIN' ) )
	exit();

require_once('core.php');

After_Publish_Drafts::uninstall();
