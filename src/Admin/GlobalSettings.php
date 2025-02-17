<?php

declare(strict_types=1);

namespace Blink\WC\Admin;

use Blink\WC\Helpers\Logger;
use Blink\WC\Helpers\OrderStates;
use Blink\WC\Helpers\GaloyApiHelper;

class GlobalSettings extends \WC_Settings_Page {
  private GaloyApiHelper $apiHelper;

  public function __construct() {
    $this->id = 'blink_settings';
    $this->label = __('Blink Settings', 'blink-for-woocommerce');
    $this->apiHelper = new GaloyApiHelper();

    // Register custom field type order_states with OrderStatesField class.
    add_action('woocommerce_admin_field_order_states', [
      new OrderStates(),
      'renderOrderStatesHtml',
    ]);
    add_action('woocommerce_admin_field_custom_markup', [
      $this,
      'output_custom_markup_field',
    ]);

    if (is_admin()) {
      // Register and include JS.
      wp_enqueue_script('galoy_blink_global_settings');
      wp_localize_script('galoy_blink_global_settings', 'BlinkGlobalSettings', [
        'url' => admin_url('admin-ajax.php'),
        'apiNonce' => wp_create_nonce('galoy-blink-api-url-nonce'),
      ]);

      // Register and include CSS.
      wp_register_style(
        'galoy_blink_admin_styles',
        BLINK_PLUGIN_URL . 'assets/css/admin.css',
        [],
        BLINK_VERSION
      );
      wp_enqueue_style('galoy_blink_admin_styles');
    }
    parent::__construct();
  }

  public function output(): void {
    echo '<h1>' .
      _x('Blink Payment settings', 'global_settings', 'blink-for-woocommerce') .
      '</h1>';
    $settings = $this->get_settings_for_default_section();
    \WC_Admin_Settings::output_fields($settings);
  }

  public function get_settings_for_default_section(): array {
    return $this->getGlobalSettings();
  }

  public function getGlobalSettings(): array {
    Logger::debug('Entering Global Settings form.');

    // Check setup status and prepare output.
    $storedApiKey = get_option('galoy_blink_api_key');
    $storedGaloyEnv = get_option('galoy_blink_env');

    $setupStatus =
      '<p class="blink-connection-error">' .
      _x(
        'Not connected. Please configure your api key.',
        'global_settings',
        'blink-for-woocommerce'
      ) .
      '</p>';
    if ($storedGaloyEnv && $storedApiKey) {
      if (GaloyApiHelper::verifyApiKey($storedGaloyEnv, $storedApiKey)) {
        $setupStatus =
          '<p class="blink-connection-success">' .
          _x('Connected.', 'global_settings', 'blink-for-woocommerce') .
          '</p>';
      }
    }

    return [
      // Section connection.
      'title_connection' => [
        'title' => esc_html_x(
          'Connection settings',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'type' => 'title',
        'desc' => sprintf(
          _x(
            'This plugin version is %s and your PHP version is %s. Check out our <a href="https://dev.blink.sv/examples/woocommerce-plugin/" target="_blank">installation instructions</a>. If you need assistance, please come on our <a href="https://chat.galoy.io" target="_blank">chat</a>. Thank you for using Blink!',
            'global_settings',
            'blink-for-woocommerce'
          ),
          BLINK_VERSION,
          PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION
        ),
        'id' => 'galoy_blink_connection',
      ],
      'galoy_env' => [
        'title' => esc_html_x(
          'Blink Environment',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'type' => 'select',
        'options' => [
          'blink' => _x('Blink', 'global_settings', 'blink-for-woocommerce'),
          'staging' => _x('Galoy Staging', 'global_settings', 'blink-for-woocommerce'),
        ],
        'default' => 'Blink',
        'desc' => esc_html_x(
          'Galoy instance.',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'desc_tip' => true,
        'id' => 'galoy_blink_env',
      ],
      'galoy_wallet_type' => [
        'title' => esc_html_x('Blink Wallet', 'global_settings', 'blink-for-woocommerce'),
        'type' => 'select',
        'options' => [
          'bitcoin' => _x('Bitcoin', 'global_settings', 'blink-for-woocommerce'),
          'stablesats' => _x('Stablesats', 'global_settings', 'blink-for-woocommerce'),
        ],
        'default' => 'Blink',
        'desc' => esc_html_x(
          'Galoy/Blink Wallet',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'desc_tip' => true,
        'id' => 'galoy_blink_wallet_type',
      ],
      'api_key' => [
        'title' => esc_html_x(
          'Blink API Key',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'type' => 'text',
        'desc' => _x(
          'Your Blink API Key. If you do not have any yet use <a target="_blank" href="https://dashboard.blink.sv/api-keys">Blink dashboard</a> to get a new one.',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'default' => '',
        'id' => 'galoy_blink_api_key',
      ],
      'webhook_url' => [
        'title' => esc_html_x('Webhook Url', 'blink-for-woocommerce'),
        'type' => 'custom_markup',
        'markup' =>
          WC()->api_request_url('galoy_blink_default') .
          '<p class="description"> Please use <a target="_blank" href="https://dashboard.blink.sv/callback">Blink dashboard</a> to set it up.</p>',
        'id' => 'galoy_blink_webhook_url',
      ],
      'status' => [
        'title' => esc_html_x('Setup status', 'global_settings', 'blink-for-woocommerce'),
        'type' => 'custom_markup',
        'markup' => $setupStatus,
        'id' => 'galoy_blink_status',
      ],
      'sectionend_connection' => [
        'type' => 'sectionend',
        'id' => 'galoy_blink_connection',
      ],
      // Section general.
      'title' => [
        'title' => esc_html_x(
          'General settings',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'type' => 'title',
        'id' => 'blink_gf',
      ],
      'default_description' => [
        'title' => esc_html_x('Default Customer Message', 'blink-for-woocommerce'),
        'type' => 'textarea',
        'desc' => esc_html_x(
          'Message to explain how the customer will be paying for the purchase. Can be overwritten on a per gateway basis.',
          'blink-for-woocommerce'
        ),
        'default' => esc_html_x(
          'You will be redirected to Blink to complete your purchase.',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'desc_tip' => true,
        'id' => 'galoy_blink_default_description',
      ],
      'order_states' => [
        'type' => 'order_states',
        'id' => 'galoy_blink_order_states',
      ],
      'protect_orders' => [
        'title' => __('Protect order status', 'blink-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'yes',
        'desc' => _x(
          'Protects order status from changing if it is already "processing" or "completed". This will protect against orders getting cancelled via webhook if they were paid in the meantime with another payment gateway. Default is ON.',
          'global_settings',
          'blink-for-woocommerce'
        ),
        'id' => 'galoy_blink_protect_order_status',
      ],
      'debug' => [
        'title' => __('Debug Log', 'blink-for-woocommerce'),
        'type' => 'checkbox',
        'default' => 'yes',
        'desc' => sprintf(
          _x(
            'Enable logging <a href="%s" class="button">View Logs</a>',
            'global_settings',
            'blink-for-woocommerce'
          ),
          Logger::getLogFileUrl()
        ),
        'id' => 'galoy_blink_debug',
      ],
      'sectionend' => [
        'type' => 'sectionend',
        'id' => 'blink_gf',
      ],
    ];
  }

  /**
   * On saving the settings form make sure to check if the API key works and register a webhook if needed.
   */
  public function save() {
    // If we have url, storeID and apiKey we want to check if the api key works and register a webhook.
    Logger::debug('Saving GlobalSettings.');
    if ($this->hasNeededApiCredentials()) {
      $apiEnv = sanitize_text_field($_POST['galoy_blink_env']);
      $apiKey = sanitize_text_field($_POST['galoy_blink_api_key']);

      if (!GaloyApiHelper::verifyApiKey($apiEnv, $apiKey)) {
        $messageException = __(
          'Error fetching data for this API key from server. Please check if the API key is valid.',
          'blink-for-woocommerce'
        );
        Notice::addNotice('error', $messageException);
        Logger::debug($messageException, true);
      }
    } else {
      $messageNotConnecting =
        'Did not try to connect to Blink API because one of the required information was missing: Environment or api key';
      Notice::addNotice('warning', $messageNotConnecting);
      Logger::debug($messageNotConnecting);
    }

    parent::save();
  }

  private function hasNeededApiCredentials(): bool {
    return !empty($_POST['galoy_blink_env']) && !empty($_POST['galoy_blink_api_key']);
  }

  public function output_custom_markup_field($value) {
    echo '<tr valign="top">';
    if (!empty($value['title'])) {
      echo '<th scope="row" class="titledesc">' . esc_html($value['title']) . '</th>';
    } else {
      echo '<th scope="row" class="titledesc">&nbsp;</th>';
    }

    echo '<td class="forminp" id="' . $value['id'] . '">';
    echo $value['markup'];
    echo '</td>';
    echo '</tr>';
  }
}
