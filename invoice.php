<?php
/*******************************************************************************
MLInvoice: web-based invoicing application.
Copyright (C) 2010-2012 Ere Maijala

Portions based on:
PkLasku : web-based invoicing software.
Copyright (C) 2004-2008 Samu Reinikainen

This program is free software. See attached LICENSE.

*******************************************************************************/

/*******************************************************************************
MLInvoice: web-pohjainen laskutusohjelma.
Copyright (C) 2010-2012 Ere Maijala

Perustuu osittain sovellukseen:
PkLasku : web-pohjainen laskutusohjelmisto.
Copyright (C) 2004-2008 Samu Reinikainen

Tämä ohjelma on vapaa. Lue oheinen LICENSE.

*******************************************************************************/

require_once "sessionfuncs.php";

sesVerifySession();

require_once "sqlfuncs.php";
require_once "localize.php";
require_once "pdf.php";
require_once "datefuncs.php";
require_once "miscfuncs.php";

$intInvoiceId = getRequest('id', FALSE);
$printTemplate = getRequest('template', 1);
$receiptDate = getRequest( 'date', FALSE );

$date = " ";
if( $receiptDate > 9999999 && $receiptDate < 100000000 ) {
	$receiptDate = $receiptDate;
	$date = " AND row_date=$receiptDate ";
}

if (!$intInvoiceId)
  return;

$res = mysql_param_query('SELECT filename, parameters, output_filename from {prefix}print_template WHERE id=?', array($printTemplate));
if (!$row = mysql_fetch_row($res))
  return;
$printTemplateFile = $row[0];
$printParameters = $row[1];
$printOutputFileName = $row[2];

$strQuery =
  "SELECT inv.*, ref.invoice_no as refunded_invoice_no, delivery_terms.name as delivery_terms, delivery_method.name as delivery_method " .
  "FROM {prefix}invoice inv " .
  "LEFT OUTER JOIN {prefix}invoice ref ON ref.id = inv.refunded_invoice_id ".
  "LEFT OUTER JOIN {prefix}delivery_terms as delivery_terms ON delivery_terms.id = inv.delivery_terms_id ".
  "LEFT OUTER JOIN {prefix}delivery_method as delivery_method ON delivery_method.id = inv.delivery_method_id ".
  "WHERE inv.id=?";
$intRes = mysql_param_query($strQuery, array($intInvoiceId));
$invoiceData = mysql_fetch_assoc($intRes);
if (!$invoiceData)
  die('Could not find invoice data');

$strQuery = 'SELECT * FROM {prefix}company WHERE id=?';
$intRes = mysql_param_query($strQuery, array($invoiceData['company_id']));
$recipientData = mysql_fetch_assoc($intRes);

$strQuery = 'SELECT * FROM {prefix}base WHERE id=?';
$intRes = mysql_param_query($strQuery, array($invoiceData['base_id']));
$senderData = mysql_fetch_assoc($intRes);
if (!$senderData)
  die('Could not find invoice sender data');
$senderData['vat_id'] = createVATID($senderData['company_id']);

$strQuery =
    "SELECT pr.product_name, pr.product_code, pr.price_decimals, ir.description, ir.pcs, ir.price, IFNULL(ir.discount, 0) as discount, ir.row_date, ir.vat, ir.vat_included, ir.reminder_row, rt.name type ".
    "FROM {prefix}invoice_row ir ".
    "LEFT OUTER JOIN {prefix}row_type rt ON rt.id = ir.type_id ".
    "LEFT OUTER JOIN {prefix}product pr ON ir.product_id = pr.id ".
    //"WHERE ir.invoice_id=? AND ir.deleted=0 ". $date ." ORDER BY ir.order_no, ir.row_date, pr.product_name DESC, ir.description DESC";
    "WHERE ir.invoice_id=? $date AND ir.deleted=0 ORDER BY ir.order_no, row_date, pr.product_name DESC, ir.description DESC";
$intRes = mysql_param_query($strQuery, array($intInvoiceId));
$invoiceRowData = array();
while ($row = mysql_fetch_assoc($intRes))
{
  $invoiceRowData[] = $row;
}

if (sesWriteAccess()) {
  mysql_param_query('UPDATE {prefix}invoice SET print_date=? where id=?', array(date('Ymd'), $intInvoiceId));
}

$printer = instantiateInvoicePrinter(trim($printTemplateFile));
$printer->init($intInvoiceId, $printParameters, $printOutputFileName, $senderData, $recipientData, $invoiceData, $invoiceRowData, $receiptDate);
$printer->printInvoice();
