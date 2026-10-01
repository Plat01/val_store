<?php
/**
 * Plugin Name: Собери станок — ядро
 * Description: Данные магазина, интеграции и доработки сайта. Модули лежат в mu-plugins/soberi-stanok/.
 */

defined('ABSPATH') || exit;

foreach (glob(__DIR__ . '/soberi-stanok/*.php') as $ss_module) {
    require_once $ss_module;
}
unset($ss_module);
