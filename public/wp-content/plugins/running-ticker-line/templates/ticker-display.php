<?php
$messages = rtl_get_active_messages();
if (empty($messages)) return;
?>
<div id="rtl-ticker-wrapper">
    <div id="rtl-ticker">
        <div class="rtl-ticker-content">
            <?php foreach ($messages as $msg): ?>
                <span class="rtl-ticker-item"><?php echo $msg['message']; ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
