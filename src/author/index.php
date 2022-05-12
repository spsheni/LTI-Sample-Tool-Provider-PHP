<?php
/**
 * rating - Rating: an example LTI tool provider
 *
 * @author  Shyama Praveena S <spsheni@gmail.com>
 * @copyright  IMS Global Learning Consortium Inc
 * @date  2022
 * @version 2.0.0
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3.0
 */

/*
 * This page manages the content item records for selected consumers.
 *
 * *** IMPORTANT ***
 * Access to this page should be restricted to prevent unauthorised access to the configuration of content
 * item records (for example, using an entry in an Apache .htaccess file); access to all other pages is
 * authorised by LTI.
 * ***           ***
*/

  use IMSGlobal\LTI\ToolProvider;
  use IMSGlobal\LTI\ToolProvider\DataConnector;

  require_once('../lib.php');

// Initialise session and database
  $db = NULL;
  $ok = init($db, FALSE);
// Initialise parameters
  $id = NULL;
  $launchUrl = getAppUrl() . 'connect.php';
  $returnUrl = getAppUrl() . 'author/index.php';
  $citem = NULL;

  if ($ok) {
// Create LTI Tool Provider instance
    $data_connector = DataConnector\DataConnector::getDataConnector(DB_TABLENAME_PREFIX, $db);
    $tool = new ToolProvider\ToolProvider($data_connector);
// Check for consumer id and action parameters
    $action = '';
    if (isset($_REQUEST['id'])) {
      $id = intval($_REQUEST['id']);
    }
    if (isset($_REQUEST['do'])) {
      $action = $_REQUEST['do'];
    }
    if (isset($_POST['lti_message_type']) && isset($_POST['data'])) {
      $id = intval($_POST['data']);
      if ($_POST['lti_message_type'] == 'ContentItemSelection') {
        $_SESSION['message'] = 'Content Items Added: ' . $_POST['content_items'];
      }
    }
    if (isset($_REQUEST['citem'])) {
      $citem = $_REQUEST['citem'];
    }
// Process consumer selection
    if ($action == 'cilaunch') {
      if (empty($id)) {
        $_SESSION['error_message'] = 'Invalid consumer selected.';
        header('Location: ./');
        exit;
      } else {
        $_SESSION['lti_version'] = 'LTI-1p0';
        $_SESSION['consumer_pk'] = $id;

        $form_params = array();
        $form_params['custom_debug'] = 'true';
        $form_params['accept_media_types'] = '*/*';
        $form_params['accept_presentation_document_targets'] = 'none,embed,frame,iframe,window,popup,overlay';
        $form_params['content_item_return_url'] = $returnUrl;
        $form_params['data'] = $id;
        $form_params['user_id'] = '100';
        $form_params['roles'] = 'Instructor';
        $consumer = ToolProvider\ToolConsumer::fromRecordId($_SESSION['consumer_pk'], $data_connector);
        $form_params = $consumer->signParameters($launchUrl, 'ContentItemSelectionRequest', $_SESSION['lti_version'], $form_params);
        $page = ToolProvider\ToolProvider::sendForm($launchUrl, $form_params);
        echo $page;
        exit;
      }
    }

// Fetch a list of existing tool consumer records
    $consumers = $tool->getConsumers();

// Initialise an empty tool consumer instance
    $selected_consumer = new ToolProvider\ToolConsumer(NULL, $data_connector);
  }

// Page header
  $title = APP_NAME . ': Manage Content Items';
  $page = <<< EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="content-language" content="EN" />
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<title>{$title}</title>
<link href="../css/rating.css" media="screen" rel="stylesheet" type="text/css" />
<script type="text/javascript">
//<![CDATA[
var numSelected = 0;
function onConsumerSelected(el) {
  console.log('selected consumer: ' + el.value);
}
//]]>
</script>
</head>

<body>
<h1>{$title}</h1>

EOD;

// Display warning message if access does not appear to have been restricted
  if (!(isset($_SERVER['AUTH_TYPE']) && isset($_SERVER['REMOTE_USER']) && isset($_SERVER['PHP_AUTH_PW']))) {
    $page .= <<< EOD
<p><strong>*** WARNING *** Access to this page should be restricted to application administrators only.</strong></p>

EOD;
  }

// Check for any messages to be displayed
  if (isset($_SESSION['error_message'])) {
  $page .= <<< EOD
<p style="font-weight: bold; color: #f00;">ERROR: {$_SESSION['error_message']}</p>

EOD;
    unset($_SESSION['error_message']);
  }

  if (isset($_SESSION['message'])) {
  $page .= <<< EOD
<p style="font-weight: bold; color: #00f;">{$_SESSION['message']}</p>

EOD;
    unset($_SESSION['message']);
  }

// Display table of existing tool consumer records
  if ($ok) {

    if (count($consumers) <= 0) {
      $page .= <<< EOD
<p>No consumers have been added yet.</p>

EOD;
    } else {
      $page .= <<< EOD
<h2>Tool Consumer (Platform) Selection</h2>
<div class="box">
  <form method="POST">
    <span class="label">Platform:<span class="required" title="required">*</span></span>&nbsp;
    <select name="id" onchange="this.form.submit()" style="width: 400px">
      <option value="" disabled selected>--select--</option>

EOD;
      $available = 'cross';
      $available_alt = 'Not available';
      $trclass = 'notvisible';
      $protected = 'cross';
      $protected_alt = 'Not protected';
      $last = 'None';
      foreach ($consumers as $consumer) {
        $consumerid = urlencode($consumer->getRecordId());
        $selected = '';
        if ($consumer->getRecordId() === $id) {
          $selected_consumer = $consumer;
          $selected = ' selected';
          if (!$consumer->getIsAvailable()) {
            $available = 'cross';
            $available_alt = 'Not available';
            $trclass = 'notvisible';
          } else {
            $available = 'tick';
            $available_alt = 'Available';
            $trclass = '';
          }
          if ($consumer->protected) {
            $protected = 'tick';
            $protected_alt = 'Protected';
          } else {
            $protected = 'cross';
            $protected_alt = 'Not protected';
          }
          if (is_null($consumer->lastAccess)) {
            $last = 'None';
          } else {
            $last = date('j-M-Y', $consumer->lastAccess);
          }
        }
        $page .= <<< EOD
      <option value="{$consumerid}"{$selected}>{$consumer->name}</option>

EOD;
      }
      $page .= <<< EOD
    </select><br />
    <noscript><input type="submit" value="Submit"></noscript>
  </form>
  <table class="items" border="1" cellpadding="3">
  <thead>
    <tr>
      <th>Name</th>
      <th>Key</th>
      <th>Version</th>
      <th>Available?</th>
      <th>Protected?</th>
      <th>Last access</th>
    </tr>
  </thead>
  <tbody>
  <tr class={$trclass}>
    <td>{$selected_consumer->name}</td>
    <td>{$selected_consumer->getKey()}</td>
    <td><span title="{$selected_consumer->consumerGuid}">{$selected_consumer->consumerVersion}</span></td>
    <td class="aligncentre"><img src="../images/{$available}.gif" alt="{$available_alt}" title="{$available_alt}" /></td>
    <td class="aligncentre"><img src="../images/{$protected}.gif" alt="{$protected_alt}" title="{$protected_alt}" /></td>
    <td>{$last}</td>
  </tr>
  </tbody>
  </table>
</div>
EOD;

    }

// Go to Content Item Selection
    $disabled = isset($selected_consumer->created)? '': ' disabled';
    $page .= <<< EOD
<p class="clear" />
<h2>Content Item Selection</a></h2>
<div class="box">
  <form method="POST">
    <input type="hidden" name="id" value="{$id}" />
    <input type="hidden" name="do" value="cilaunch" />
    <input type="submit" value="Go to Select Content Items"{$disabled} />
  </form>
</div>

EOD;
// View Content Items Created
      $trclass = isset($selected_consumer->created)? '': 'notvisible';
      $page .= <<< EOD
<p class="clear" />
<h2>Content Items</h2>
<div class="box">
  <table class="items" border="1" cellpadding="3">
  <thead>
    <tr class={$trclass}>
      <th>Id</th>
      <th>Consumer Id</th>
      <th>Content Item Id</th>
      <th>Created</th>
      <th>Updated</th>
    </tr>
  </thead>
  <tbody>

EOD;
      if(isset($selected_consumer->created)) {
        $result = $db->query("SELECT * from `myrating_lti2_resource_link` order by `resource_link_pk` desc");
        while($rows = $result->fetch ()) {
          if (isset($rows['consumer_pk']) && intval($rows['consumer_pk']) == $id) {
            $page .= <<< EOD
  <tr class={$trclass}>
    <td>{$rows['resource_link_pk']}</td>
    <td>{$rows['consumer_pk']}</td>
    <td>{$rows['lti_resource_link_id']}</td>
    <td>{$rows['created']}</td>
    <td>{$rows['updated']}</td>
  </tr>

EOD;
          }
        }
      }
      $page .= <<< EOD
  </tbody>
  </table>
</div>

EOD;
// View Rating Items
    $page .= <<< EOD
<p class="clear" />
<h2>Rating Items</a></h2>
<div class="box">
  <form method="post">
    <input type="hidden" name="id" value="{$id}" />
    <span class="label">Content Item Id:<span class="required" title="required">*</span></span>&nbsp;<input name="citem" type="text" value="{$citem}" size="50" maxlength="100"{$disabled} /><br />
    <span class="label"><span class="required" title="required">*</span>&nbsp;=&nbsp;required field</span>&nbsp;<input type="submit" value="Fetch Rating Items"{$disabled} />
  </form>

EOD;
      $page .= <<< EOD
  <br />
  <table class="items" border="1" cellpadding="3">
  <thead>
    <tr class={$trclass}>
      <th>Id</th>
      <th>Title</th>
      <th>Text</th>
      <th>Url</th>
      <th>Max Rating</th>
      <th>Step</th>
      <th>Visible</th>
      <th>Sequence</th>
      <th>Created</th>
      <th>Updated</th>
    </tr>
  </thead>
  <tbody>

EOD;
      if(isset($selected_consumer->created) && strlen($citem) > 0) {
        $query = 'SELECT `resource_link_pk` FROM `myrating_lti2_resource_link` WHERE `lti_resource_link_id`="'.$citem.'" and `consumer_pk`='.$id;
        $query = "SELECT * FROM `myrating_item` WHERE `resource_link_pk` IN (" . $query . ")";
        $result = $db->query($query);
        while($rows = $result->fetch ()) {
          $page .= <<< EOD
  <tr class={$trclass}>
    <td>{$rows['item_pk']}</td>
    <td>{$rows['item_title']}</td>
    <td>{$rows['item_text']}</td>
    <td>{$rows['item_url']}</td>
    <td>{$rows['max_rating']}</td>
    <td>{$rows['step']}</td>
    <td>{$rows['visible']}</td>
    <td>{$rows['sequence']}</td>
    <td>{$rows['created']}</td>
    <td>{$rows['updated']}</td>
  </tr>

EOD;
        }
      }
      $page .= <<< EOD
  </tbody>
  </table>
</div>

EOD;
  }

// Page footer
  $page .= <<< EOD
</body>
</html>

EOD;

// Display page
  echo $page;

?>
