<html>
<head>
<title>GitMan</title>
<link rel="stylesheet" href="gitman.css">
</head>
<body bgcolor=white>

<?php

if (isset($_GET['token']) || isset($_GET['org']))
  {
  if (empty($_GET['token']) || empty($_GET['org'])) 
    {
    print "usage: URL?token=YOURTOKEN&org=YOURORG\n";
    exit;
    }
  $token=htmlspecialchars($_GET['token'], ENT_QUOTES);
  $org=htmlspecialchars($_GET['org'], ENT_QUOTES);
  }
else
  {
  if (sizeof($argv) != 3) 
    {
    print "usage: URL?token=YOURTOKEN&org=YOURORG\n";
    exit;
    }
  ini_set('display_errors', 'On');
  error_reporting(E_ALL);
  $token=$argv[1];
  $org=$argv[2];
  }


date_default_timezone_set("America/Santiago");
$today = new DateTime('now');

function print_issue_number_linked($issue,$t_yellow,$t_red,$closed=false)
{
global $today;
unset($spanL);
$created_at = new DateTime(substr($issue->created_at,0,10));
if ($closed)
  {
  $closed_at = new DateTime(substr($issue->closed_at,0,10));
  $diff = $closed_at->diff($created_at);
  }
else
  {
  $diff = $today->diff($created_at);
  }

$days = $diff->days+0;
if ($days > $t_red) $spanL="<span style='background-color:red'>";
elseif ($days > $t_yellow) $spanL="<span style='background-color:yellow'>";
if(isset($spanL)) $spanR="</span>";
else {$spanL="";$spanR="";}

print $spanL."[<a href=".$issue->html_url." title=\"".$issue->title."\">".$issue->number."</a>]$spanR";
unset($created_at);unset($diff);unset($closed_at);
}
 
unset ($repos);
unset ($assoc);

if (!isset($_GET['token'])) print "[0] checking permissions\n";

# Test permissions

$ok = `curl -I -H "Authorization: token $token" https://api.github.com/user/subscriptions?per_page=100\&direction\=asc 2>/dev/null | head -n 1 | cut -d$' ' -f2`;
$ok2 = `curl -I -H "Authorization: token $token" https://api.github.com/orgs/$org/issues 2>/dev/null | head -n 1 | cut -d$' ' -f2`;

if ($ok != 200 || $ok2 != 200) 
  {
  print "Error connecting to GitHub, check credentials\n";
  exit;
  }

if (!isset($_GET['token'])) print "[1] reading watched repositories\n";
# Get Subscription ('cause subscription in filters doesn't work)
$watchedrepo = array();

$query = `curl -s -H "Authorization: token $token" https://api.github.com/user/subscriptions?per_page=100\&direction\=asc`;

$query = json_decode($query);
foreach ($query as $wrep)
  {
  if (strpos($org,$wrep->full_name) == 0)
    {
    $awrep=explode('/',$wrep->full_name);
    $wrep->full_name=$awrep[1];
    unset($awrep);
    $watchedrepo[$wrep->full_name]=true;
    }
  }
# read JSON

if (!isset($_GET['token'])) print "[2] reading and filtering issues\n";

$assoc = array();

$len = 0;
$page=1;
do {
$query = `curl -s -H "Authorization: token $token" https://api.github.com/orgs/$org/issues\?filter\=all\&state\=all\&direction\=asc\&per_page\=100\&page=$page`;
$page++;
$len = strlen($query);
$query = json_decode($query,true);
$assoc = array_merge_recursive($query,$assoc);
  } while($len > 10);

$repos = json_encode($assoc);
unset($query);
unset($assoc);

$repos = preg_replace('/,\s*([\]}])/m', '$1', $repos);

# Put all the info in the list

$list = json_decode($repos);

unset($mil);unset($nomil);unset($human);unset($nohuman);

$mil = array();
$nomil = array();
$human = array();
$nohuman = array();
$todo = array();
$pull_request = array();

if (!isset($_GET['token'])) print "[3] aggregating info\n";

# Aggregate info
foreach($list as $key => $issue)
  {
  $labels="";
  foreach ((array)($issue->labels) as $label)
    {
    $labels.="<span style='background-color:#".$label->color."'>[".$label->name."]</span>";
    }
  unset($issue->labels);
  $issue->labels=$labels;
 
  $urlrepo = explode('/',$issue->url);
  $reponame = $urlrepo[count($urlrepo)-3]; 
  $issue->reponame=$reponame;

  if (!isset($watchedrepo[$reponame]) || !strpos($labels,"idea")===false || !strpos($labels,"invalid")===false || !strpos($labels,"todo")===false)
    {
    unset($list[$key]);
    continue;
    }

  if (isset($issue->milestone))
    {
    $mil[$issue->milestone->id][]=$issue;
    }
  else
    {
    $nomil[] = $issue;
    }

  if ((!strpos($labels,"todo"))===false)
    {
    $todo[$reponame][]=$issue;
    $reponames[$reponame]=true;
    }
  elseif (isset($issue->pull_request))
    {
    $pull_request[$issue->reponame][]=$issue;
    $reponames[$reponame]=true;
    }
  elseif (isset($issue->assignee))
    {
    $human[$issue->assignee->login][]=$issue;
    }
    else
    {
    $nohuman[$issue->reponame][]=$issue;
    $reponames[$reponame]=true;
    }
  }
unset($issue);

if (!isset($_GET['token'])) print "[4] print milestones\n";

?>
<h1>Milestones</h1>

<?php
unset($open);unset($close);
print "<table border=0 width=100%><tr align=left>\n";
foreach($mil as $id => $m)
  {
  # First milestone has the information of all tickets
  $reponame = $m[0]->reponame;
  if ($m[0]->milestone->state == "open")
    {
    print "<td align=left width=auto>";
    $milesurl = $m[0]->milestone->html_url;
    $milesurl = explode('/',$milesurl);
    array_pop($milesurl);
    $milesurl = implode('/',$milesurl);
    print "[<a href=$milesurl>".$reponame."</a>] ".$m[0]->milestone->title.": \n";
    print "</td><td alignt=left>";
    print "Created at ".substr($m[0]->milestone->created_at,0,10);
    print "</td><td align=left>";
    print "<table style='border:1px solid gray; width:200px;text-align: left;vertical-align:middle;padding:0px;table-layout: fixed;'><tr>\n";
    $created_at=new DateTime(substr($m[0]->milestone->created_at,0,10));
    $due_on=new DateTime(substr($m[0]->milestone->due_on,0,10));
    $duration=($due_on->diff($created_at)->days)+0;
    $barcolor="";
    $percent=0;
    $days=0;
    if ($today < $due_on)
       {
       $days=($today->diff($created_at)->days)+0;
       $percent=floor(($days*100)/$duration);
       $barcolor="green";
       }
    else
       {
       $days=($today->diff($due_on)->days)+0;
       $percent=floor(($days*100)/$duration);
       $barcolor="red";
       }
    if ($percent==0) $percent=1;
    if ($percent>99) $percent=99;
    $days=($today->diff($due_on)->days)+0;
    print "<td width=".$percent."px bgcolor=$barcolor align=left border=1>&nbsp;</td>";
    print "<td width=".(100-$percent)."px bgcolor=white align=left border=1>&nbsp;</td>";
    unset($created_at);unset($due_on); 
    print "</td></tr></table>";
    print "</td><td>$days days";
    print "</td><td>Open:</td>";
    print "<td align=left>".$m[0]->milestone->open_issues."</td>";
    print "<td>Closed:</td>";
    print "<td align=left>".$m[0]->milestone->closed_issues."</td>";
    print "</tr>\n";
    }
  }
print "</table>\n";

if (!isset($_GET['token'])) print "[5] print tickets per user\n";
?>
<hr>
<h1>Tickets per user</h1>

<div class=table>
<div class=row>
<?php

unset($open);unset($close);

$i=0;

$rname="";

foreach($human as $login => $issues)
  {
  $arr = array();
  foreach ($issues as $issue)
    {
    if (isset($issue->pull_request)) continue;
    if ($issue->state == "open") $arr[$issue->reponame]['open'][]=$issue;
    else $arr[$issue->reponame]['closed'][]=$issue;
    }

  $i++;
  print "<div class=cell>\n";
  print "<tt>$login</tt>\n<hr>\n";

  foreach ($arr as $name => $ids)
    {
    print "<div class=row>\n";
    print "<div class=divreponame>$name</div>\n";
    print "<div class=divissue>";
    print "<div class=divopen>O:";
    if (!empty($ids['open']))
    foreach ($ids['open'] as $ticket)
      {
      print_issue_number_linked($ticket,7,14);
      }
    print "</div><div class=divclosed>C:";

    if (!empty($ids['closed']))
    foreach ($ids['closed'] as $ticket)
      {
      print_issue_number_linked($ticket,7,14,true);
      }
    print "</div>\n";
    print "</div>\n";
    print "</div>\n";
    }

  print "</div>\n";
  unset($arr);
  if ($i % 4 == 0) print "</div><div class=row>\n";
  }

?>
</div>
</div>

<hr>
<h1>Repo View</h1>

<div class=table>
<div class=row>
<?php

unset($open);unset($close);

$i=0;

foreach ($reponames as $reponame => $value)
  {
  if (!isset($pull_request[$reponame]) && !isset($nohuman[$reponame])) continue;

  unset($pr);unset($nh);
  $pr = array();
  $nh = array();

  if (isset($pull_request[$reponame])) 
    {
    foreach($pull_request[$reponame] as $issue)
      {
      if ($issue->state == "open") $pr['open'][]=$issue;
      else $pr['closed'][]=$issue;
      }
    }
  if (isset($nohuman[$reponame])) 
    {
    foreach ($nohuman[$reponame] as $issue)
      {
      if ($issue->state == "open") $nh['open'][]=$issue;
      else $nh['closed'][]=$issue;
      }
    }

  print "<div class=cell>\n";
  print "<tt>$reponame</tt>\n<hr>\n";

  if (isset($pull_request[$issue->reponame]))
    {
    print "Pull Requests:<br>\n";

    print "<div class=row>\n";
    print "<div class=divreponame></div>\n";
    print "<div class=divissue>";
    print "<div class=divopen>O:";

    if (!empty($pr['open']))
      foreach ($pr['open'] as $ticket)
        {
        print_issue_number_linked($ticket,7,14);
        }
    print "</div><div class=divclosed>C:";

    if (!empty($pr['closed']))
      foreach ($pr['closed'] as $ticket)
        {
        print_issue_number_linked($ticket,7,14,true);
        }
    print "</div>\n";
    print "</div>\n";
    print "</div>\n";
    }

  if (isset($nohuman[$reponame]))
    {
    if (isset($pull_request[$issue->reponame])) print "<hr>\n";

    print "Unassigned issues:<br>\n";

    print "<div class=row>\n";
    print "<div class=divreponame></div>\n";
    print "<div class=divissue>";
    print "<div class=divopen>O:";

    foreach ($nh['open'] as $o)
      {
      print_issue_number_linked($o,7,14);
      }
    print "</div><div class=divclosed>C:";

    foreach ($nh['closed'] as $o)
      {
      print_issue_number_linked($o,7,14,true);
      }
    print "</div>\n";
    print "</div>\n";
    print "</div>\n";

    }
  print "</div>\n"; # del cell

  $i++;
  if (($i % 4) == 0) print "</div><div class=row>\n";
  }

?>
</div>
</div>
<hr>

<h1>10 older issues</h1>

<?php

if (!isset($_GET['token'])) print "[7] print ten older users\n";

$i=1;

function cmp($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return (substr($a->created_at,0,10) < substr($b->created_at,0,10)) ? -1 : 1;
}

uasort($list,'cmp');

foreach($list as $issue)
  {
  if ($issue->state=="open" && !isset($issue->pull_request)) 
    {
    print "<a href=".$issue->html_url.">[$i]</a> (".substr($issue->created_at,0,10).") ".$issue->repository->name." issue ".$issue->closed_at." ".$issue->title." ".$issue->labels."
<br>\n";
    if($i++ >= 10) break;
    }
  else
    {
    }
  }


?>
