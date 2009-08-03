<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Paypal Payment Data Transfer (PDT) class for PHP 5
 *
 * A simple class encapsulating Paypal's PDT logic.
 *
 * Based on Paypal's DevCentral code example, but made pretty.
 * 
 * Paypal API Documentation:
 * https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_html_paymentdatatransfer
 *
 * Author: Pedro Fayolle (a.k.a. Pilaf)
 * Version: 0.1
 * License: BSD
 * Date: 2009-08-03
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 * Requirements:
 *
 *   PHP >= 5.1 with sockets support enabled
 *
 * Basic usage:
 *
 *   $pdt = new PaypalPaymentDataTransfer($_GET['tx'], $auth_token);
 *   
 *   if ($pdt->success) {
 *		// Output all raw data:
 *		var_dump($pdt->data);
 *
 *      // Output just one property:
 *      echo $pdt->mc_gross;
 *   }
 *
 * Options:
 *
 *   Pass options as an associative array in the third parameter of the
 *   class constructor.
 *
 *   - use_sandbox     Use Paypal's sandbox server instead of production
 *	                   Default: false
 *
 *   - use_ssl         Use HTTPS
 *                     Default: false
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

class PaypalPaymentDataTransfer
{
	const PRODUCTION_SERVER_HOST = 'www.paypal.com';
	const SANDBOX_SERVER_HOST = 'sandbox.paypal.com';

	private $options = array();
	private $transaction_token;
	private $auth_token;
	private $response;

	public $data = array();
	public $success = false;
	
	function __construct($transaction_token, $auth_token, $options = array())
	{
		$this->options = $options;
		$this->transaction_token = $transaction_token;
		$this->auth_token = $auth_token;

		if ($this->request()) {
			$this->close_socket();
			$this->parse_data();
		}
	}

	private function __get($name)
	{
		return $this->data[$name];
	}

	private function parse_data()
	{
		$lines = explode("\n", $this->response);

		$this->success = (strcmp($lines[0], 'SUCCESS') == 0);

		if (!$this->success) {
			return false;
		}

		for ($i = 1; $i < count($lines); ++$i) {
			list($key, $value) = explode('=', $lines[$i]);
			$this->data[urldecode($key)] = urldecode($value);
		}

		return true;
	}

	private function request()
	{
		if (!$this->open_socket()) {
			return false;
		}

		$request = "cmd=_notify-synch&tx={$this->transaction_token}&at={$this->auth_token}";
		$header .= "POST /cgi-bin/webscr HTTP/1.0\r\n"
		         . "Content-Type: application/x-www-form-urlencoded\r\n"
		         . "Content-Length: " . strlen($request) . "\r\n\r\n";

		fputs($this->socket, $header . $request);

		$this->response = '';
		$header_done = false;

		while (!feof($this->socket)) {
			$line = fgets($this->socket, 1024);

			if (strcmp($line, "\r\n") === 0) {
				$header_done = true;
			} else if ($header_done) {
				$this->response .= $line;
			}
		}

		return $header_done;
	}

	private function close_socket()
	{
		fclose($this->socket);
	}

	private function open_socket() 
	{
		return $this->socket = fsockopen($this->get_protocol() . $this->get_server_host(), $this->get_port(), $errno, $errstr, 30);
	}

	private function get_server_host()
	{
		return $this->options['use_sandbox'] ? self::SANDBOX_SERVER_HOST : self::PRODUCTION_SERVER_HOST;
	}

	private function get_protocol()
	{
		return $this->options['use_ssl'] ? 'ssl://' : '';
	}

	private function get_port()
	{
		return $this->options['use_ssl'] ? 443 : 80;
	}
}
