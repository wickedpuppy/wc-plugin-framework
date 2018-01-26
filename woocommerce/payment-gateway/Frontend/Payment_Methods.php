<?php
/**
 * WooCommerce Payment Gateway Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the plugin to newer
 * versions in the future. If you wish to customize the plugin for your
 * needs please refer to http://www.skyverge.com
 *
 * @package   SkyVerge/WooCommerce/Payment-Gateway/Classes
 * @author    SkyVerge
 * @copyright Copyright (c) 2013-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\PluginFramework\v5_0_1\PaymentGateway\Frontend;

use SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Plugin as Plugin;
use SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway as Gateway;
use SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token as Token;
use SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Helper as Helper;
use SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Exception as Exception;

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\v5_0_1\\PaymentGateway\\Frontend\\Payment_Methods' ) ) :

/**
 * Frontend payment methods class.
 *
 * Handles customizing the My Account -> Payment Methods screen.
 *
 * @since 5.1.0-dev
 */
class Payment_Methods {


	/** @var \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token[] method objects */
	protected $tokens = array();

	/** @var \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway payment gateway object */
	protected $gateway;


	/**
	 * Constructs the class.
	 *
	 * @since 5.1.0-dev
	 *
	 * @param \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway payment gateway object
	 */
	public function __construct( Gateway $gateway ) {

		$this->gateway = $gateway;

		if ( $gateway->is_available() && $gateway->supports_tokenization() && $gateway->tokenization_enabled() ) {
			$this->add_hooks();
		}
	}


	/**
	 * Adds the action & filter hooks.
	 *
	 * @internal
	 *
	 * @since 5.1.0-dev
	 */
	protected function add_hooks() {

		// handle the method actions like "delete" or "make default"
		add_action( 'wp', array( $this, 'handle_actions' ) );

		// enqueue the styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );

		// add custom columns to the WooCommerce core payment methods table
		add_filter( 'woocommerce_account_payment_methods_columns', array( $this, 'add_columns' ) );

		// add the gateway's tokens to the WooCommerce core payment methods list
		add_filter( 'woocommerce_saved_payment_methods_list', array( $this, 'add_methods' ), 10, 2 );

		// render a payment method details HTML
		add_action( 'woocommerce_account_payment_methods_column_default', array( $this, 'render_method_default_html' ) );

		// render a payment method details HTML
		add_action( 'woocommerce_account_payment_methods_column_details', array( $this, 'render_method_details_html' ) );
	}


	/**
	 * Enqueues the styles and scripts.
	 *
	 * @since 5.1.0-dev
	 */
	public function enqueue_styles_scripts() {

		if ( $this->is_payment_methods_page() ) {

			wp_enqueue_style( 'sv-wc-payment-gateway-my-account-payment-methods', $this->get_gateway()->get_plugin()->get_payment_gateway_framework_assets_url() . '/css/frontend/sv-wc-payment-gateway-my-account-payment-methods.min.css', array(), Plugin::VERSION );
		}
	}


	/**
	 * Adds custom columns to the WooCommerce core payment methods table.
	 *
	 * @internal
	 *
	 * @since 5.1.0-dev
	 *
	 * @param array $columns payment method table columns
	 * @param array
	 */
	public function add_columns( $columns ) {

		if ( isset( $columns['default'], $columns['details'] ) ) {
			return $columns;
		}

		$custom_columns = array(
			'default' => '&nbsp;',
			'details' => '&nbsp;',
		);

		return Helper::array_insert_after( $columns, 'method', $custom_columns );
	}


	/**
	 * Adds the gateway's tokens to the WooCommerce core payment methods list.
	 *
	 * @internal
	 *
	 * @since 5.1.0-dev
	 *
	 * @param array $methods payment method data
	 * @param int $user_id WordPress user ID
	 */
	public function add_methods( $methods, $user_id ) {

		foreach ( $this->get_tokens( $user_id ) as $token ) {

			$name            = ( $token->get_nickname() ) ? $token->get_nickname() : $token->get_type_full();
			$expiration_date = ( $token->get_exp_month() && $token->get_exp_month() ) ? $token->get_exp_date() : __( 'N/A', 'woocommerce-plugin-framework' );

			$method = array(
				'method' => array(
					'gateway' => $this->get_gateway()->get_id(),
					'brand'   => $name,
				),
				'details'    => $this->get_method_details_html( $token ),
				'expires'    => $expiration_date,
				'is_default' => $token->is_default(),
				'actions'    => $this->get_method_actions( $token ),
			);

			/**
			 * Filters a payment method's item data.
			 *
			 * @since 5.1.0-dev
			 *
			 * @param array $method payment method item data
			 * @param \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token $token method token object
			 */
			$methods[ $token->get_type() ][] = (array) apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_method_data', $method, $token );
		}

		return $methods;
	}


	/**
	 * Renders a payment method default tag HTML.
	 *
	 * @internal
	 *
	 * @since 5.1.0-dev
	 *
	 * @param array $method payment method data
	 */
	public function render_method_default_html( $method ) {

		if ( isset( $method['method']['gateway'] ) && $method['method']['gateway'] === $this->get_gateway()->get_id() && ! empty( $method['is_default'] ) ) {
			echo '<mark class="default">' . esc_html__( 'Default', 'woocommerce-plugin-framework' ) . '</mark>';
		}
	}


	/**
	 * Renders a payment method details HTML.
	 *
	 * @internal
	 *
	 * @since 5.1.0-dev
	 *
	 * @param array $method payment method data
	 */
	public function render_method_details_html( $method ) {

		if ( isset( $method['method']['gateway'] ) && $method['method']['gateway'] === $this->get_gateway()->get_id() && ! empty( $method['details'] ) ) {
			echo wp_kses_post( $method['details'] );
		}
	}


	/**
	 * Gets a token's payment method details HTML.
	 *
	 * This includes the method type icon, last four digits, and "default"
	 * badge if applicable. Example:
	 *
	 * [icon] * * * 1234 [default]
	 *
	 * @since 5.1.0-dev
	 *
	 * @param \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token $token payment method token object
	 * @return array
	 */
	protected function get_method_details_html( Token $token ) {

		$html = '';

		if ( $image_url = $token->get_image_url() ) {
			$html .= sprintf( '<img src="%1$s" alt="%2$s" title="%2$s" width="40" height="25" />', esc_url( $image_url ), esc_attr( $token->get_type_full() ) );
		}

		if ( $last_four = $token->get_last_four() ) {
			$html .= "&bull; &bull; &bull; {$last_four}";
		}

		/**
		 * Filters a payment method's details HTML.
		 *
		 * @since 5.1.0-dev
		 *
		 * @param string $html method details HTML
		 * @param \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token $token method token object
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_method_details_html', $html, $token );
	}


	/**
	 * Gets a token's payment method actions.
	 *
	 * @since 5.1.0-dev
	 *
	 * @param \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token $token payment method token object
	 * @return array
	 */
	protected function get_method_actions( Token $token ) {

		$actions = array(
			'delete' => __( 'Delete', 'woocommerce-plugin-framework' ),
		);

		if ( ! $token->is_default() ) {
			$actions['make-default'] = __( 'Make Default', 'woocommerce-plugin-framework' );
		}

		$gateway_slug = $this->get_gateway()->get_id_dasherized();

		// loop through each action and format for WooCommerce core output
		foreach ( $actions as $action => $name ) {

			$url = add_query_arg( array(
				"wc-$gateway_slug-token-action" => $action,
				"wc-$gateway_slug-token"        => $token->get_id(),
			) );

			$actions[ $action ] = array(
				'name' => $name,
				'url'  => wp_nonce_url( $url, "wc-{$gateway_slug}-{$action}-token", "wc-{$gateway_slug}-token-nonce" )
			);
		}

		/**
		 * Filters a payment method's actions.
		 *
		 * @since 5.1.0-dev
		 *
		 * @param array $actions method actions
		 * @param \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token $token method token object
		 */
		return apply_filters( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_method_actions', $actions, $token );
	}


	/**
	 * Handles the payment method actions.
	 *
	 * @since 5.1.0-dev
	 */
	public function handle_actions() {

		// sanity check to make sure a registered user is on the Payment Methods page
		if ( ! $this->is_payment_methods_page() ) {
			return;
		}

		$gateway_slug = $this->get_gateway()->get_id_dasherized();

		$action   = Helper::get_request( "wc-{$gateway_slug}-token-action" );
		$nonce    = Helper::get_request( "wc-{$gateway_slug}-token-nonce" );
		$token_id = Helper::get_request( "wc-{$gateway_slug}-token" );

		// bail if no token action is requested
		if ( ! $action || ! $token_id ) {
			return;
		}

		try {

			// security check
			if ( ! wp_verify_nonce( $nonce, "wc-{$gateway_slug}-{$action}-token" ) ) {
				throw new Exception( __( 'Oops, something went wrong! Please try again.', 'woocommerce-plugin-framework' ) );
			}

			$user_id = get_current_user_id();

			switch ( $action ) {

				case 'delete':

					if ( $this->get_gateway()->get_payment_tokens_handler()->remove_token( $user_id, $token_id ) ) {

						Helper::wc_add_notice( esc_html__( 'Payment method deleted.', 'woocommerce-plugin-framework' ) );

						/**
						 * Fires after a new payment method is deleted by a customer.
						 *
						 * @since 5.0.0
						 *
						 * @param string $token_id ID of the deleted token
						 * @param int $user_id user ID
						 */
						do_action( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_method_deleted', $token_id, $user_id );

					} else {

						throw new Exception( __( 'Error removing payment method', 'woocommerce-plugin-framework' ) );
					}

				break;

				case 'make-default':

					$this->get_gateway()->get_payment_tokens_handler()->set_default_token( $user_id, $token_id );

					Helper::wc_add_notice( esc_html__( 'Default payment method updated.', 'woocommerce-plugin-framework' ) );

					/**
					 * Fires after a new payment method is made default by a customer.
					 *
					 * @since 5.0.0
					 *
					 * @param string $token_id ID of the modified token
					 * @param int $user_id user ID
					 */
					do_action( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_method_made_default', $token_id, $user_id );

				break;

				// custom actions
				default:

					/**
					 * Fires after a payment method custom action.
					 *
					 * @since 5.1.0-dev
					 *
					 * @param string $token_id paymeth token ID
					 */
					do_action( 'wc_payment_gateway_' . $this->get_gateway()->get_id() . '_payment_method_action_' . sanitize_title( $action ), $token_id );

				break;
			}

		} catch ( Exception $e ) {

			Helper::wc_add_notice( $e->getMessage(), 'error' );
		}

		wp_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
		exit;
	}


	/**
	 * Determines if the Payment Methods page is viewed.
	 *
	 * @since 5.1.0-dev
	 *
	 * @return bool
	 */
	protected function is_payment_methods_page() {
		global $wp;

		return is_user_logged_in() && is_account_page() && isset( $wp->query_vars['payment-methods'] );
	}


	/**
	 * Gets the payment method tokens for the given user.
	 *
	 * @since 5.1.0-dev
	 *
	 * @param int $user_id WordPress user ID
	 * @return \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway_Payment_Token[]
	 */
	protected function get_tokens( $user_id ) {

		if ( empty( $this->tokens ) ) {
			$this->tokens = $this->get_gateway()->get_payment_tokens_handler()->get_tokens( $user_id );
		}

		return $this->tokens;
	}


	/**
	 * Gets the payment gateway instance.
	 *
	 * @since 5.1.0-dev
	 *
	 * @return \SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway
	 */
	protected function get_gateway() {

		return $this->gateway;
	}


}

endif;
