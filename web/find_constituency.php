<?
// find_constituency.php:
// Given a postcode, redirect to a particular constituency page.
//
// Copyright (c) 2005 UK Citizens Online Democracy. All rights reserved.
// Email: etienne@ejhp.net WWW: http://www.mysociety.org
//
// $Id: find_constituency.php,v 1.2 2005-12-22 11:14:59 matthew Exp $

require_once '../phplib/ycml.php';
require_once '../phplib/fns.php';
require_once '../phplib/constituent.php';
require_once '../../phplib/person.php';
require_once '../../phplib/utility.php';
require_once '../../phplib/importparams.php';

postcode_page();

function postcode_page() {
    global $q_email, $q_name, $q_postcode, $q_h_postcode;
    $errors = importparams(
                array('postcode',      "importparams_validate_postcode")
            );
    if (!is_null($errors)) {
	$title = 'Invalid UK postcode';
	page_header($title);
?>
<div id="errors"><ul><li>Sorry, that's not a valid UK postcode. Please try again!</li></ul></div>';
<?
	postcode_to_constituency_form();
	page_footer();
    } else {
        $wmc_id = ycml_get_constituency_id($q_postcode);
        header('Location: http://'.OPTION_WEB_DOMAIN."/view/$wmc_id");
    }
}