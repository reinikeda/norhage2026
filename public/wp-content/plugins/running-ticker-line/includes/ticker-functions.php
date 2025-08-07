<?php

function rtl_get_active_messages() {
    $messages = get_option('rtl_ticker_messages', []);
    $now = date('Y-m-d');

    return array_filter($messages, function ($msg) use ($now) {
        return empty($msg['start']) || empty($msg['end']) || ($now >= $msg['start'] && $now <= $msg['end']);
    });
}
