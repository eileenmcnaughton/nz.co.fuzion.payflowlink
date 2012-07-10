<?php 

/*
 * written by Eileen McNaughton
 * Licensed to CiviCRM under the Academic Free License version 3.0.
 *
 */
 
session_start( );

require_once '../civicrm.config.php';
require_once 'CRM/Core/Config.php';

$config = CRM_Core_Config::singleton();
$config = CRM_Core_Config::singleton();

require_once 'CRM/Core/Payment/PayflowLinkIPN.php';
$payFlowLinkIPN = new CRM_Core_Payment_PayflowLinkIPN( );
$payFlowLinkIPN ->main( );
//if for any reason we come back here
CRM_Core_Error::debug_log_message( "It should not be possible to reach this line" );
