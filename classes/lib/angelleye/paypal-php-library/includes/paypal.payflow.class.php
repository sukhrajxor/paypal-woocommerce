<?php
/**
 * 	Angell EYE PayPal PayFlow Class
 *	An open source PHP library written to easily work with PayPal's API's
 *
 *  Copyright � 2014  Andrew K. Angell
 *	Email:  andrew@angelleye.com
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 * @package			Angell_EYE_PayPal_PayFlow_Class_Library
 * @author			Andrew K. Angell
 * @copyright       Copyright � 2014 Angell EYE, LLC
 * @link			https://github.com/angelleye/PayPal-PHP-Library
 * @website			http://www.angelleye.com
 * @since			Version 1.52
 * @updated			01.14.2014
 * @filesource
 */

class Angelleye_PayPal_PayFlow extends Angelleye_PayPal_WC
{	
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	config preferences
	 * @return	void
	 */
	function __construct($DataArray)
	{
		$DataArray = apply_filters( 'angelleye_paypal_payflow_construct_params', $DataArray );

		parent::__construct($DataArray);
		
		$this->APIVendor = isset($DataArray['APIVendor']) ? $DataArray['APIVendor'] : '';
		$this->APIPartner = isset($DataArray['APIPartner']) ? $DataArray['APIPartner'] : '';
		$this->Verbosity = isset($DataArray['Verbosity']) ? $DataArray['Verbosity'] : 'HIGH';
                $this->Force_tls_one_point_two = isset($DataArray['Force_tls_one_point_two']) ? $DataArray['Force_tls_one_point_two'] : 'no';
		
		if($this->Sandbox)
		{
			$this->APIEndPoint = apply_filters('aepfw_payments_pro_payflow_endpoint_sandbox', 'https://pilot-payflowpro.paypal.com');
		}
		else
		{
			$this->APIEndPoint = apply_filters('aepfw_payments_pro_payflow_endpoint', 'https://payflowpro.paypal.com');
		}
		
		$this->NVPCredentials = 'BUTTONSOURCE['.strlen($this->APIButtonSource).']='.$this->APIButtonSource.'&VERBOSITY['.strlen($this->Verbosity).']='.$this->Verbosity.'&USER['.strlen($this->APIUsername).']='.$this->APIUsername.'&VENDOR['.strlen($this->APIVendor).']='.$this->APIVendor.'&PARTNER['.strlen($this->APIPartner).']='.$this->APIPartner.'&PWD['.strlen($this->APIPassword).']='.$this->APIPassword;
		$this->NVPCredentials_masked = 'BUTTONSOURCE['.strlen($this->APIButtonSource).']='.$this->APIButtonSource.'&VERBOSITY['.strlen($this->Verbosity).']='.$this->Verbosity.'&USER['.strlen($this->APIUsername).']=*****&VENDOR['.strlen($this->APIVendor).']=*****&PARTNER['.strlen($this->APIPartner).']='.$this->APIPartner.'&PWD['.strlen($this->APIPassword).']='.'*****';

		$this->TransactionStateCodes = array(
				'1' => 'Error',
				'6' => 'Settlement Pending',
				'7' => 'Settlement in Progress',
				'8' => 'Settlement Completed Successfully',
				'11' => 'Settlement Failed',
				'14' => 'Settlement Incomplete'
		);
	}	
	
	/*
	 * GetTransactionStateCodeMessage
	 * 
	 * @access public
	 * @param number
	 * @return string
	 */
	function GetTransactionStateCodeMessage($Code)
	{
		return $this -> TransactionStateCodes[$Code];
	}
	
	/*
	 * CURLRequest
	 * 
	 * @access public
	 * @param string Request
	 * @return string
	 */
	function CURLRequest($Request = "", $APIName = "", $APIOperation = "", $PrintHeaders = false)
	{
	
		$unique_id = date('YmdGis').rand(1000,9999);
                $args = array(
                        'method'      => 'POST',
                        'body'        => $Request,
                        'user-agent'  => __CLASS__,
                        'httpversion' => '1.1',
                        'headers'   => array('Content-Type' => 'text/namevalue', 'Content-Length' => strlen ($Request), 'X-VPS-Timeout: 45' => '45', 'X-VPS-Request-ID' => $unique_id),
                        'timeout'     => 90,
                );
                $response = wp_safe_remote_post( $this->APIEndPoint, $args );
                if ( is_wp_error( $response ) ) {
                        $Response = array( 'CURL_ERROR' => $response->get_error_message() );
                        return $Response;
		} else {
                    parse_str( wp_remote_retrieve_body( $response ), $result );
                    return $result;
                }
	}
	
	
	/**
	 * Convert an NVP string to an array with URL decoded values
	 *
	 * @access	public
	 * @param	string	NVP string
	 * @return	array
	 */
	function NVPToArray($NVPString)
	{
		$proArray = array();
		parse_str($NVPString,$proArray);
		return $proArray;
	}
	
	/*
	 * ProcessTransaction
	 * 
	 * @access public
	 * @param array request parameters
	 * @return array
	 */
	function ProcessTransaction($DataArray)
	{
		$NVPRequest = $this->NVPCredentials;
		$NVPRequestmask = $this->NVPCredentials_masked;
		$star = '*****';
		
		foreach($DataArray as $DataArrayVar => $DataArrayVal)
		{
			if($DataArrayVal != '')
			{
				$NVPRequest .= '&'.strtoupper($DataArrayVar).'['.strlen($DataArrayVal).']='.$DataArrayVal;
                                if(strtoupper($DataArrayVar) == 'ACCT' || strtoupper($DataArrayVar) == 'EXPDATE' || strtoupper($DataArrayVar) == 'CVV2') {
                                    $NVPRequestmask .= '&'.strtoupper($DataArrayVar).'['.strlen($DataArrayVal).']='.'****';
                                } else {
                                    $NVPRequestmask .= '&'.strtoupper($DataArrayVar).'['.strlen($DataArrayVal).']='.$DataArrayVal;
                                } 
				
			}
		}
		
		$NVPResponse = $this->CURLRequest($NVPRequest);
                if( isset( $NVPResponse ) && is_array( $NVPResponse ) && !empty( $NVPResponse['CURL_ERROR'] ) ){
                    return $NVPResponse;
                }
		//$NVPResponse = strstr($NVPResponse,"RESULT");
		$NVPResponseArray = $NVPResponse; //$this->NVPToArray($NVPResponse);

		$NVPResponseArray['RAWREQUEST'] = $NVPRequestmask;
		$NVPResponseArray['RAWRESPONSE'] = $NVPResponse;
		
		return $NVPResponseArray;
	}
}