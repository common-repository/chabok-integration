<?php
/**
 * Utility functions.
 *
 * @package ChabokIO
 * @subpackage Misc
 */

/**
 * Starts a session if it wasn't already
 * started.
 *
 * @return void
 */
function chabok_start_session()
{
    if (!session_id()) {
        session_start();
    }
}

/**
 * Gets the current user ID for registering
 * in Chabok.
 *
 * @return string|null
 */
function chabok_get_user()
{
    global $chabok_options;

    $user = sanitize_text_field(wp_get_current_user());
    $key = null;
    $attribute = isset($chabok_options['user_id_key']) ? $chabok_options['user_id_key'] : 'user_email';
    if ($user) {
        $key = $user->get($attribute);
    }

    return $key;
}

/**
 * Adds menu links to the plugin entry
 * in the plugins menu.
 *
 * @param array $links
 * @param string $file
 * @return array
 */
function chabok_action_links($links, $file)
{
    if ($file === plugin_basename(CHABOK_ROOT)) {
        $plugin_links[] = '<a href="' . admin_url('admin.php?page=chabok') . '">' . __('Settings', 'chabok-io') . '</a>';
        $plugin_links[] = '<a target="_blank" href="https://doc.chabok.io/wordpress/">' . __('Documentation', 'chabok-io') . '</a>';

        foreach ($plugin_links as $link) {
            array_unshift($links, $link);
        }
    }

    return $links;
}
add_filter('plugin_action_links', 'chabok_action_links', 10, 2);

/**
 * return chabok user_id and installation_id
 *
 * @return array
 */
function chabok_user_data()
{
    chabok_start_session();
    $installation_id = sanitize_text_field($_SESSION['chabok_device_id']);
    $user_id = sanitize_text_field(chabok_get_user());
    if (!$user_id) {
        if (isset($_SESSION['chabok_user_id'])) {
            $user_id = sanitize_text_field($_SESSION['chabok_user_id']);
        }
    }
    return array($user_id, $installation_id);
}

/**
 * log custom message on file
 * @param string message
 * @return void
 */
function chabok_log($message, $type)
{
    global $chabok_options;
    if (isset($chabok_options['chabok_log_' . $type]) || 'on' === $chabok_options['chabok_log_' . $type]) {
        if (is_array($message)) {
            $message = json_encode($message);
        }
        $file = fopen(WP_CONTENT_DIR . "/chabok_$type.log", "a");
        fwrite($file, "\n" . date('Y-m-d h:i:s') . " :: " . $message);
        fclose($file);
    }
}
