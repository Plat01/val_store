<?php
/**
 * Локальная почта: если в wp-config задан SS_SMTP_HOST (docker → Mailpit),
 * все письма уходят туда без авторизации. На проде константа не задаётся,
 * почтой управляет FluentSMTP.
 */

defined('ABSPATH') || exit;

if (defined('SS_SMTP_HOST')) {
    add_action('phpmailer_init', static function ($mailer) {
        $mailer->isSMTP();
        $mailer->Host       = SS_SMTP_HOST;
        $mailer->Port       = defined('SS_SMTP_PORT') ? SS_SMTP_PORT : 1025;
        $mailer->SMTPAuth   = false;
        $mailer->SMTPSecure = '';
        $mailer->SMTPAutoTLS = false;
    });

    // wordpress@localhost PHPMailer считает невалидным адресом.
    add_filter('wp_mail_from', static fn () => 'noreply@soberi-stanok.test');
}
