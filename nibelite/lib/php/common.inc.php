<?php
/* ===========================================================================
 *
 * SVN: $Id: common.inc.php 159 2008-02-19 11:20:55Z misha $
 *
 * MODULE
 * 	
 * 	common.inc.php
 *
 * DESCRIPTION
 *
 * 	This module contains some common constants for messages (types, statuses),
 * 	transactions and content.
 *
 * AUTHORS
 *
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 *	1. app.inc.php (messaging)
 *	2. transactions.inc.php (transactions)
 *
 * TODO
 *
 *	1. Add some new statuses and probably other constants.
 * 
 * ===========================================================================
 */

// Message types
define("MSG_TEXT", 0);           // Plain text message.
define("MSG_CONTENT_LINK", 1);   // Link to (request code of) binary content.
define("MSG_CONTENT_BINARY", 2); // Binary message in proper format.
define("MSG_HTTP_LINK", 3);      // Link for WAP push message.

// Message status values.
define("STATUS_NEW", 0);         // New message for processing by router.
define("STATUS_ROUTING", 1);     // Message is under processing by router.
define("STATUS_ROUTED", 2);      // Message is processed by router to proper application.
define("STATUS_NOTARGET", 3);    // Message is processed by router but no route found.
define("STATUS_PROCESSING", 4);  // Message is processing by application.
define("STATUS_PROCESSED", 5);   // Message is successfully processed by application.
define("STATUS_DELIVERED", 6);   // Message is processed and successfully delivered.
define("STATUS_UNDELIVERED", 7); // Message is processed but cannot be delivered.
define("STATUS_DELIVERING", 8);  // Message is delivering to subscriber. Waiting for report.

// Content charging status (only for MT charging)
define("CONTENT_FREE", 0);       // Content is free of charge.
define("CONTENT_PREMIUM", 1);    // Content is premium-rate and should be billed.

// Fake transaction identifier (should never be in transactions storage)
define("FAKE_TRANSACTION", -1);  // Fake transactions to be invisible in billing.

// Transaction statuses
define('TS_REQUEST',0);          // request initiated (beging only)
define('TS_PROCESS',1);          // processing NOW(!) - don't do anything with it
define('TS_DELIVERY',2);         // delivery, waiting for report (DLR)
define('TS_OK',10);
define('TS_ERROR_REQUEST',11);
define('TS_ERROR',12);
define('TS_ERROR_CONTENT',13);
define('TS_ERROR_DELIVERY',14);
define('TS_ERROR_FROM',15);
define('TS_ERROR_TO',16);

$TS_STR = array(
	TS_REQUEST => 'New query',
	TS_PROCESS => 'Processing',
	TS_DELIVERY => 'Delivery in progress',
	TS_OK => 'OK',
	TS_ERROR_REQUEST => 'Request error',
	TS_ERROR => 'Error',
	TS_ERROR_CONTENT => 'Content not found',
	TS_ERROR_DELIVERY => 'Delivery error',
	TS_ERROR_FROM => 'Invalid sender',
	TS_ERROR_TO => 'Invalid destination',
	);

?>
