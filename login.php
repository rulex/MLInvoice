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

// buffered, so we can redirect later if necessary
ini_set('implicit_flush', 'Off');
ob_start();

require_once 'sqlfuncs.php';
require_once 'miscfuncs.php';
require_once 'config.php';
require_once 'htmlfuncs.php';
require_once 'sessionfuncs.php';

session_start();

$strLogin = getPost('flogin', FALSE);
$strPasswd = getPost('fpasswd', FALSE);
$strLogon = getPost('logon', '');
$backlink = getRequest('backlink', '0');

if (defined('_UI_LANGUAGE_SELECTION_')) {
  $languages = array();
  foreach (explode('|', _UI_LANGUAGE_SELECTION_) as $lang) {
    $lang = explode('=', $lang, 2);
    $languages[$lang[0]] = $lang[1];
  }
  $language = getRequest('lang', '');
  if ($language && isset($languages[$language])) {
    $_SESSION['sesLANG'] = $language;
  }
}
if (!isset($_SESSION['sesLANG'])) {
  $_SESSION['sesLANG'] = defined('_UI_LANGUAGE_') ? _UI_LANGUAGE_ : 'fi-FI';
}

require_once 'localize.php';

switch(verifyDatabase())
{
  case 'OK': break;
  case 'UPGRADED':
    $upgradeMessage = $GLOBALS['locDatabaseUpgraded'];
    break;
  case 'FAILED':
    $upgradeFailed = true;
    $upgradeMessage = $GLOBALS['locDatabaseUpgradeFailed'];
    break;
}

$strMessage = $GLOBALS['locWelcomeMessage'];

if ($strLogon)
{
    if ($strLogin && $strPasswd)
    {
        switch (sesCreateSession($strLogin, $strPasswd))
        {
        case 'OK':
            if ($backlink == '1' && isset($_SESSION['BACKLINK'])) {
              header('Location: ' . $_SESSION['BACKLINK']);
            } else {
              header('Location: ' . getSelfPath() . '/index.php');
            }
            exit;
        case 'FAIL':
            $strMessage = $GLOBALS['locInvalidCredentials'];
            break;
        case 'TIMEOUT':
            $strMessage = $GLOBALS['locLoginTimeout'];
            break;
        }
    }
    else
    {
        $strMessage = $GLOBALS['locMissingFields'];
    }
}

$key = sesCreateKey();

echo htmlPageStart(_PAGE_TITLE_, array('jquery/js/jquery.md5.js'));
?>

<body onload="document.getElementById('flogin').focus();">
<div class="pagewrapper ui-widget ui-widget-content">
<div class="form" style="padding: 30px;">
<div class="container">

<?php
if (isset($upgradeMessage)) {
?>
<div class="message ui-widget <?php echo isset($upgradeFailed) ? 'ui-state-error' : 'ui-state-highlight'?>">
  <?php echo $upgradeMessage?>
</div>
<br/>
<?php
}
?>

<?php
if (isset($languages)) {
  foreach ($languages as $code => $name) {
    if ($code == $_SESSION['sesLANG']) {
      continue;
    }
?>
<a href="login.php?lang=<?php echo $code?>"><?php echo htmlspecialchars($name)?></a><br/>
<?php
  }
  echo '<br/>';
}
?>
<div style="max-width: 330px; padding: 15px; margin: 0 auto;">
<h1 class="form-signin-heading"><?php echo $GLOBALS['locWelcome']?></h1>
<small class="muted" id="loginmsg"><?php echo $strMessage?></small>

<script type="text/javascript">
function createHash()
{
  var pass_md5 = $.md5(document.getElementById('passwd').value);
  var key = document.getElementById('key').value;
  document.getElementById('fpasswd').value = $.md5(key + pass_md5);
  document.getElementById('passwd').value = '';
  document.getElementById('key').value = '';
  var loginmsg = document.getElementById('loginmsg');
  loginmsg.childNodes.item(0).nodeValue = '<?php echo $GLOBALS['locLoggingIn']?>';
}
</script>

<form action="login.php" method="post" name="login_form" onsubmit="createHash();">
  <input type="hidden" name="backlink" value="<?php echo $backlink?>">
  <input type="hidden" name="fpasswd" id="fpasswd" value="">
  <input type="hidden" name="key" id="key" value="<?php echo $key?>">
	<input class="form-control input-lg" name="flogin" id="flogin" placeholder="<?php echo $GLOBALS['locUserID']?>" type="text" value="">
	<input class="form-control input-lg" name="passwd" id="passwd" placeholder="<?php echo $GLOBALS['locPassword']?>" type="password" value="">
  <input class="btn btn-lg btn-primary btn-block" type="submit" name="logon" value="<?php echo $GLOBALS['locLogin']?>">
</form>

</div>

</div>
</div>
</div>
</body>
</html>
