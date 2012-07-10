<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
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

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

require_once 'CRM/Core/Payment/BaseIPN.php';

class CRM_Core_Payment_PayflowLinkIPN extends CRM_Core_Payment_BaseIPN {

    static $_paymentProcessor = null;

    function __construct( ) {
        parent::__construct( );
    }

    static function retrieve( $name, $type, $location = 'POST', $abort = true ) 
    {
        static $store = null;
        $value = CRM_Utils_Request::retrieve( $name, $type, $store,
                                              false, null, $location );
        if ( $abort && $value === null ) {
            CRM_Core_Error::debug_log_message( "Could not find an entry for $name in $location" );
            echo "Failure: Missing Parameter<p>";
            exit( );
        }
        return $value;
    }

 
    function single( &$input, &$ids, &$objects,
                     $recur = false,
                     $first = false ) 
    {
        $contribution =& $objects['contribution'];

        // make sure the invoice is valid and matches what we have in the contribution record
        if ( ( ! $recur ) || ( $recur && $first ) ) {
            if ( $contribution->invoice_id != $input['invoice'] ) {
                CRM_Core_Error::debug_log_message( "Invoice values dont match between database and IPN request" );
                echo "Failure: Invoice values dont match between database and IPN request<p>";
                return false;
            }
        } else {
            $contribution->invoice_id = md5( uniqid( rand( ), true ) );
        }

        if ( ! $recur ) {
            if ( $contribution->total_amount != $input['amount'] ) {
                CRM_Core_Error::debug_log_message( "Amount values dont match between database and IPN request" );
                echo "Failure: Amount values dont match between database and IPN request<p>";
                return false;
            }
        } else {
            $contribution->total_amount = $input['amount'];
        }

        require_once 'CRM/Core/Transaction.php';
        $transaction = new CRM_Core_Transaction( );

  
        $participant =& $objects['participant'];
        $membership  =& $objects['membership' ];

        $status = $input['paymentStatus'];
        if ( $status == 'Denied' || $status == 'Failed' || $status == 'Voided' ) {
            return $this->failed( $objects, $transaction );
        } else if ( $status == 'Pending' ) {
            return $this->pending( $objects, $transaction );
        } else if ( $status == 'Refunded' || $status == 'Reversed' ) {
            return $this->cancelled( $objects, $transaction );
        } else if ( $status != 'Completed' ) {
            return $this->unhandled( $objects, $transaction );
        }

        // check if contribution is already completed, if so we ignore this ipn
        if ( $contribution->contribution_status_id == 1 ) {
            $transaction->commit( );
            CRM_Core_Error::debug_log_message( "contribution already been handled (silent post" );
            return "success";
        }

         $this->completeTransaction( $input, $ids, $objects, $transaction, $recur );
         return "success";
	
    }

    function main( ) 
    {
        CRM_Core_Error::debug_log_message( 'GET' .print_r($_GET,true ));
        CRM_Core_Error::debug_log_message( 'POST'.print_r($_POST,true ));

        require_once 'CRM/Utils/Request.php';
        
        $objects = $ids = $input = array( );
        $user7 = explode(", ", strtolower(self::retrieve('USER7', 'String', 'POST', true)));
        $input['component'] = $component = $user7[0];
        $qfkey = $user7[1];
        // get the contribution and contact ids from the GET params
        $ids['contact']           = self::retrieve( 'USER1'         , 'Integer', 'POST' , true  );
        $ids['contribution']      = self::retrieve( 'INVOICE'    , 'Integer', 'POST' , true  );

        $this->getInput( $input, $ids );

        if ( $component == 'event' ) {
			    $ids['participant'] = self::retrieve( 'USER3', 'Integer', 'POST', true );
          $ids['event']       = self::retrieve( 'USER4'      , 'Integer', 'POST', true );
		    } else {
            // get the optional ids
            $ids['membership']          = self::retrieve( 'USER5'       , 'Integer', 'POST', false );
			$ids['contributionPage']     = self::retrieve( 'USER8'       , 'Integer', 'POST', false );
			$ids['related_contact']     = self::retrieve( 'USER9'       , 'Integer', 'POST', false );
			$ids['onbehalf_dupe_alert']     = self::retrieve( 'USER10'       , 'Integer', 'POST', false );
   // $ids['contributionRecur']   = self::retrieve( 'contributionRecurID', 'Integer', 'POST', false );

 
        }

        if ( ! $this->validateData( $input, $ids, $objects ) ) {
            return false;
        }
        $redirectURL = $this->getURLtoRedirectBrowserOnSuccess($component, $qfkey);
        self::$_paymentProcessor =& $objects['paymentProcessor'];
        if ( $this->single( $input, $ids, $objects, false, false ) == 'success'){
             CRM_Utils_System::redirect( $redirectURL );	 
         }else{
           //TODO  I guess we need a failed URL here
         }

    }

    function getInput( &$input, &$ids ) {
        if ( ! $this->getBillingID( $ids ) ) {
            return false;
        }

     //   $input['txnType']       = self::retrieve( 'txn_type'          , 'String' , 'POST', false );
	 if (self::retrieve( 'RESULT'    , 'String' , 'POST', false  ) == '0'){
	 $input['paymentStatus'] = 'Completed';
	 }else{
	 	 $input['paymentStatus'] = 'Failed';
	}

        $input['invoice']       = self::retrieve( 'USER2'           , 'String' , 'POST', true  );
        $input['amount']        = self::retrieve( 'AMOUNT'          , 'Money'  , 'POST', false  );
        $input['reasonCode']    = self::retrieve( 'AUTHCODE'        , 'String' , 'POST', false );

        $billingID = $ids['billing'];
        $lookup = array( "display_name"                  => 'NAME',
                         "street_address-{$billingID}" => 'ADDRESS',
                         "city-{$billingID}"           => 'CITY',
                         "state-{$billingID}"          => 'STATE',
                         "postal_code-{$billingID}"    => 'ZIP',
                         "country-{$billingID}"        => 'COUNTRY' );
        foreach ( $lookup as $name => $paypalName ) {
            $value = self::retrieve( $paypalName, 'String', 'POST', false );
            $input[$name] = $value ? $value : null;
        }

        $input['trxn_id']    = self::retrieve( 'PNREF'       , 'String' , 'POST', false );
        
    }
    
    function getURLtoRedirectBrowserOnSuccess($component, $qfKey){

              if ( $component == "event" ) {
                $finalURL = CRM_Utils_System::url( 'civicrm/event/register',
                                                   "_qf_ThankYou_display=1&qfKey=$qfKey", 
                                                   false, null, false );
			} elseif ( $component == "contribute" ) {
                $finalURL = CRM_Utils_System::url( 'civicrm/contribute/transact',
                                                   "_qf_ThankYou_display=1&qfKey=$qfKey",
                                                   false, null, false );
			}
				return $finalURL;

    }
}


