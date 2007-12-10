<?
// post.php:
// Posting messages through HearFromYourMP.
//
// Copyright (c) 2006 UK Citizens Online Democracy. All rights reserved.
// Email: matthew@mysociety.org. WWW: http://www.mysociety.org
//
// $Id: post.php,v 1.16 2007-12-10 11:29:55 angie Exp $

require_once '../phplib/ycml.php';
require_once '../phplib/constituent.php';
require_once '../phplib/comment.php';
require_once '../../phplib/person.php';
require_once '../../phplib/importparams.php';
require_once '../../phplib/dadem.php';

$title = 'Post a message';
page_header($title);
$P = person_if_signed_on();
if (!$P) 
    err('You must be logged in to view this page. Please click the unique link
in an email that has been sent to you. If you have done that and are still
seeing this page, then your browser does not have cookies enabled, which we
use to track logins. Please enable cookies and try again.');

importparams(
    array('area_id', '/^\d+$/', '', null),
    array('rep', '/^\d+$/', '', null),
    array('post', '/^\d$/', '', null)
);
if ($q_rep && !$q_area_id) {
    $rep_info = dadem_get_representative_info($q_rep);
    $q_area_id = $rep_info['voting_area'];
}
if (!$q_rep && $q_area_id) {
    $reps = dadem_get_representatives($q_area_id);
    $q_rep = $reps[0];
    $rep_info = dadem_get_representative_info($q_rep);
}

$is_rep = constituent_is_rep($P->id(), $q_area_id);
if (!$is_rep || $is_rep == 'f')
    err('You cannot use this form, sorry.');

if ($q_post == 2) { # Post
    if (importparams(
        array('message', '/./', 'Please write a message', null),
        array('subject', '/./', 'Please include a subject', null)
    ))
        err("Blank subject or message when confirming - should not happen!");
    $q_message = str_replace("\r", '', $q_message);
    db_query("INSERT INTO message (area_id, subject, content, state, rep_id)
                VALUES (?, ?, ?, 'approved', ?)",
                array($q_area_id, $q_subject, $q_message, $q_rep));
    db_commit();
    print '<p><em>Thank you; your message has been posted, and will be emailed to the subscribed constituents shortly.</em></p>';
    # TODO: Cross-sell TheyWorkForYou!? Maybe ask for copyright-free photos of them?
} elseif ($q_post == 1) { # Preview
    if ($err = importparams(
        array('message', '/./', 'Please write a message', null),
        array('subject', '/./', 'Please include a subject', null)
    )) {
        print '<ul><li>' . join('<li>', array_values($err)) . '</ul>';
        post_message_form();
    } else {
        print '<p>You can now see how your message will look, both on our website and in our email to your constituents. Please check it through, then see the bottom of this page to either confirm your message or make any changes.</p>';
        $content = comment_prettify($q_message);
        $content = preg_replace('#<p>\*(.*?)\*</p>#', "<h3>$1</h3>", $content);
        $content = preg_replace('#((<p>\*.*?</p>\n)+)#e', "'<ul>'.str_replace('<p>*', '<li>', '$1') . \"</ul>\n\"", $content);
        print '<div id="message"><h2>' . $q_h_subject . '</h2> <blockquote><p>' . $content . '</p></blockquote></div>';
        print '<p>And this is how it will appear in the email to constituents:</p>';
        $preview = preg_replace('#\r#', '', htmlspecialchars($q_message));
        print '<pre>';
        $paras = preg_split('/\n{2,}/', $preview);
        foreach ($paras as $para) {
            $para = str_replace("\n", " ", $para);
            $para = "     $para";
            print wordwrap($para, 64, "\n     ");
            print "\n\n";
        }
?>
</pre>
<p>If you are happy with this, please click this button to confirm your message:</p>
<form method="post" name="confirm_form" accept-charset="UTF-8">
<input type="hidden" name="post" value="2">
<input type="hidden" name="subject" value="<?=$q_h_subject ?>">
<input type="hidden" name="message" value="<?=$q_h_message ?>">
<p align="center"><input type="submit" value="Confirm message" style="font-size:150%"></p>
</form>
<p>Or if you wish to make any changes to the above, please change your text in the form below:</p>
<?      post_message_form();
    }
} else {
?>
<p>Hello, <?=$P->name() ?>. To post a message through <?=$_SERVER['site_name']?>,
please enter a subject and
message in the boxes below, then click "Preview". You will be given the opportunity to preview
and re-edit your message before it is confirmed and sent.</p>

<?
    $r = db_getRow("select id, rep_id, subject,
            date_trunc('second', posted) as posted
        from message
        where area_id=? and state='approved'
        order by posted desc limit 1", $q_area_id);
    if ($r) {
        if ($r['rep_id'] == $q_rep) {
            $last_name = $rep_info['name'];
        } else {
            $last_rep = dadem_get_representative_info($r['rep_id']);
            $last_name = $last_rep['name'];
        }
        print '<p>The last message in this ' . area_type() . ' was ';
        print '<a href="/view/message/' . $r['id'] . '">';
        print "$r[subject]</a> by $last_name on ";
        print prettify($r['posted']);
        print '.</p>';
    }
?>

<p>We find that the <?=rep_type('plural') ?> who succeed in provoking the largest number of interesting comments
from their constituents tend to send short messages on a single topic. Often asking your
constituents for their views on something provokes interesting responses. Here are a couple
of examples on HearFromYourMP: Stephen William's
<a href="http://www.hearfromyourmp.com/view/message/6" target="_blank">post on smoking in public places</a>,
and Ed Vaizey's
<a href="http://www.hearfromyourmp.com/view/message/91" target="_blank">post on climate change</a>.
</p>
<?  post_message_form();
}
page_footer();

function post_message_form() {
    global $q_h_subject, $q_h_message;
?>
<form method="post" name="message_form" accept-charset="UTF-8">
<input type="hidden" name="post" value="1">
<table cellpadding="3" cellspacing="0" border="0">
<tr><th><label for="subject">Subject:</label></th>
<td><input type="text" id="subject" name="subject" value="<?=$q_h_subject ?>" size="40"></td>
</tr>
<tr valign="top"><th><label for="message">Message:</label></th>
<td><textarea id="message" name="message" rows="10" cols="58"><?=$q_h_message ?></textarea></td>
</tr></table>
<input type="submit" value="Preview">
</form>
<?
}
