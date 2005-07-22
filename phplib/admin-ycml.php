<?php
/*
 * admin-ycml.php:
 * YCML admin pages.
 * 
 * Copyright (c) 2005 UK Citizens Online Democracy. All rights reserved.
 * Email: francis@mysociety.org. WWW: http://www.mysociety.org
 *
 * $Id: admin-ycml.php,v 1.1 2005-07-22 11:54:10 matthew Exp $
 * 
 */

require_once "../phplib/ycml.php";
require_once "fns.php";
require_once "../../phplib/db.php";
require_once "../../phplib/mapit.php";
require_once "../../phplib/dadem.php";
require_once "../../phplib/utility.php";
require_once "../../phplib/importparams.php";

class ADMIN_PAGE_YCML_MAIN {
    function ADMIN_PAGE_YCML_MAIN () {
        $this->id = "ycml";
        $this->navname = _("YCML Summary");
    }

    function table_header($sort) {
        print '<table border="1" cellpadding="5" cellspacing="0"><tr>';
        $cols = array(
            'c'=>'Constituency', 
            's'=>'Signups',
            'l'=>'Latest signup'
        );
        foreach ($cols as $s => $col) {
            print '<th>';
            if ($sort != $s) print '<a href="'.$this->self_link.'&amp;s='.$s.'">';
            print $col;
            if ($sort != $s) print '</a>';
            print '</th>';
        }
        print '</tr>';
        print "\n";
    }

    function list_all() {
        global $open;
        $sort = get_http_var('s');
        if (!$sort || preg_match('/[^csl]/', $sort)) $sort = 's';
        $order = '';
        if ($sort=='l') $order = 'latest DESC';
        elseif ($sort=='c') $order = 'constituency';
        elseif ($sort=='s') $order = 'count DESC';

        $q = db_query('SELECT COUNT(id) AS count,constituency,EXTRACT(epoch FROM MAX(creation_time)) AS latest FROM constituent GROUP BY constituency' . 
            ($order ? ' ORDER BY ' . $order : '') );
        $rows = array();
        while ($r = db_fetch_array($q)) {
            $rows[] = array_map('htmlspecialchars', $r);
            $ids[] = $r['constituency'];
        }

        $areas_info = mapit_get_voting_areas_info($ids);

        foreach ($rows as $k=>$r) {
            $c_id = $r['constituency'];
            $c_name = $areas_info[$c_id]['name'];
            $row = "";
            $row .= '<td>' . $c_name . '<br><a href="'.$this->self_link.'&amp;constituency='.$c_id.'">admin</a> |
                <a href="?page=ycmllatest&amp;constituency='.$c_id.'">timeline</a>';
            $row .= '</td>';
            $row .= '<td align="center">' . $r['count'] . '</td>';
            $row .= '<td>' . prettify($r['latest']) . '</td>';
            $rows[$k] = $row;
        }
        if (count($rows)) {
            print '<p>Here\'s the current state of YCML:</p>';
            $this->table_header($sort);
            $a = 0;
            foreach ($rows as $row) {
                print '<tr'.($a++%2==0?' class="v"':'').'>';
                print $row;
                print '</tr>'."\n";
            }
            print '</table>';
        } else {
            print '<p>No-one has signed up to YCML at all, anywhere, ever.</p>';
        }
    }

    function show_constituency($id) {
        print '<p><a href="'.$this->self_link.'">' . _('Main page') . '</a></p>';

        $sort = get_http_var('s');
        if (!$sort || preg_match('/[^etn]/', $sort)) $sort = 'e';

        $area_info = mapit_get_voting_area_info($id);

        $reps = dadem_get_representatives($id);
        $rep_info = dadem_get_representative_info($reps[0]);
        $query = 'SELECT constituent.*, person.*, extract(epoch from creation_time) as creation_time
                   FROM constituent
                   LEFT JOIN person ON person.id = constituent.person_id
                   WHERE constituency=?';
        if ($sort=='t') $query .= ' ORDER BY constituent.creation_time DESC';
        elseif ($sort=='n') $query .= ' ORDER BY showname DESC';
        $q = db_query($query, $id);
        $subscribers = db_num_rows($q);

        $out = array();
        print "<h2>The constituency of $area_info[name]</h2>";
        print "<p>The MP for this constituency is $rep_info[name] ($rep_info[party]). Subscribed so far: $subscribers.";

        print "<h2>Subscribers</h2>";
        while ($r = db_fetch_array($q)) {
            $r = array_map('htmlspecialchars', $r);
            $e = array();
            if ($r['name']) array_push($e, $r['name']);
            if ($r['email']) array_push($e, $r['email']);
            $e = join("<br>", $e);
            $out[$e] = '<td>'.$e.'</td>';
            $out[$e] .= '<td>'.prettify($r['creation_time']).'</td>';

#            $out[$e] .= '<td><form name="shownameform" method="post" action="'.$this->self_link.'"><input type="hidden" name="showname_signer_id" value="' . $r['signid'] . '">';
#            $out[$e] .= '<select name="showname">';
#            $out[$e] .=  '<option value="1"' . ($r['showname'] == 't'?' selected':'') . '>Yes</option>';
#            $out[$e] .=  '<option value="0"' . ($r['showname'] == 'f'?' selected':'') . '>No</option>';
#            $out[$e] .=  '</select>';
#            $out[$e] .= '<input type="submit" name="showname_signer" value="update">';
#            $out[$e] .= '</form></td>';

#            $out[$e] .= '<td>';
#            $out[$e] .= '<form name="removesignerform" method="post" action="'.$this->self_link.'"><input type="hidden" name="remove_signer_id" value="' . $r['signid'] . '"><input type="submit" name="remove_signer" value="Remove signer permanently"></form>';
#            $out[$e] .= '</td>';
        }
        if ($sort == 'e') {
            function sort_by_domain($a, $b) {
                $aa = stristr($a, '@');
                $bb = stristr($b, '@');
                if ($aa==$bb) return 0;
                return ($aa>$bb) ? 1 : -1;
            }
            uksort($out, 'sort_by_domain');
        }
        if (count($out)) {
            print '<table border="1" cellpadding="3" cellspacing="0"><tr>';
            $cols = array('e'=>'Signer', 't'=>'Time', 'n'=>'Show name?');
            foreach ($cols as $s => $col) {
                print '<th>';
                if ($sort != $s) print '<a href="'.$this->self_link.'&amp;constituency='.$id.'&amp;s='.$s.'">';
                print $col;
                if ($sort != $s) print '</a>';
                print '</th>';
            }
            print '<th>Action</th>';
            print '</tr>';
            $a = 0;
            foreach ($out as $row) {
                print '<tr'.($a++%2==0?' class="v"':'').'>';
                print $row;
                print '</tr>';
            }
            print '</table>';
        } else {
            print '<p>Nobody has signed up to this pledge.</p>';
        }
        print '<p>';
        
        // Messages
        print h2(_("Messages"));
        $q = db_query('select * from message 
                where pledge_id = ? order by whencreated', $pdata['id']);

        $n = 0;
        while ($r = db_fetch_array($q)) {
            if ($n++)
                print '<hr>';

            $got_creator_count = db_getOne('select count(*) from message_creator_recipient where message_id = ?', $r['id']);
            $got_signer_count = db_getOne('select count(*) from message_signer_recipient where message_id = ?', $r['id']);

            $whom = array();
            if ($r['sendtocreator'] == 't') { $whom[] = 'creator'; }
            if ($r['sendtosigners'] == 't') { $whom[] = 'signers'; }
            if ($r['sendtolatesigners'] == 't') { $whom[] = 'late signers'; }

            print "<p>";
            print "<strong>". $r['circumstance'] . ' ' . $r['circumstance_count'] . '</strong>';
            print " created on ". prettify(substr($r['whencreated'], 0, 19));
            print " to be sent from <strong>" . $r['fromaddress'] . "</strong> to <strong>";
            print join(", ", $whom) . "</strong>";
            print "<br>has been queued to evel for ";
            print "<strong>$got_creator_count creators</strong>";
            print " and <strong>$got_signer_count signers</strong>";
            if ($r['sms'])
                print "<br><strong>sms content:</strong> " . $r['sms'];
            if ($r['emailtemplatename'])
                print "<br><strong>email template:</strong> " . $r['emailtemplatename'];
            if ($r['emailsubject'])
                print "<br><strong>email subject:</strong> " . $r['emailsubject'];
            if ($r['emailbody']) {
                ?><br><strong>email body:</strong>
                <div class="message"><?= comments_text_to_html($r['emailbody']) ?></div> <?
            }

        }
        if ($n == 0) {
            print "No messages yet.";
        }

        // Category setting
        $cats = array();
        $q = db_query('select category_id from pledge_category where pledge_id = '.$pdata['id']);
        while ($r = db_fetch_array($q)) {
            $cats[$r['category_id']] = 1;
        }
        print '<form name="categoriesform" method="post" action="'.$this->self_link.'">
            <input type="hidden" name="pledge_id" value="'.$pdata['id'].'">
            <input type="hidden" name="update_cats" value="1">
            <h2>Categories</h2>
            <p><select name="categories[]" multiple>';
        $s = db_query('select id, parent_category_id, name from category 
            where parent_category_id is null
            order by id');
        while ($a = db_fetch_row($s)) {
            list($id, $parent_id, $name) = $a;
            print '<option';
            if (array_key_exists($id, $cats)) print ' selected';
            print ' value="' . $id . '">' .
                (is_null($parent_id) ? '' : '&nbsp;-&nbsp;') . 
                 htmlspecialchars($name) . ' </option>';
        }
        print '</select> <input type="submit" value="Update"></p></form>';

        print '<h2>Actions</h2>';
        print '<form name="sendannounceform" method="post" action="'.$this->self_link.'"><input type="hidden" name="send_announce_token_pledge_id" value="' . $pdata['id'] . '"><input type="submit" name="send_announce_token" value="Send announce URL to creator"></form>';

print '<form name="removepledgepermanentlyform" method="post" action="'.$this->self_link.'"><strong>Caution!</strong> This really is forever, you probably don\'t want to do it: <input type="hidden" name="remove_pledge_id" value="' . $pdata['id'] . '"><input type="submit" name="remove_pledge" value="Remove pledge permanently"></form>';

    }

    function remove_pledge($id) {
        pledge_delete_pledge($id);
        db_commit();
        print p(_('<em>That pledge has been successfully removed, along with all its signatories.</em>'));
    }

    function remove_signer($id) {
        pledge_delete_signer($id);
        db_commit();
        print p(_('<em>That signer has been successfully removed.</em>'));
    }

    function showname_signer($id) {
        db_query('UPDATE signers set showname = ? where id = ?', 
            array(get_http_var('showname') ? true : false, $id));
        db_commit();
        print p(_('<em>Show name for signer updated</em>'));
    }

    function update_prominence($pledge_id) {
        db_query('UPDATE pledges set prominence = ? where id = ?', array(get_http_var('prominence'), $pledge_id));
        db_commit();
        print p(_("<em>Changes to pledge prominence saved</em>"));
    }

    function display($self_link) {
        $constituency = get_http_var('constituency');
/*
        // Perform actions
        if (get_http_var('update_prom')) {
            $pledge_id = get_http_var('pledge_id');
            $this->update_prominence($pledge_id);
        } elseif (get_http_var('remove_pledge_id')) {
            $remove_id = get_http_var('remove_pledge_id');
            if (ctype_digit($remove_id))
                $this->remove_pledge($remove_id);
        } elseif (get_http_var('remove_signer_id')) {
            $signer_id = get_http_var('remove_signer_id');
            if (ctype_digit($signer_id)) {
                $pledge_id = db_getOne("SELECT pledge_id FROM signers WHERE id = $signer_id");
                $this->remove_signer($signer_id);
            }
        } elseif (get_http_var('showname_signer_id')) {
            $signer_id = get_http_var('showname_signer_id');
            if (ctype_digit($signer_id)) {
                $pledge_id = db_getOne("SELECT pledge_id FROM signers WHERE id = $signer_id");
                $this->showname_signer($signer_id);
            }
         } elseif (get_http_var('update_cats')) {
            $pledge_id = get_http_var('pledge_id');
            $this->update_categories($pledge_id);
        } elseif (get_http_var('send_announce_token')) {
            $pledge_id = get_http_var('send_announce_token_pledge_id');
            if (ctype_digit($pledge_id)) {
                send_announce_token($pledge_id);
                print p(_('<em>Announcement permission mail sent</em>'));
            }
        }
*/

        // Display page
        if ($constituency) {
            $this->show_constituency($constituency);
        } else {
            $this->list_all();
        }
    }
}

class ADMIN_PAGE_PB_LATEST {
    function ADMIN_PAGE_PB_LATEST() {
        $this->id = 'pblatest';
        $this->navname = 'Timeline';

        if (get_http_var('linelimit')) {
            $this->linelimit = get_http_var('linelimit');
        } else {
            $this->linelimit = 250;
        }

        $this->ref = null;
        if ($ref = get_http_var('ref')) {
            $this->ref = db_getOne('select id from pledges where ref=?', $ref);
        }
        $this->ignore = null;
        if ($ignore = get_http_var('ignore')) {
            $this->ignore = db_getOne('select id from pledges where ref=?', $ignore);
        }
    }

    # pledges use creationtime
    # signers use signtime
    function show_latest_changes() {
        $q = db_query('SELECT signers.name, signer_person.email,
                              signers.mobile, signtime, showname, pledges.title,
                              pledges.ref, pledges.id,
                              extract(epoch from signtime) as epoch
                         FROM pledges, signers
                         LEFT JOIN person AS signer_person ON signer_person.id = signers.person_id
                        WHERE signers.pledge_id = pledges.id
                     ORDER BY signtime DESC');
        while ($r = db_fetch_array($q)) {
            if (!$this->ref || $this->ref==$r['id']) {
                $signed[$r['id']][$r['email']] = 1;
                $time[$r['epoch']][] = $r;
            }
        }

        // Token display not so useful, and wastes too much space
        // (what would be useful is unused tokens)
        /*
        $q = db_query('SELECT *,extract(epoch from created) as epoch
                         FROM token
                     ORDER BY created DESC');
        while ($r = db_fetch_array($q)) {
            $stuff = $r['data'];
            $pos = 0;
            $res = rabx_wire_rd(&$stuff, &$pos);
            if (rabx_is_error($res)) {
                $r['error'] = 'RABX Error: ' . $res->text;
            }
            if ($r['scope'] == "login") {
                $stash_data = db_getRow('select * from requeststash where key = ?', $res['stash']);
                # TODO: Could extract data from post_data here for display if it were useful to do so
                $time[$r['epoch']][] = array_merge(array_merge($r, $res), $stash_data);
            } else {
                if (!isset($signed[$res['pledge_id']]) || 
                    !isset($res['email']) || 
                    !isset($signed[$res['pledge_id']][$res['email']])) {
                        $time[$r['epoch']][] = array_merge($r, $res);
                }
            }
        }
        */
    
        $q = db_query('SELECT pledges.*,extract(epoch from creationtime) as epoch, person.email as email
                         FROM pledges LEFT JOIN person ON person.id = pledges.person_id
                     ORDER BY pledges.id DESC');
        $this->pledgeref = array();
        while ($r = db_fetch_array($q)) {
            if (!$this->ref || $this->ref==$r['id']) {
                if (!get_http_var('onlysigners')) {
                    $time[$r['epoch']][] = $r;
                }
                $this->pledgeref[$r['id']] = $r['ref'];
            }
        }
        if (!get_http_var('onlysigners')) {
            $q = db_query('SELECT *
                             FROM incomingsms
                         ORDER BY whenreceived DESC');
            while ($r = db_fetch_array($q)) {
                $time[$r['whenreceived']][] = $r;
            }
            $q = db_query('SELECT *
                             FROM outgoingsms
                         ORDER BY lastsendattempt DESC LIMIT 10');
            while ($r = db_fetch_array($q)) {
                if (!$this->ref) {
                    $time[$r['lastsendattempt']][] = $r;
                }
            }
            $q = db_query('SELECT whencreated, circumstance, ref,extract(epoch from whencreated) as epoch, pledges.id
                             FROM message, pledges
                            WHERE message.pledge_id = pledges.id
                         ORDER BY whencreated DESC');
            while ($r = db_fetch_array($q)) {
                if (!$this->ref || $this->ref==$r['id']) {
                    $time[$r['epoch']][] = $r;
                }
            }
            $q = db_query('SELECT comment.*, extract(epoch from whenposted) as commentposted,
                                  person.email as author_email
                             FROM comment
                             LEFT JOIN person ON person.id = comment.person_id
                         ORDER BY whenposted DESC');
            while ($r = db_fetch_array($q)) {
                if (!$this->ref || $this->ref==$r['pledge_id']) {
                    $time[$r['commentposted']][] = $r;
                }
            }
            $q = db_query('SELECT alert.postcode as alertpostcode, 
                                    extract(epoch from whenqueued) as whenqueued,
                                  person.email as email, person.name as name,
                                  pledges.ref as ref, pledges.id as pledge_id
                             FROM alert_sent
                             LEFT JOIN alert ON alert.id = alert_sent.alert_id
                             LEFT JOIN person ON person.id = alert.person_id
                             LEFT JOIN pledges ON alert_sent.pledge_id = pledges.id
                             WHERE event_code = \'pledges/local/GB\'
                         ORDER BY whenqueued DESC');
            while ($r = db_fetch_array($q)) {
                if (!$this->ref || $this->ref==$r['pledge_id']) {
                    $time[$r['whenqueued']][] = $r;
                }
            }
         }
        krsort($time);

        print '<a href="'.$this->self_link.'">Full log</a>';
        if ($this->ref) {
            print ' | <em>Viewing only pledge "'.$this->pledgeref[$this->ref].'"</em> (<a href="?page=pb&amp;pledge='.$this->pledgeref[$this->ref].'">admin</a>)';
        } elseif ($this->ignore) {
            print ' | <em>Ignoring pledge "'.$this->pledgeref[$this->ignore].'"</em> (<a href="?page=pb&amp;pledge='.$this->pledgeref[$this->ignore].'">admin</a>)';
        } else {
            print ' | <a href="'.$this->self_link.'&amp;onlysigners=1">Only signatures</a>';
        }
        $date = ''; 
        $linecount = 0;
        print "<div class=\"timeline\">";
        foreach ($time as $epoch => $datas) {
            $linecount++;
            if ($linecount > $this->linelimit) {
                print '<dt><br><a href="'.$this->self_link.
                        '&linelimit='.htmlspecialchars($this->linelimit + 250).'">Expand timeline...</a></dt>';
                break;
            }
            $curdate = date('l, jS F Y', $epoch);
            if ($date != $curdate) {
                if ($date <> "")
                    print '</dl>';
                print '<h2>'. $curdate . '</h2> <dl>';
                $date = $curdate;
            }
            print '<dt><b>' . date('H:i:s', $epoch) . ':</b></dt> <dd>';
            foreach ($datas as $data) {
            if (array_key_exists('signtime', $data)) {
                print $this->pledge_link('ref', $data['ref']);
                if ($data['showname'] == 'f')
                    print ' anonymously';
                print ' signed by ';
                print $data['name'];
                if ($data['email']) print ' &lt;'.$data['email'].'&gt;';
                if ($data['mobile']) print ' (' . $data['mobile'] . ')';
            } elseif (array_key_exists('creationtime', $data)) {
                print "Pledge $data[id], ref <em>$data[ref]</em>, ";
                print $this->pledge_link('ref', $data['ref'], $data['title']) . ' created (confirmed)';
                print " by $data[name] &lt;$data[email]&gt;";
            } elseif (array_key_exists('whenreceived', $data)) {
                print "Incoming SMS from $data[sender] received, sent
                $data[whensent], message $data[message]
                ($data[foreignid] $data[network])";
            } elseif (array_key_exists('whencreated', $data)) {
                print "Message $data[circumstance] queued for pledge " .
                $this->pledge_link('ref', $data['ref']);
            } elseif (array_key_exists('created', $data)) {
                if (array_key_exists('error', $data)) {
                    print '<em>' . $data['error'] . '</em><br>';
                }
                print "$data[scope] token $data[token] created ";
                if (array_key_exists('email', $data)) {
                    print "for $data[name] $data[email] ";
                    if (array_key_exists('pledge_id', $data)) {
                        print " pledge " . $this->pledge_link('id', $data['pledge_id']);
                    }
                } elseif (array_key_exists('circumstance', $res)) {
                    print "for pledge " . $this->pledge_link('id', $res['pledge_id']);
                }
                if ($data['scope'] == "login") {
                    if (!array_key_exists('method', $data)) {
                        print "<em>Stash expired</em>";
                    } else {
                        print " " . $data['method'] . " to " . $data['url'];
                    }
                }
            } elseif (array_key_exists('lastsendattempt', $data)) {
                if ($data['ispremium'] == 't') 
                    print 'Premium ';
                print "SMS sent to $data[recipient], message
                    '$data[message]' status $data[lastsendstatus]";
            } elseif (array_key_exists('commentposted', $data)) {
                $comment_email = $data['email'];
                if (!$comment_email)
                    $comment_email = $data['author_email'];
                print "$data[name] &lt;$comment_email&gt; commented on " .
                    $this->pledge_link('id', $data['pledge_id']) . " saying
                '$data[text]'";
            } elseif (array_key_exists('whenqueued', $data)) {
                print "Local alert to ". htmlspecialchars($data['email']) .
                  " " . htmlspecialchars($data['alertpostcode']) . " " .
                  " for pledge " . $this->pledge_link('id', $data['pledge_id']);
            } else {
                print_r($data);
            }
            print '<br>';
            }
            print "</dd>\n";
        }
        print '</dl>';
        print "</div>";
    }

    function pledge_link($type, $data, $title='') {
        if ($type == 'id') {
            if (!array_key_exists($data, $this->pledgeref)) {
                return "DELETED";
            }
            $ref = $this->pledgeref[$data];
        }
        else 
            $ref = $data;
        if (!$title) 
            $title = $ref;
        $str = '<a href="' . OPTION_BASE_URL . '/' . $ref . '">' .
            htmlspecialchars($title) . '</a>';
        if (!$this->ref)
            $str .= ' (<a href="?page=pb&amp;pledge='.$ref.'">admin</a>' .  ' | ' . ' <a href="?page=pblatest&amp;ref='.$ref.'">timeline</a>'. ')';
        return $str;
    }

    function display($self_link) {
        db_connect();
        $this->show_latest_changes();
    }
}

class ADMIN_PAGE_PB_ABUSEREPORTS {
    function ADMIN_PAGE_PB_ABUSEREPORTS() {
        $this->id = 'pbabusereports';
        $this->navname = _('Abuse reports');
    }

    function display($self_link) {
        db_connect();

        if (array_key_exists('prev_url', $_POST)) {
            $do_discard = false;
            if (get_http_var('discardReports'))
                $do_discard = true;
            foreach ($_POST as $k => $v) {
                if ($do_discard && preg_match('/^ar_([1-9]\d*)$/', $k, $a))
                    db_query('delete from abusereport where id = ?', $a[1]);
                // Don't think delete pledge is safe as a button here
                # if (preg_match('/^delete_(comment|pledge|signer)_([1-9]\d*)$/', $k, $a)) {
                if (preg_match('/^delete_(comment)_([1-9]\d*)$/', $k, $a)) {
                    if ($a[1] == 'comment') {
                        pledge_delete_comment($a[2]);
                    } else if ($a[1] == 'pledge') {
                        // pledge_delete_pledge($a[2]);
                    } else {
                        // pledge_delete_signer($a[2]);
                    }
                    print "<em>Deleted "
                            . htmlspecialchars($a[1])
                            . " #" . htmlspecialchars($a[2]) . "</em><br>";
                }
            }

            db_commit();

        }

        $this->showlist($self_link);
    }

    function showlist($self_link) {
        global $q_what;
        importparams(
                array('what',       '/^(comment|pledge|signer)$/',      '',     'comment')
            );

        print "<p><strong>See reports on:</strong> ";

        $ww = array('comment', 'signer', 'pledge');
        $i = 0;
        foreach ($ww as $w) {
            if ($w != $q_what)
                print "<a href=\"$self_link&amp;what=$w\">";
            print "${w}s ("
                    . db_getOne('select count(id) from abusereport where what = ?', $w)
                    . ")";
            if ($w != $q_what)
                print "</a>";
            if ($i < sizeof($ww) - 1)
                print " | ";
            ++$i;
        }

        $this->do_one_list($self_link, $q_what);
    }

    function do_one_list($self_link, $what) {

        $old_id = null;
        $q = db_query('select id, what_id, reason, ipaddr, extract(epoch from whenreported) as epoch from abusereport where what = ? order by what_id, whenreported desc', $what);

        if (db_num_rows($q) > 0) {

            print '<form name="discardreportsform" method="POST" action="'.$this->self_link.'"><input type="hidden" name="prev_url" value="'
                        . htmlspecialchars($self_link) . '">';
            print '
    <p><input type="submit" name="discardReports" value="Discard selected abuse reports"></p>
    <table class="abusereporttable">
    ';
            while (list($id, $what_id, $reason, $ipaddr, $t) = db_fetch_row($q)) {
                if ($what_id !== $old_id) {
                
                    /* XXX should group by pledge and then by signer/comment, but
                     * can't be arsed... */
                    print '<tr style="background-color: #eee;"><td colspan="4">';

                    if ($what == 'pledge')
                        $pledge_id = $what_id;
                    elseif ($what == 'signer')
                        $pledge_id = db_getRow('select pledge_id from signers where id = ?', $what_id);
                    elseif ($what == 'comment')
                        $pledge_id = db_getOne('select pledge_id from comment where id = ?', $what_id);
                    
                    $pledge = db_getRow('
                                    select *,
                                        extract(epoch from creationtime) as createdepoch,
                                        extract(epoch from date) as deadlineepoch
                                    from pledges
                                    where id = ?', $pledge_id);
                        
                    /* Info on the pledge. Print for all categories. */
                    print '<table>';
                    print '<tr><td><b>Pledge:</b> ';
                    $pledge_obj = new Pledge($pledge);
                    print $pledge_obj->h_sentence(array());
                    print ' <a href="'.$pledge_obj->url_main().'">'.$pledge_obj->ref().'</a> ';
                    print '<a href="?page=pb&amp;pledge='.$pledge_obj->ref().'">(admin)</a> ';
                            
                    /* Print signer/comment details under pledge. */
                    if ($what == 'signer') {
                        $signer = db_getRow('
                                        select signers.*, person.email,
                                            extract(epoch from signtime) as epoch
                                        from signers
                                        left join person on signers.person_id = person.id
                                        where signers.id = ?', $what_id);

                        print '</td></tr>';
                        print '<tr class="break"><td><b>Signer:</b> '
                                . (is_null($signer['name'])
                                        ? "<em>not known</em>"
                                        : htmlspecialchars($signer['name']))
                                . ' ';

                        if (!is_null($signer['email']))
                            print '<a href="mailto:'
                                    . htmlspecialchars($signer['email'])
                                    . '">'
                                    . htmlspecialchars($signer['email'])
                                    . '</a> ';

                        if (!is_null($signer['mobile']))
                            print htmlspecialchars($signer['mobile']);

                        print '<b>Signed at:</b> ' . date('Y-m-d H:m', $signer['epoch']);
                    } elseif ($what == 'comment') {
                        $comment = db_getRow('
                                        select id,
                                            extract(epoch from whenposted)
                                                as whenposted,
                                            text, name, website
                                        from comment
                                        where id = ?', $what_id);

                        print '</td></tr>';
                        print '<tr class="break">';
                        print '<td><b>Comment:</b> ';
                        comments_show_one($comment, true);
                    }

                    if ($what == "comment") {
                        print " <input type=\"submit\" name=\"delete_${what}_${what_id}\" value=\"Delete this $what\">";
                    }
                    print '</td></tr>';
                    print '</table>';
                    $old_id = $what_id;
                }

                print '<tr><td>'
                            . '<input type="checkbox" name="ar_' . $id . '" value="1">'
                        . '</td><td><b>Abuse report:</b> '
                            . date('Y-m-d H:i', $t)
                            . ' from '
                            . $ipaddr
                        . '</td><td><b>Reason: </b>'
                            . htmlspecialchars($reason)
                        . '</td></tr>';
            }

            print '</table>';
            print '<p><input type="submit" name="discardReports" value="' . _('Discard selected abuse reports') . '"></form>';
        } else {
            print '<p>No abuse reports of this type.</p>';
        }
    }
}

?>
