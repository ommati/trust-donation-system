<?php
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo '✅ mod_rewrite is ENABLED';
    } else {
        echo '❌ mod_rewrite is DISABLED - Contact your hosting provider';
    }
} else {
    echo '⚠️ Cannot determine mod_rewrite status - Check with cPanel support';
}
?>
