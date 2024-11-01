<?php
/*
Plugin Name: Website statistics with Matomo
Plugin URI: 
Description: Get statistics of your website with Matomo
Version: 1.9
Author: Arno Welzel
Author URI: http://arnowelzel.de
Text Domain: webstats-matomo
*/
defined('ABSPATH') or die();

/**
 * Website statistics with Matomo
 * 
 * @package WebstatsMatomo
 */
class WebstatsMatomo
{
    const PLUGIN_VERSION = '1.9';

    var $enableMatomo;
    var $enableCookie;
    var $siteId;
    var $matomoUrl;
    var $authToken;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->enableMatomo = get_option('webstats_matomo_enable_matomo');
        $this->enableCookie = get_option('webstats_matomo_enable_cookie');
        $this->siteId = get_option('webstats_matomo_site_id');
        $this->matomoUrl = sprintf('%s/', rtrim(get_option('webstats_matomo_matomo_url'), '/'));
        $this->authToken = get_option('webstats_matomo_auth_token');
        if ('0' !== $this->enableMatomo && '1' !== $this->enableMatomo) {
            $this->enableMatomo = '0';
        }
        if ('0' !== $this->enableCookie && '1' !== $this->enableCookie) {
            $this->enableCookie = '0';
        }

        if (!is_admin()) {
            add_action('wp_footer', [$this, 'footer']);
        } else {
        }
        add_action('wpmu_new_blog', [$this, 'onCreateBlog'], 10, 6);
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_init', [$this, 'adminInit']);
        add_action('wp_dashboard_setup', [$this, 'setupDashboardWidgets']);
    }

    /**
     * Setup dashbboard widgets
     *
     * @return void
     */
    function setupDashboardWidgets() {
        global $wp_meta_boxes;

        wp_add_dashboard_widget('webstats_matomo_dashboard_widget', __('Website statistics with Matomo', 'webstats-matomo'), [$this, 'widgetAdminMatomo']);
    }

    /**
     * Output admin widget
     *
     * @return void
     */
    function widgetAdminMatomo() {
        $url = sprintf(
            '%s?module=API&method=DevicesDetection.getType&idSite=%s&period=day&date=last7&format=xml&token_auth=%s',
            $this->matomoUrl,
            $this->siteId,
            $this->authToken
        );
        $response = wp_remote_get($url);
        $xml = wp_remote_retrieve_body($response);
        $stats = false;
        if (!empty($xml)) {
            $stats = @simplexml_load_string($xml);
        }

        echo sprintf(
            '<p><a href="%s?idSite=%s" target="_blank">%s</a></p>',
            $this->matomoUrl,
            $this->siteId,
            __('Open Matomo for this site', 'webstats-matomo')
        );

        if (false !== $stats) {
            echo sprintf(
                '<table class="wp-list-table fixed striped table-view-list widefat"><thead><th><strong>%s</strong></th><th class="textright"><strong>%s</strong></th><th class="textright"><strong>%s</strong></th></thead>',
                __('Date', 'webstats-matomo'),
                __('Visits', 'webstats-matomo'),
                __('Views', 'webstats-matomo'),
            );
            foreach ($stats->result as $result) {
                $date = $result->attributes()->date;
                $dateValue = DateTime::createFromFormat('Y-m-d', $date);
                $visits = 0;
                $views = 0;
                foreach ($result->row as $row) {
                    $visits += $row->nb_visits;
                    $views += $row->nb_actions;
                }
                echo sprintf(
                    '<tr><td>%s</td><td class="textright">%d</td><td class="textright">%d</td></tr>',
                    $dateValue->format(get_option('date_format')),
                    $visits,
                    $views
                );
            }
            echo '</table>';
        }
    }

    /**
     * Footer in frontend
     * 
     * @return void
     */
    function footer()
    {
        if ('1' !== $this->enableMatomo) {
            return;
        }
?>
<script>
    var _paq = window._paq = window._paq || [];
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
<?php if ('1' !== $this->enableCookie) { ?>
    _paq.push(['disableCookies']);
<?php } ?>(function() {
        var u="<?php echo $this->matomoUrl; ?>";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '<?php echo $this->siteId; ?>']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    })();
</script>
<?php
    }

    /**
     * Add admin menu in the backend
     * 
     * @return void
     */
    function adminMenu()
    {
        add_options_page(
            __('Website statistics with Matomo', 'webstats-matomo'),
            __('Website statistics with Matomo', 'webstats-matomo'),
            'administrator',
            'webstats-matomo',
            [$this, 'settingsPage']
        );
    }

    /**
     * Initialization: Register settings, create session
     * 
     * @return void
     */
    function adminInit()
    {
        register_setting('webstats-matomo-settings-group', 'webstats_matomo_enable_matomo');
        register_setting('webstats-matomo-settings-group', 'webstats_matomo_site_id');
        register_setting('webstats-matomo-settings-group', 'webstats_matomo_matomo_url');
        register_setting('webstats-matomo-settings-group', 'webstats_matomo_enable_cookie');
        register_setting('webstats-matomo-settings-group', 'webstats_matomo_auth_token');
    }

    /**
     * Output settings page in backend
     * 
     * @return void
     */
    function settingsPage()
    {
        global $wpdb;
?>
<style>
.wsm_text {
    font-size:14px;
}
.wsm_text:first-child {
    padding-top:15px;
}
</style>
<div class="wrap"><h1><?php echo __('Website statistics with Matomo', 'webstats-matomo'); ?></h1>
<form method="post" action="options.php">
<?php settings_fields('webstats-matomo-settings-group'); ?>
<script>
function wsmSwitchTab(tab)
{
    let num=1;
    while (num < 3) {
        if (tab == num) {
            document.getElementById('wsm-switch-'+num).classList.add('nav-tab-active');
            document.getElementById('wsm-tab-'+num).style.display = 'block';
        } else {
            document.getElementById('wsm-switch-'+num).classList.remove('nav-tab-active');
            document.getElementById('wsm-tab-'+num).style.display = 'none';
        }
        num++;
    }
    document.getElementById('wsm-switch-'+tab).blur();
    if (tab == 1 && ("pushState" in history)) {
        history.pushState("", document.title, window.location.pathname+window.location.search);
    } else {
        location.hash = 'tab-' + tab;
    }
    let referrer = document.getElementsByName('_wp_http_referer');
    if (referrer[0]) {
        let parts = referrer[0].value.split('#');
        if (tab>1) {
            referrer[0].value = parts[0] + '#tab-' + tab;
        } else {
            referrer[0].value = parts[0];
        }
    }
}

function wsmUpdateCurrentTab()
{
    if(location.hash == '') {
        wsmSwitchTab(1);
    } else {
        let num = 1;
        while (num < 3) {
            if (location.hash == '#tab-' + num) wsmSwitchTab(num);
            num++;
        }
    }
}
</script>
<nav class="nav-tab-wrapper" aria-label="<?php echo __('Secondary menu'); ?>">
    <a href="#" id="wsm-switch-1" class="nav-tab nav-tab-active" onclick="wsmSwitchTab(1);return false;"><?php echo __('General', 'webstats-matomo'); ?></a>
    <a href="#" id="wsm-switch-2" class="nav-tab" onclick="wsmSwitchTab(2);return false;"><?php echo __('Info', 'webstats-matomo'); ?></a>
</nav>

<table id="wsm-tab-1" class="form-table">
    <tr>
        <th scope="row"><?php echo __('Options', 'webstats-matomo'); ?></th>
        <td>
            <label><input id="webstats_matomo_enable_matomo" type="checkbox" name="webstats_matomo_enable_matomo" value="1"<?php if('1' === $this->enableMatomo) echo ' checked="checked"'; ?> /> <?php echo __('Enable statistics with Matomo', 'webstats-matomo'); ?></label><br />
            <label><input id="webstats_matomo_enable_cookie" type="checkbox" name="webstats_matomo_enable_cookie" value="1"<?php if('1' === $this->enableCookie) echo ' checked="checked"'; ?> /> <?php echo __('Enable tracking cookie', 'webstats-matomo'); ?></label><br />
            <p class="description"><?php echo __('Before enabling the tracking cookie please consider the privacy of your visitors! Matomo works fine without this cookie - you will just not get a detailed analysis about recurring visitors.', 'webstats-matomo'); ?></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="webstats_matomo_matomo_url"><?php echo __('Matomo URL', 'webstats-matomo'); ?></label></th>
        <td>
            <input id="webstats_matomo_matomo_url" class="regular-text" type="text" name="webstats_matomo_matomo_url" value="<?php echo esc_attr(get_option('webstats_matomo_matomo_url')); ?>" />
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="webstats_matomo_site_id"><?php echo __('Matomo site ID', 'webstats-matomo'); ?></label></th>
        <td>
            <input id="webstats_matomo_site_id" class="regular-text" type="text" name="webstats_matomo_site_id" value="<?php echo esc_attr(get_option('webstats_matomo_site_id')); ?>" />
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="webstats_matomo_site_id"><?php echo __('Matomo auth token', 'webstats-matomo'); ?></label></th>
        <td>
            <input id="webstats_matomo_auth_token" class="regular-text" type="text" name="webstats_matomo_auth_token" value="<?php echo esc_attr(get_option('webstats_matomo_auth_token')); ?>" />
            <p class="description"><?php echo __('Generate this token once in Matomo (Settings → Personal → Security) and then paste it here.', 'webstats-matomo'); ?></p>
        </td>
    </tr>
<table>

<div id="wsm-tab-2" style="display:none">
    <p class="wsm_text"><?php echo __('Plugin version', 'webstats-matomo') ?>: <?php echo self::PLUGIN_VERSION; ?></p>
    <p class="wsm_text"><?php echo __('This plugin allows to use a self hosted Matomo server to get website statistics.', 'webstats-matomo'); ?></p>
    <p class="wsm_text"><b><?php echo __('© Arno Welzel 2022', 'webstats-matomo'); ?></b></p>
</div>
<?php submit_button(); ?>
</form>
</div>
<script>
wsmUpdateCurrentTab()
window.addEventListener('popstate', (event) => {
    wsmUpdateCurrentTab();
});
</script>
<?php
    }

    /**
     * Handler for creating a new blog
     * 
     * @param mixed $blog_id ID of the blog
     * @param mixed $user_id ID of the user
     * @param mixed $domain  Domain of the blog
     * @param mixed $path    Path inside the domain
     * @param mixed $site_id ID of the site
     * @param mixed $meta    Metadata
     *
     * @return void
     */
    function onCreateBlog($blog_id, $user_id, $domain, $path, $site_id, $meta)
    {
        if (is_plugin_active_for_network('webstats-matomo/webstats-matomo.php')) {
            switch_to_blog($blog_id);
            update_option('webstats-matomo_enable_matomo', '0');
            update_option('webstats-matomo_enable_cookie', '0');
            update_option('webstats-matomo_site_id', '');
            update_option('webstats-matomo_matomo_url', '');
            update_option('webstats-matomo_auth_token', '');
            restore_current_blog();
        }
    }

    /**
     * Plugin initialization
     * 
     * @return void
     */
    function init()
    {
        load_plugin_textdomain('webstats-matomo', false, 'webstats-matomo/languages/');
    }
}

$webstats_matomo = new WebstatsMatomo();
