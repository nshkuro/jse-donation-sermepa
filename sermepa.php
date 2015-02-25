<?php
/**
 * @version		$Id: $
 * @author		Nik
 * @package		Joomla!
 * @subpackage	Sermepa Payment Plugin
 * @copyright	Copyright (C) 2014 . All rights reserved.
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL version 3
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Joomseller Donation Payment - Sermepa Payment Plugin.
 * @package		Joomseller Donation Payment
 * @subpackage	Payment Plugin
 */
class plgJsePaymentSermepa extends JPlugin
{

	/** @var plugin parameter */
	var $params = null;

	/** @var string Code name of payment method */
	var $_name = 'sermepa';

	/** @var array Sermepa payment data */
	var $data = array();

	/** @var string Sermepa notify URL */
	var $_url = null;

	/** @var integer IPN logging */
	var $ipn_log = 0;

	/** @var string	IPN logging file */
	var $ipn_log_file = null;

	/** @var array IPN data */
	var $ipn_data = array();

	/** @var string Clave secreta de encriptación */
	var $_clave = '';


	/** @var string Tipo de clave */
	var $_encryption = '';


	/**
	 * Constructor.
	 */
	function __construct(&$subject, $params)
	{
		// load plugin parameters
		parent::__construct($subject, $params);
		// init variables
		$this->_name = 'sermepa';
		$this->live_site = JUri::base();
		$this->_url = $this->params->get('url');
		$this->ipn_log = $this->params->get('ipn_log');
		$this->ipn_log_file = JPATH_ROOT .DIRECTORY_SEPARATOR. 'plugins' .DIRECTORY_SEPARATOR. 'jsepayment' .DIRECTORY_SEPARATOR. 'sermepa' .DIRECTORY_SEPARATOR. 'sermepa' .DIRECTORY_SEPARATOR. 'ipn_log.txt';

		// init some default values
		$this->addField('Ds_Merchant_MerchantName', $this->params->get('Ds_Merchant_MerchantName'));
		$this->addField('Ds_Merchant_Titular', $this->params->get('Ds_Merchant_Titular'));
		$this->addField('Ds_Merchant_MerchantCode', $this->params->get('Ds_MerchantCode'));
		$this->_clave =$this->params->get('Ds_MerchantSignature');
		$this->addField('Ds_Merchant_Terminal', $this->params->get('Ds_Merchant_Terminal'));
		$this->addField('Ds_Merchant_Currency', $this->params->get('currency'));
		$this->addField('Ds_Merchant_ConsumerLanguage', $this->params->get('Ds_Merchant_ConsumerLanguage'));
		$this->_encryption = $this->params->get('encryption');
		/*
			Transaction types
			0 - Authorization
 			1 - Pre-authorization
			2 - Confirmation
 			3 - Automatic Refund
 			4 - Payment by Cell Phone
 			5 - Recurrent Transaction
 			6 - Successive Transaction
 			7 - Authentication
 			8 - Confirmation of Authentication
		*/

		$this->addField('Ds_Merchant_TransactionType', '0');
		//$this->addField('Ds_Merchant_PayMethods', $this->params->get('Ds_Merchant_PayMethods'));


	}

	/**
	 * Add form field data
	 */
	function addField($field, $value)
	{
		$this->data["$field"] = $value;
	}

	/**
	 * Get payment info method.
	 */
	function onPaymentInfo()
	{
		if (empty($this->_info))
		{
			$this->_info = array(
				'code' => 'sermepa', // Code to separate payment plugin
				'name' => JText::_('Sermepa'), // Name to display of payment method
				'image' => $this->params->get('payment_image'), // Image to display of payment method
				'use_cc' => 0, // Use credit card or not?
			);
		}

		return $this->_info;
	}

	/**
	 * Process payment method.
	 */
	function onProcessPayment($order)
	{
		if ($order->payment_method != $this->_name)
		{
			return 0;
		}

                $urlMerchant=$order->return_url;

		if ($this->_encryption == 'sha1-enhanced') {
			$signature = strtoupper(sha1(round($order->amount*100, 2) . '000' . $order->id . $this->data['Ds_Merchant_MerchantCode'] . $this->data['Ds_Merchant_Currency'] . 
			$this->data['Ds_Merchant_TransactionType'] . $urlMerchant . $this->_clave));
		} else {
			$signature = strtoupper(sha1($order->amount . $order->id . $this->data['Ds_Merchant_MerchantCode'] . $this->data['Ds_Merchant_Currency'] . $this->_clave));
		}
		
                $this->addField('Ds_Merchant_Amount', round($order->amount*100, 2));
		$this->addField('Ds_Merchant_Order', '000' . $order->id);
		$this->addField('Ds_Merchant_UrlOK', $order->return_url);
		$this->addField('Ds_Merchant_UrlKO', $order->cancel_url);
		$this->addField('Ds_Merchant_ProductDescription', $order->description);
		$this->addField('Ds_Merchant_MerchantSignature', $signature);
		$this->addField('Ds_Merchant_MerchantURL', $urlMerchant);

		$this->redirectingSermepa();

		return 0;
	}

	/**
	 * Process payment method.
	 */
	function onProcessRecurringPayment($order)
	{
		if ($order->payment_method != $this->_name)
		{
			return 0;
		}

		$this->redirectingSermepa();

		return 0;
	}

	/**
	 * Redirecting to sermepa
	 */
	function redirectingSermepa()
	{
		$document = JFactory::getDocument();
		$js = '
			function directToSermepa() {
				document.formSermepa.submit();
			}

			setTimeout("directToSermepa()", 5000);
			';
		$document->addScriptDeclaration($js);
		?>
		<div class="componentheading">
		<?php echo JText::_('Redirecting to sermepa...'); ?>
		</div>
		<div>
		<?php echo JText::_('Please wait while redirecting to sermepa...'); ?>
		</div>
		<form method="post" name="formSermepa" action="<?php echo $this->_url; ?>">
		<?php
		foreach ($this->data as $name => $value)
		{
			echo "<input type=\"hidden\" name=\"$name\" value=\"" . htmlspecialchars($value) . "\" />";
		}
		?>

		<input type="button" class="button" name="submitsermepa" onclick="directToSermepa()" value="Click here" /> if you are not redirected to Sermepa after 5 seconds
		</form>
		<?php
	}

	/**
	 * Get order id from notification.
	 */
	function onPaymentNotify($payment_method)
	{
		if ($payment_method != $this->_name)
		{
			return array();
		}

		return false;
	}

	/**
	 * Verify payment notification.
	 */
	function onVerifyPayment($order)
	{
		if ($order->payment_method != $this->_name)
		{
			return false;
		}
		if ($this->validate_ipn($order))
		{
			return $this->order_stt;
		}

		return false;
	}

}

