<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    return;
}

delete_option('woocommerce_truelayer_settings');
