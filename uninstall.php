<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

delete_option('lws_users_login_translate');
delete_option('lws_users_register_translate');
delete_option('lws_users_username_translate');
delete_option('lws_users_email_translate');
delete_option('lws_users_password_translate');
delete_option('lws_users_confirm_translate');
delete_option('lws_users_remember_translate');
delete_option('lws_users_submit_translate');
delete_option('lws_users_logout_translate');
delete_option('lws_users_welcome_translate');
delete_option('lws_users_pregister_alert_admin');

delete_option('lws_users_lost_translate');
delete_option('lws_users_plugin_actmail');
delete_option('lws_users_plogin_activation');
delete_option('lws_users_pwd_lost_mail_content');

delete_site_option('lws_users_version');

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lws_user_redir_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE option_name LIKE 'lws_user_redir_%'" );

?>
