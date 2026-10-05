<?php
/** Staging на VPS: письма ждут настройки SMTP на этапе 8. */
defined('ABSPATH') || exit;
if (wp_get_environment_type() === 'staging') {
    // Не отправлять реальные письма из перенесённого staging с заглушками.
    add_filter('pre_wp_mail', static fn () => false);
    add_filter('wp_is_application_passwords_available', '__return_false');
}
