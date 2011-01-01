<?php
/*******************************************************************************
VLLasku: web-based invoicing application.
Copyright (C) 2010-2011 Ere Maijala

This program is free software. See attached LICENSE.

*******************************************************************************/

/*******************************************************************************
VLLasku: web-pohjainen laskutusohjelma.
Copyright (C) 2010-2011 Ere Maijala

T�m� ohjelma on vapaa. Lue oheinen LICENSE.

*******************************************************************************/

// Tietokantapalvelimen osoite
define('_DB_SERVER_', 'localhost');

// Tunnus tietokantapalvelimelle
define('_DB_USERNAME_', 'vllasku');

// Salasana tietokantapalvelimelle
define('_DB_PASSWORD_', 'vllasku');

// Tietokannan nimi
define('_DB_NAME_', 'vllasku');

// Tietokantataulujen prefix
define ('_DB_PREFIX_', 'vllasku');

// Merkist�: UTF-8 tai ISO-8859-15
define ('_CHARSET_', 'UTF-8');

// Sivujen otsikko
define ("_PAGE_TITLE_", "VLLasku");

// http vai https - vaihda vain jos automaattinen valinta alla ei toimi
define ('_PROTOCOL_', isset($_SERVER['HTTPS']) ? 'https://' : 'http://');
//define ("_PROTOCOL_", "http://");

// HUOM! Asetukset l�ytyv�t nyt k�ytt�liittym�st� kohdasta Asetukset - Yleiset asetukset

mb_internal_encoding(_CHARSET_);

function getSetting($name)
{
  require_once 'localize.php';
  require 'settings_def.php';
  
  if (isset($arrSettings[$name]) && isset($arrSettings[$name]['session']) && $arrSettings[$name]['session'])
  {
    if (isset($_SESSION[$name]))
      return $_SESSION[$name];
  }
  else
  {
    $res = mysql_param_query('SELECT value from {prefix}settings WHERE name=?', array($name));
    if ($row = mysql_fetch_assoc($res))
      return $row['value'];
  }
  return isset($arrSettings[$name]) && isset($arrSettings[$name]['default']) ? cond_utf8_encode($arrSettings[$name]['default']) : '';
}
