<?php
/*
  +--------------------------------------------------------------------+
  | CiviCRM version 3.4                                                |
  +--------------------------------------------------------------------+
  | This file is a part of CiviCRM.                                    |
  |                                                                    |
  | CiviCRM is free software; you can copy, modify, and distribute it  |
  | under the terms of the GNU Affero General Public License           |
  | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
  |                                                                    |
  | CiviCRM is distributed in the hope that it will be useful, but     |
  | WITHOUT ANY WARRANTY; without even the implied warranty of         |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
  | See the GNU Affero General Public License for more details.        |
  |                                                                    |
  | You should have received a copy of the GNU Affero General Public   |
  | License and the CiviCRM Licensing Exception along                  |
  | with this program; if not, contact CiviCRM LLC                     |
  | at info[AT]civicrm[DOT]org. If you have questions about the        |
  | GNU Affero General Public License or the licensing of CiviCRM,     |
  | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
  +--------------------------------------------------------------------+
*/

/*
 * Payment processor plugin for Payflow Link
 */
 
require_once 'CRM/Core/Payment.php';


class CRM_Core_Payment_PayflowLink extends CRM_Core_Payment {
    const
        CHARSET = 'iso-8859-1';
    static protected $_mode = null;

    static protected $_params = array();
       /** 
    * We only need one instance of this object. So we use the singleton 
    * pattern and cache the instance in this variable 
    * 
    * @var object 
    * @static 
    */ 
   static private $_singleton = null; 
   
    /**
     * Constructor
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    function __construct( $mode, &$paymentProcessor ) {

        $this->_mode             = $mode;
        $this->_paymentProcessor = $paymentProcessor;
        $this->_processorName    = ts('PayflowLink');
    }
   /** 
     * singleton function used to manage this object 
     * 
     * @param string $mode the mode of operation: live or test
     *
     * @return object 
     * @static 
     * 
     */ 
    static function &singleton( $mode, &$paymentProcessor ) {
        $processorName = $paymentProcessor['name'];
        if (self::$_singleton[$processorName] === null ) {
            self::$_singleton[$processorName] = new CRM_Core_Payment_PayflowLink( $mode, $paymentProcessor );
        }
        return self::$_singleton[$processorName];
    }
    
    function checkConfig( ) {
        $config = CRM_Core_Config::singleton( );

        $error = array( );

        if ( empty( $this->_paymentProcessor['user_name'] ) ) {
            $error[] = ts( 'UserID is not set in the Administer CiviCRM &raquo; Payment Processor.' );
        }
        
        if ( empty( $this->_paymentProcessor['password'] ) ) {
            $error[] = ts( 'password is not set in the Administer CiviCRM &raquo; Payment Processor.' );
        }
        
        if ( ! empty( $error ) ) {
            return implode( '<p>', $error );
        } else {
            return null;
        }
    }

    function setExpressCheckOut( &$params ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) ); 
    }
    function getExpressCheckoutDetails( $token ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) ); 
    }
    function doExpressCheckout( &$params ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) ); 
    }

    function doDirectPayment( &$params ) {
        CRM_Core_Error::fatal( ts( 'This function is not implemented' ) );
    }

    /**  
     * Main transaction function
     *  
     * @param array $params  name value pair of contribution data
     *  
     * @return void  
     * @access public 
     *  
     */   
    function doTransferCheckout( &$params, $component ) 
    {

        //doesn't look like these can actually be passed in....      
        $config    = CRM_Core_Config::singleton( );
        $cancelURL = $this->getCancelURL($component);     
        $url = $config->userFrameworkResourceURL."extern/payFlowLinkIPN.php";
        $component = strtolower( $component );
        $paymentProcessorParams = $this->mapParamstoPaymentProcessorFields( $params,$component );
        
        // Allow further manipulation of params via custom hooks
        CRM_Utils_Hook::alterPaymentProcessorParams( $this, $params, $paymentProcessorParams );
                
        $processorURL = $this->_paymentProcessor['url_site'] ."?". $this->buildPaymentProcessorString($paymentProcessorParams);
        CRM_Utils_System::redirect( $processorURL) ;
        		     
    }	
    /*
     * Get URL which the browser should be returned to if they cancel or are unsuccessful
     * @component string $omponent function is called from
     * @return string $cancelURL Fully qualified return URL
     * @todo Ideally this would be in the parent payment class
     */	
    function getCancelURL($component){
        $component = strtolower( $component );
        if ( $component != 'contribute' && $component != 'event' ) {
            CRM_Core_Error::fatal( ts( 'Component is invalid' ) );
        }
        if ( $component == 'event') {
            $cancelURL = CRM_Utils_System::url( 'civicrm/event/register',
                                                "_qf_Confirm_display=true&qfKey={$params['qfKey']}", 
                                                false, null, false );
        } else if ( $component == 'contribute' ) {
            $cancelURL = CRM_Utils_System::url( 'civicrm/contribute/transact',
                                                "_qf_Confirm_display=true&qfKey={$params['qfKey']}", 
                                                false, null, false );
        }	
        return $cancelURL;
    }
    
    /*
     * map the name / value set required by the payment processor
     * @param array $params
     * @return array $processorParams array reflecting parameters required for payment processor
     */
     function mapParamstoPaymentProcessorFields($params,$component){
       //AFAIK partner is always paypal but have configured it here so that it can be set in signature field if required
       $partner = (empty($this->_paymentProcessor['signature'] )) ? 'PAYPAL' : $this->_paymentProcessor['signature'];
       
       $processorParams = array(
       													'TYPE'        => 'S',
                                'ADDRESS'     =>  $this->URLencodetoMaximumLength($params['street_address'],60),
                                'CITY'	      =>  $this->URLencodetoMaximumLength($params['city'],32),
      													'LOGIN'       =>  $this->_paymentProcessor['user_name'],		
                                'PARTNER'	    =>  $partner,
                                'AMOUNT'      =>  $params['amount'],
                                'ZIP'			    =>  $this->URLencodetoMaximumLength($params['postal_code'],10),
                                'COUNTRY'	    =>  $params['country'],
                                'COMMENT1'    =>  'civicrm contact ID ' . $params['contactID'],//ref not returned to Civi but visible in paypal
                                'COMMENT2'    =>  'contribution id ' . $params['contributionID'],//ref not returned to Civi but visible in paypal
                                'CUSTID'	    =>  $params['contributionIDinvoiceID'],//11 max
                                'DESCRIPTION' =>  $this->URLencodetoMaximumLength($params['description'],255),//255
                                'EMAIL'       =>  $params['email'],
                                'INVOICE'			=>  $params['contributionID'],//9 max
                                'NAME'				=>  $this->URLencodetoMaximumLength($params['display_name'],60),
 //                              'PONUM'				=> //purchase order
 //                              'TAX'//
                                'STATE'			  => $params['state_province'],
                                'USER1'			  => $params['contactID'],//USER fields are returned to Civi silent POST. Add them all in here for debug help.
                                'USER2'				=> $params['invoiceID'], 
                                'USER3'				=> CRM_Utils_Array::value('participantID',$params),      
                                'USER4'				=> CRM_Utils_Array::value('eventID',$params), 
                                'USER5'				=> CRM_Utils_Array::value( 'membershipID', $params ),    
                                'USER6'				=> CRM_Utils_Array::value( 'pledgeID', $params ), 
	              						    'USER7'				=> $component . ", " .  $params['qfKey'],
	                							'USER8'				=> CRM_Utils_Array::value('contributionPageID',$params), 
                								'USER9'				=> CRM_Utils_Array::value('related_contact',$params),
                								'USER10'			=> CRM_Utils_Array::value( 'onbehalf_dupe_alert', $params ),								
       );
      return $processorParams;
    }
    
    /*
     * Encodes string for a URL & ensures that it does not exceed the maximum length of the relevant field
     * The cut needs to be made after spaces etc are transformed (as the string becomes longer after html encoding
     * but must not include a partial transformed character 
     * e.g. space is encoded to %20 - these 3 characters must be included or excluded but not partially included
     * 
     * @params string $value value to be encoded
     * @params string @fieldLength maximum length of encoded field
     * @return string $encodedString value html encoded 
     */
    function URLencodetoMaximumLength($value,$fieldLength = 255)
    {
        $encodedString   = substr(rawurlencode($value),0,$fieldLength);
        $lastPercent =  strrpos($encodedString ,'%');
        if ($lastPercent >  $fieldLength- 3) {
            $encodedString = substr($encodedString ,0,$lastPercent);
        }
        return $encodedString ;
    }
    
    /*
     * Build string of name value pairs for submission to payment processor
     * 
     * @params array $paymentProcessorParams
     * @return string $paymentProcessorString
     */
    
    function buildPaymentProcessorString($paymentProcessorParams){
      $validParams = array();
      foreach ($paymentProcessorParams as $key => $value){
        if (!empty($value)){
          $validParams[] = $key ."=".$value;   
        }
      }
      $paymentProcessorString = implode('&',$validParams);

      return $paymentProcessorString;
      
    }
}
