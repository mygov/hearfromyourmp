<?
// subscribe.php:
// Signing up for HearFromYourMP.
//
// Copyright (c) 2005 UK Citizens Online Democracy. All rights reserved.
// Email: matthew@mysociety.org. WWW: http://www.mysociety.org
//
// $Id: subscribe.php,v 1.22 2006-03-13 13:22:39 francis Exp $

require_once '../phplib/ycml.php';
require_once '../phplib/fns.php';
require_once '../phplib/constituent.php';
require_once '../../phplib/person.php';
require_once '../../phplib/utility.php';
require_once '../../phplib/importparams.php';

$title = _('Signing up');
page_header($title);
$extra = '';
if (get_http_var('subscribe')) {
    $errors = do_subscribe();
    if (is_array($errors)) {
        print '<div id="errors"><ul><li>';
        print join ('</li><li>', $errors);
        print '</li></ul></div>';
        constituent_subscribe_box();
    }
} elseif (get_http_var('direct_unsubscribe')) {
    // Clicked from email to unsubscribe
    $constituent_id = get_http_var('direct_unsubscribe');
    $P = person_if_signed_on();
    if (!$P) 
        err(_('Unexpectedly not signed on after following unsubscribe link'));
    $row = db_getRow("select * from constituent where id = ?", $constituent_id);
    print '<p>';
    if ($row) {
        constituent_unsubscribe($P->id(), $constituent_id);
        print "Thanks! You won't receive more email from that constituency.";
    } else {
        print "Thanks! You are already unsubscribed from that constituency.";
    }
    print '</p>';
} else {
    constituent_subscribe_box();
}
page_footer(array('extra' => $extra));

function do_subscribe() {
    global $q_email, $q_name, $q_postcode, $q_h_postcode, $extra;
    $errors = importparams(
                array('name',      "/./", 'Please enter a name'),
                array('email',      "importparams_validate_email"),
                array('postcode',      "importparams_validate_postcode")
            );
    if (!is_null($errors))
        return $errors;

    $wmc_id = ycml_get_constituency_id($q_postcode);
    $area_info = ycml_get_area_info($wmc_id);
    $rep_info = ycml_get_mp_info($wmc_id);

    /* Check for authentication forwarded from WriteToThem.com */
    $external_auth = auth_verify_with_shared_secret($q_email, OPTION_AUTH_SHARED_SECRET, get_http_var('sign'));
    if ($external_auth) {
        $person = person_get_or_create($q_email, $q_name);
    } else {
        $person = person_if_signed_on();
        if (!$person) {
            /* Otherwise get the user to log in. */
            $template_data = array();
            $template_data['reason_web'] = _('Before adding you to HearFromYourMP, we need to confirm your email address.');
            $template_data['rep_name'] = $rep_info['name'];
            $template_data['area_name'] = $area_info['name'];
            $template_data['user_name'] = $q_name;
            $template_data['user_email'] = $q_email;
            $token = auth_token_store('login', array(
                'email' => $q_email,
                'name' => $q_name,
                'stash' => stash_request(),
                'direct' => 1
            ));
            db_commit();
            $url = OPTION_BASE_URL . "/L/$token";
            $template_data['url'] = $url;
            ycml_send_email_template($q_email, 'confirm-subscribe', $template_data);
            page_header("Now check your email");
?>
<p id="loudmessage">
Now check your email!<br>
We've sent you an email, and you'll need to click the link in it before you can
continue
</p>
<?
            page_footer(array('nonav' => 1));
            exit();
            /* NOTREACHED */
        }
    }
    $person_id = $person->id();

    $already_signed = db_getOne("select id from constituent where 
        constituency = ? and person_id = ?
        for update", array( $wmc_id, $person_id ) );
    if ($already_signed) { ?>
<p class="loudmessage" align="center">You have already signed up to HearFromYourMP in this constituency!</p>
<?  #    return;
    }

    if (!$already_signed) {
        db_query("insert into constituent (
                    person_id, constituency,
                    postcode, creation_ipaddr
                )
                values (?, ?, ?, ?)", array(
                    $person_id, $wmc_id,
                    $q_postcode, $_SERVER['REMOTE_ADDR']
                ));
        db_commit();
        $extra = "subscribed=1";
    }
    $count = db_getOne("select count(*) from constituent where constituency = ?", $wmc_id);
    $nothanks = db_getRow('SELECT status,website,gender FROM mp_nothanks WHERE constituency = ?', $wmc_id);

    $local_pledges = file_get_contents('http://www.pledgebank.com/rss?postcode=' . urlencode($q_postcode));
    preg_match_all('#<link>(.*?)</link>\s+<description>(.*?)</description>#', $local_pledges, $m, PREG_SET_ORDER);
    $local_num = count($m) - 1;
    if ($local_num>5) $local_num = 5;
    if ($local_num) {
        print '<div id="pledges"><h2>Recent pledges local to ' . canonicalise_postcode($q_h_postcode) . '</h2>';
        print '<p style="margin-top:0; text-align:right; font-size: 89%">These are pledges near you made by users of <a href="http://www.pledgebank.com/">PledgeBank</a>, another mySociety site. We thought you might be interested. N.B. mySociety does not endorse specific pledges.</p> <ul>';
        for ($p=1; $p<=$local_num; ++$p) {
            print '<li><a href="' . $m[$p][1] . '">' . $m[$p][2] . '</a>';
        }
        print '</ul><p align="center"><a href="http://www.pledgebank.com/alert?postcode='.$q_h_postcode.'">Get emails about local pledges</a></p></div>';
    }
?>
<p class="loudmessage"><?
    print sprintf("<strong>Thanks! You're the %s person to sign up to get emails from %s in the %s constituency.</strong> ",
        english_ordinal($count), $rep_info['name'], $area_info['name']);
    if ($nothanks['status'] == 't') {
        $mp_gender = $nothanks['gender'];
        if ($mp_gender == 'm') { $nomi = 'he is'; $accu = 'him'; $geni = 'his'; }
        elseif ($mp_gender == 'f') { $nomi = 'she is'; $accu = 'her'; $geni = 'her'; }
        else { $nomi = 'they are'; $accu = 'them'; $geni = 'their'; }
        $mp_website = $nothanks['website']; ?>
Unfortunately, <?=$rep_info['name'] ?> has said <?=$nomi ?> not interested in using this
service<?
        if ($mp_website)
            print ', and asks that we encourage users to visit ' . $geni . ' website at <a href="' . $mp_website . '">' . $mp_website . '</a>';
?>. You can still contact <?=$accu ?> directly via our service
<a href="http://www.writetothem.com/">www.writetothem.com</a>.</p>

<p>In accordance with our site policy we will continue to allow signups for
<?=$area_info['name'] ?>. As our FAQ says &quot;There is one list per
constituency, not per MP, and we will continue to accept subscribers
regardless of whether your current MP chooses to use the site or not.
If your MP changes for any reason, we will hand access to the list
over to their successor.&quot;</p>
<?  } else {
        $next_threshold = db_getOne('select mp_threshold(?, +1)', $count);
        $next_next_threshold = db_getOne('select mp_threshold(?, +1)', $next_threshold);
?>
<h2>What happens next?</h2>
<p>The mailing list for <?=$area_info['name']?> will keep growing until there
are <?=$next_threshold?> people signed up. We'll then automatically send your
MP an email asking them to talk to you.</p>
<p>When you get the email, it'll contain a link, right underneath the MP's words.
To talk about what they said all you'll have to do is click on the link, and start
talking with other local people, and, if you're lucky, your MP as well.</p>
<p>If your MP doesn't respond to the next email we send them, we'll remind them again
when there are <?=$next_next_threshold?> people signed up.</p>
<?  }
?>
<p><strong>While you wait, why not
<a href="http://www.theyworkforyou.com/mp/?c=<?=urlencode($area_info['name']) ?>">follow
what <?=$rep_info['name'] ?> does in Parliament</a>, including speeches made, questions
asked, and general information about them, on our sister site
<a href="http://www.theyworkforyou.com/mp/?c=<?=urlencode($area_info['name']) ?>">TheyWorkForYou</a>?</strong></p>

<?  if ($return = get_http_var('r'))
        print '<p><a href="' . htmlspecialchars($return). '">Continue to where you came from</a></p>';
}

?>
