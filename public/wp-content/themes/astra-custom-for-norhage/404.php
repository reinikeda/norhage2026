<?php
/**
 * 404 Template — Norhage Child Theme
 * Translatable via 'nh-theme' text domain
 */
get_header();
?>

<style>
.nh-404-wrapper {
    max-width: 600px;
    margin: 100px auto;
    padding: 0 20px 80px;
    text-align: center;
    font-family: inherit;
    color: var(--nh-charcoal, #2C2A29);
}

.nh-404-code {
    font-size: 6rem;
    font-weight: 800;
    line-height: 1;
    color: var(--nh-mint, #C3E8C6);
    margin-bottom: 8px;
    letter-spacing: -2px;
}

.nh-404-wrapper h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--nh-forest, #1E3932);
    margin-bottom: 16px;
}

.nh-404-subtitle {
    font-size: 1rem;
    color: var(--ui-muted, #8e8c89);
    line-height: 1.75;
    margin-bottom: 40px;
}

.nh-404-btn {
    display: inline-block;
    padding: 13px 32px;
    background-color: var(--nh-green, #00704A);
    color: #fff;
    border: 2px solid var(--nh-green, #00704A);
    border-radius: 4px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s, border-color 0.2s;
    letter-spacing: 0.01em;
}

.nh-404-btn:hover {
    background-color: var(--nh-forest, #1E3932);
    border-color: var(--nh-forest, #1E3932);
    color: #fff;
}
</style>

<div class="nh-404-wrapper">

    <div class="nh-404-code">404</div>

    <h1><?php esc_html_e( "We couldn't find that page", 'nh-theme' ); ?></h1>

    <p class="nh-404-subtitle">
        <?php esc_html_e( "We've recently updated our website and are doing everything we can to fix broken links — but sometimes a 404 still slips through. Feel free to browse our updated site.", 'nh-theme' ); ?>
    </p>

    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="nh-404-btn">
        <?php esc_html_e( 'Back to Homepage', 'nh-theme' ); ?>
    </a>

</div>

<?php get_footer(); ?>
