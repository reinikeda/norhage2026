<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Ticker Line',
        'Ticker Line',
        'manage_options',
        'rtl-ticker-line',
        'rtl_render_admin_page',
        'dashicons-megaphone',
        80
    );
});

function rtl_render_admin_page() {
    $messages = get_option('rtl_ticker_messages', []);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $messages = [];
        for ($i = 0; $i < 5; $i++) {
            if (!empty($_POST["message_$i"])) {
                $messages[] = [
                    'message' => wp_kses_post($_POST["message_$i"]),
                    'start' => sanitize_text_field($_POST["start_$i"]),
                    'end' => sanitize_text_field($_POST["end_$i"]),
                ];
            }
        }
        update_option('rtl_ticker_messages', $messages);
        echo '<div class="updated"><p>Messages saved.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Ticker Line Messages</h1>
        <form method="post">
            <?php for ($i = 0; $i < 5; $i++):
                $msg = $messages[$i] ?? ['message' => '', 'start' => '', 'end' => ''];
            ?>
                <h3>Message <?php echo $i + 1; ?></h3>
                <textarea name="message_<?php echo $i; ?>" rows="2" cols="80"><?php echo esc_textarea($msg['message']); ?></textarea><br>
                <label>Start Date: 
                    <input type="date" name="start_<?php echo $i; ?>" value="<?php echo esc_attr($msg['start']); ?>" />
                </label>
                <label>End Date: 
                    <input type="date" name="end_<?php echo $i; ?>" value="<?php echo esc_attr($msg['end']); ?>" />
                </label>
                <hr>
            <?php endfor; ?>
            <input type="submit" class="button button-primary" value="Save Messages">
        </form>
    </div>
    <?php
}
