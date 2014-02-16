<?php
/*******************************************************************************
MLInvoice: web-based invoicing application.
Copyright (C) 2010-2012 Ere Maijala

This program is free software. See attached LICENSE.

*******************************************************************************/

/*******************************************************************************
MLInvoice: web-pohjainen laskutusohjelma.
Copyright (C) 2010-2012 Ere Maijala

Tämä ohjelma on vapaa. Lue oheinen LICENSE.

*******************************************************************************/

ini_set('display_errors', 0);

require_once 'sqlfuncs.php';
require_once 'miscfuncs.php';
require_once 'sessionfuncs.php';
require_once 'form_funcs.php';
require_once 'localize.php';
require_once 'settings.php';

sesVerifySession(FALSE);

$strFunc = getRequest('func', '');

switch ($strFunc)
{
case 'get_company':
case 'get_company_contact':
case 'get_product':
case 'get_invoice':
case 'get_invoice_row':
case 'get_base':
case 'get_print_template':
case 'get_invoice_state':
case 'get_row_type':
case 'get_print_template':
case 'get_company':
case 'get_session_type':
case 'get_delivery_terms':
case 'get_delivery_method':
  printJSONRecord(substr($strFunc, 4));
  break;
case 'get_user':
  printJSONRecord('users');
  break;

case 'put_company':
case 'put_product':
case 'put_invoice':
case 'put_base':
case 'put_print_template':
case 'put_invoice_state':
case 'put_row_type':
case 'put_print_template':
case 'put_user':
case 'put_session_type':
case 'put_delivery_terms':
case 'put_delivery_method':
  saveJSONRecord(substr($strFunc, 4), '');
  break;

case 'session_type':
case 'user':
  if (!sesAdminAccess())
  {
    header('HTTP/1.1 403 Forbidden');
    exit;
  }
    saveJSONRecord(substr($strFunc, 4), '');
  break;

case 'get_companies':
  printJSONRecords('company', '', 'company_name');
  break;

case 'get_company_contacts':
  printJSONRecords('company_contact', 'company_id', 'contact_person');
  break;

case 'delete_company_contact':
  deleteRecord('company_contact');
  break;

case 'put_company_contact':
  saveJSONRecord('company_contact', 'company_id');
  break;

case 'get_products':
  printJSONRecords('product', '', 'product_name');
  break;

case 'get_row_types':
  printJSONRecords('row_type', '', 'order_no');
  break;

case 'get_invoice_rows':
  printJSONRecords('invoice_row', 'invoice_id', 'order_no');
  break;

case 'get_invoice_dates':
  printInvoiceDates('invoice_row', 'invoice_id', 'order_no');
  break;

case 'put_invoice_row':
  saveJSONRecord('invoice_row', 'invoice_id');
  break;

case 'delete_invoice_row':
  deleteRecord('invoice_row');
  break;

case 'add_reminder_fees':
  require 'add_reminder_fees.php';
  $invoiceId = getRequest('id', 0);
  $errors = addReminderFees($invoiceId);
  if ($errors)
  {
    $ret = array('status' => 'error', 'errors' => $errors);
  }
  else
  {
    $ret = array('status' => 'ok');
  }
  echo json_encode($ret);
  break;

case 'get_invoice_defaults':
  $baseId = getRequest('base_id', 0);
  $invoiceId = getRequest('id', 0);
  $intervalType = getRequest('interval_type', 0);
  $invNr = getRequest('invoice_no', 0);
  if (!$invNr) {
    if (getSetting('invoice_numbering_per_base') && $baseId)
      $res = mysql_param_query('SELECT max(cast(invoice_no as unsigned integer)) FROM {prefix}invoice WHERE deleted=0 AND id!=? AND base_id=?', array($invoiceId, $baseId));
    else
      $res = mysql_param_query('SELECT max(cast(invoice_no as unsigned integer)) FROM {prefix}invoice WHERE deleted=0 AND id!=?', array($invoiceId));
    $invNr = mysql_fetch_value($res) + 1;
  }
  if ($invNr < 100)
    $invNr = 100; // min ref number length is 3 + check digit, make sure invoice number matches that
  $refNr = $invNr . miscCalcCheckNo($invNr);
  $strDate = date($GLOBALS['locDateFormat']);
  $strDueDate = date($GLOBALS['locDateFormat'], mktime(0, 0, 0, date("m"), date("d")+getSetting('invoice_payment_days'), date("Y")));
  switch ($intervalType) {
    case 2:
      $nextIntervalDate = date($GLOBALS['locDateFormat'], mktime(0, 0, 0, date("m") + 1, date("d"), date("Y")));
      break;
    case 3:
      $nextIntervalDate = date($GLOBALS['locDateFormat'], mktime(0, 0, 0, date("m"), date("d"), date("Y") + 1));
      break;
    default:
      $nextIntervalDate = '';
  }
  $arrData = array(
    'invoice_no' => $invNr,
    'ref_no' => $refNr,
    'date' => $strDate,
    'due_date' => $strDueDate,
    'next_interval_date' => $nextIntervalDate
  );
  header('Content-Type: application/json');
  echo json_encode($arrData);
  break;

case 'get_table_columns':
  if (!sesAdminAccess())
  {
    header('HTTP/1.1 403 Forbidden');
    exit;
  }
  $table = getRequest('table', '');
  if (!$table)
  {
    header('HTTP/1.1 400 Bad Request');
    exit;
  }
  // account_statement is a pseudo table for account statement "import"
  if ($table == 'account_statement') {
    header('Content-Type: application/json');
    echo "{\"columns\":";
    echo json_encode(array(
      array('id' => 'date', 'name' => $GLOBALS['locImportStatementPaymentDate']),
      array('id' => 'amount', 'name' => $GLOBALS['locImportStatementAmount']),
      array('id' => 'refnr', 'name' => $GLOBALS['locImportStatementRefNr'])
    ));
    echo "\n}";
    exit;
  }

  if (!table_valid($table))
  {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid table name');
  }

  header('Content-Type: application/json');
  echo "{\"columns\":[";
  $res = mysql_query_check("select * from {prefix}$table where 1=2");
  $field_count = mysql_num_fields($res);
  for ($i = 0; $i < $field_count; $i++)
  {
    $field_def = mysql_fetch_field($res, $i);
    if ($i == 0)
    {
      echo "\n";
    }
    else
      echo ",\n";
    echo json_encode(array('name' => $field_def->name));
  }
  echo "\n]}";
  break;

case 'get_import_preview':
  if (!sesAdminAccess())
  {
    header('HTTP/1.1 403 Forbidden');
    exit;
  }
  $table = getRequest('table', '');
  if ($table == 'account_statement') {
    require 'import_statement.php';
    $import = new ImportStatement();
  } else {
    require 'import.php';
    $import = new ImportFile();
  }
  $import->create_import_preview();
  break;

case 'get_list':
  require 'list.php';

  $listFunc = getRequest('listfunc', '');

  $strList = getRequest('table', '');
  if (!$strList) {
    header('HTTP/1.1 400 Bad Request');
    die('Table must be defined');
  }

  include 'list_switch.php';

  if (!$strTable) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid table name');
  }

  $startRow = intval(getRequest('iDisplayStart', -1));
  $rowCount = intval(getRequest('iDisplayLength', -1));
  $sort = array();
  if (getRequest('iSortCol_0', 0)) {
    for ($i = 0; $i < intval(getRequest('iSortingCols', 0)); $i++) {
      $sortColumn = intval(getRequest("iSortCol_$i", 0));
			if (getRequest("bSortable_$i", 'false') == 'true') {
			  $sortDir = getRequest("sSortDir_$i", 'asc');
			  $sort[] = array($sortColumn => $sortDir === 'desc' ? 'desc' : 'asc');
			}
		}
  }
  $filter = getRequest('sSearch', '');
  $where = getRequest('where', '');

  header('Content-Type: application/json');
  echo createJSONList($listFunc, $strList, $startRow, $rowCount, $sort, $filter, $where, intval(getRequest('sEcho', 1)));
  break;

case 'get_invoice_total_sum':
  $where = getRequest('where', '');

  header('Content-Type: application/json');
  echo getInvoiceListTotal($where);
  break;

case 'get_selectlist':
  require 'list.php';

  $table = getRequest('table', '');
  if (!$table)
  {
    header('HTTP/1.1 400 Bad Request (table)');
    exit;
  }

  if (!table_valid($table))
  {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid table name');
  }

  $pageLen = intval(getRequest('pagelen', 10));
  $page = intval(getRequest('page', 1)) - 1;
  $filter = getRequest('q', '');
  $sort = getRequest('sort', '');

  header('Content-Type: application/json');
  echo createJSONSelectList($table, $page * $pageLen, $pageLen, $filter, $sort);
  break;

case 'update_invoice_row_dates':
  if (!sesWriteAccess())
  {
    header('HTTP/1.1 403 Forbidden');
    exit;
  }
  $invoiceId = getRequest('id', 0);
  $date = getRequest('date', '');
  if (!$date) {
    header('HTTP/1.1 400 Bad Request');
    die('date must be given');
  }
  header('Content-Type: application/json');
  echo updateInvoiceRowDates($invoiceId, $date);
  break;

case 'noop':
  // Session keep-alive
  break;

default:
  header('HTTP/1.1 404 Not Found');
}

function printJSONRecord($table, $id = FALSE, $warnings = null)
{
  if ($id === FALSE)
    $id = getRequest('id', '');
  if ($id)
  {
    if (substr($table, 0, 8) != '{prefix}')
      $table = "{prefix}$table";
    $select = 'SELECT t.*';
    $from = "FROM $table t";
    $where = 'WHERE t.id=?';

    if ($table == '{prefix}invoice_row') {
      $select .= ", IFNULL(p.product_name, '') as product_id_text";
      $from .= ' LEFT OUTER JOIN {prefix}product p on (p.id = t.product_id)';
    }

    $query = "$select $from $where";
    $res = mysql_param_query($query, array($id));
    $row = mysql_fetch_assoc($res);
    if ($table == 'users')
      unset($row['password']);
    header('Content-Type: application/json');
    $row['warnings'] = $warnings;
    echo json_encode($row);
  }
}

function printInvoiceDates( $table, $parentIdCol, $sort ) {
  $query = "SELECT row_date FROM {prefix}$table";
  $where = '';
  $params = array();
  $id = getRequest('parent_id', '');
  if( $id && $parentIdCol )
  {
    $where .= " WHERE $parentIdCol=?";
    $params[] = $id;
  }
  if( !getSetting('show_deleted_records' ) )
  {
    if( $where )
      $where .= " AND deleted=0";
    else
      $where = " WHERE deleted=0";
  }

  $query .= $where;
	$query .= " group by row_date ";
  if( $sort )
    $query .= " order by $sort";

  $res = mysql_param_query( $query, $params );
  header( 'Content-Type: application/json' );
  echo "{\"records\":[";
  $first = true;
  while ($row = mysql_fetch_assoc($res))
  {
    if ($first)
    {
      echo "\n";
      $first = false;
    }
    else
      echo ",\n";
    if ($table == 'users')
      unset($row['password']);
    echo json_encode($row);
  }
  echo "\n]}";
}

function printJSONRecords($table, $parentIdCol, $sort)
{
  $select = "SELECT t.*";
  $from = "FROM {prefix}$table t";

  if ($table == 'invoice_row') {
    // Include product name
    $select .= ", IFNULL(p.product_name, '') as product_id_text";
    $from .= ' LEFT OUTER JOIN {prefix}product p on (p.id = t.product_id)';
  }

  $where = '';
  $params = array();
  $id = getRequest('parent_id', '');
  if ($id && $parentIdCol)
  {
    $where .= " WHERE t.$parentIdCol=?";
    $params[] = $id;
  }
  if (!getSetting('show_deleted_records'))
  {
    if ($where) {
      $where .= ' AND t.deleted=0';
    } else {
      $where = ' WHERE t.deleted=0';
    }
  }

  $query = "$select $from $where";
  if ($sort) {
    $query .= " order by $sort";
  }
  $res = mysql_param_query($query, $params);
  header('Content-Type: application/json');
  echo "{\"records\":[";
  $first = true;
  while ($row = mysql_fetch_assoc($res))
  {
    if ($first)
    {
      echo "\n";
      $first = false;
    }
    else
      echo ",\n";
    if ($table == 'users')
      unset($row['password']);
    echo json_encode($row);
  }
  echo "\n]}";
}

function saveJSONRecord($table, $parentKeyName)
{
  if (!sesWriteAccess())
  {
    header('HTTP/1.1 403 Forbidden');
    exit;
  }

	$data = json_decode(file_get_contents('php://input'), true);
  if (!$data)
  {
    header('HTTP/1.1 400 Bad Request');
    return;
  }
  $strForm = $table;
  $strFunc = '';
  $strList = '';
  require 'form_switch.php';
  $id = isset($data['id']) ? $data['id'] : false;
  $new = $id ? false : true;
  unset($data['id']);
  $warnings = '';
  $res = saveFormData($strTable, $id, $astrFormElements, $data, $warnings, $parentKeyName, $parentKeyName ? $data[$parentKeyName] : FALSE);
  if ($res !== true)
  {
    if ($warnings) {
      header('HTTP/1.1 409 Conflict');
    }
    header('Content-Type: application/json');
    echo json_encode(
      array(
        'missing_fields' => $res,
        'warnings' => $warnings
      )
    );
    return;
  }
  if ($new)
    header('HTTP/1.1 201 Created');
  printJSONRecord($strTable, $id, $warnings);
}

function deleteRecord($table)
{
  if (!sesWriteAccess())
  {
    header('HTTP/1.1 403 Forbidden');
    exit;
  }

  $id = getRequest('id', '');
  if ($id)
  {
    $query = "UPDATE {prefix}$table SET deleted=1 WHERE id=?";
    mysql_param_query($query, array($id));
    header('Content-Type: application/json');
    echo json_encode(array('status' => 'ok'));
  }
}

function getInvoiceListTotal($where)
{
  $strFunc = 'invoices';
  $strList = 'invoice';

  require 'list_switch.php';

  $strWhereClause = '';
  $joinOp = 'WHERE';
  $arrQueryParams = array();
  if ($where) {
    // Validate and build query parameters
    $boolean = '';
    while (extractSearchTerm($where, $field, $operator, $term, $nextBool))
    {
      //echo ("bool: $boolean, field: $field, op: $operator, term: $term \n");
      $strWhereClause .= "$boolean$field $operator ?";
      $arrQueryParams[] = str_replace("%-", "%", $term);
      if (!$nextBool)
        break;
      $boolean = " $nextBool";
    }
    if ($strWhereClause) {
      $strWhereClause = "WHERE ($strWhereClause)";
      $joinOp = ' AND';
    }
  }
  if (!getSetting('show_deleted_records')) {
    $strWhereClause .= "$joinOp $strDeletedField=0";
    $joinOp = ' AND';
  }


  $sql = "SELECT sum(it.row_total) as total_sum from $strTable $strJoin $strWhereClause";

  $sum = 0;
  $res = mysql_param_query($sql, $arrQueryParams);
  if ($row = mysql_fetch_assoc($res)) {
    $sum = $row['total_sum'];
  }
  $result = array(
    'sum' => $sum,
    'sum_str' => sprintf($GLOBALS['locInvoicesTotal'], miscRound2Decim($sum))
  );

  echo json_encode($result);
}

function updateInvoiceRowDates($invoiceId, $date)
{
  $date = dateConvDate2DBDate($date);
  if ($date === false) {
    return json_encode(array('status' => 'error', 'errors' => $GLOBALS['locErrInvalidValue']));
  }
  mysql_param_query('UPDATE {prefix}invoice_row SET row_date=? WHERE invoice_id=? AND deleted=0', array($date, $invoiceId));
  return json_encode(array('status' => 'ok'));
}
