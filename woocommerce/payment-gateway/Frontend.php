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

namespace SkyVerge\WooCommerce\PluginFramework\v5_0_1\PaymentGateway;

use SkyVerge\WooCommerce\PluginFramework\v5_0_1\SV_WC_Payment_Gateway as Gateway;

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( '\\SkyVerge\\WooCommerce\\PluginFramework\\v5_0_1\\PaymentGateway\\Frontend' ) ) :

/**
 * Frontend handler class.
 *
 * Instantiates the frontend handlers like Payment Methods and the Payment Form.
 *
 * @since 5.1.0-dev
 */
class Frontend {


	/** @var Frontend\Payment_Methods payment methods handler instance */
	protected $payment_methods;

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

		if ( $gateway->supports_payment_methods() ) {
			$this->payment_methods = new Frontend\Payment_Methods( $this->get_gateway() );
		}
	}


	/**
	 * Gets the payment methods handler instance.
	 *
	 * @since 5.1.0-dev
	 *
	 * @return Frontend\Payment_Methods
	 */
	public function get_payment_methods_handler() {

		return $this->payment_methods;
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
