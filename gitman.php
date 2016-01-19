<html>
<head>
<style type="text/css">
    .table
    {
        display: table;
        width: 100%;
        border-spacing: 2px;
    }
    .title
    {
        display: table-caption;
        text-align: center;
        font-weight: bold;
        font-size: larger;
    }
    .heading
    {
        display: table-row;
        font-weight: bold;
        text-align: center;
    }
    .row
    {
        display: table-row;
    }
    .cell
    {
        display: table-cell;
        border: solid;
        border-width: thin;
        padding-left: 5px;
        padding-right: 5px;
	width: 33%;
	background-color: #00f0f0;
    }
    .bigcell
    {
        display: table-cell;
        border: solid;
        border-width: thin;
	width=100%;
    }
</style>

</head>
<body bgcolor=white>

<?php
if (isset($_GET['token']))
  {
  $token=$_GET['token'];
  $org=$_GET['org'];
  }
else
  {
  $token=$argv[1];
  $org=$argv[2];
  }


unset ($repos);
unset ($assoc);


# Get Subscription ('cause subscription in filters doesn't work)
$watchedrepo = array();
$query = `curl -s -H "Authorization: token $token" https://api.github.com/user/subscriptio
ns?per_page=100\&direction\=asc`;
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

$assoc = array();

$len = 0;
$page=1;
do {
$query = `curl -s -H "Authorization: token $token" https://api.github.com/orgs/$org/issues
\?filter\=all\&state\=all\&direction\=asc\&per_page\=100\&page=$page`;
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

# Aggregate info
foreach($list as $key => $issue)
  {
  $labels="";
  foreach ((array)($issue->labels) as $label)
    {
    $labels.="<span style='background-color:#".$label->color."'>[".$label->name."]</span> 
";
    }
  unset($issue->labels);
 
  $urlrepo = explode('/',$issue->url);
  $reponame = $urlrepo[count($urlrepo)-3]; 
  if (!isset($watchedrepo[$reponame]) || !strpos($labels,"idea")===false || !strpos($label
s,"invalid")===false )
    {
    unset($list[$key]);
    continue;
    }

  $issue->labels=$labels;

  if (isset($issue->milestone))
    {
    $mil[$issue->milestone->id][]=$issue;
    }
  else
    {
    $nomil[] = $issue;
    }
  if (isset($issue->assignee))
    {
    $human[$issue->assignee->login][]=$issue;
    }
    else
    {
    $nohuman[]=$issue;
    }
  }
unset($issue);
date_default_timezone_set("America/Santiago");
$today = new DateTime('now');
?>
<h1>Milestones</h1>

<?php
unset($open);unset($close);
print "<table border=0 width=100%><tr align=left>\n";
foreach($mil as $id => $m)
  {
  # First milestone has the information of all tickets
  $reponames = explode("/",$m[0]->url);
  $reponame = $reponames[count($reponames)-3];
  if ($m[0]->milestone->state == "open")
    {
    print "<td align=left width=auto>";
    $milesurl = $m[0]->milestone->html_url;
    $milesurl = explode('/',$milesurl);
    array_pop($milesurl);
    $milesurl = implode('/',$milesurl);
    print "[<a href=$milesurl>$reponame</a>] ".$m[0]->milestone->title.": \n";
    print "</td><td alignt=left>";
    print "Created at ".substr($m[0]->milestone->created_at,0,10);
    print "</td><td align=left>";
    print "<table style='border:1px solid gray; width:200px;text-align: left;vertical-alig
n:middle;padding:0px;table-layout: fixed;'><tr>\n";
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

?>
<hr>
<h1>Tickets per user</h1>

<div class=table>
<div class=row>
<?php

unset($open);unset($close);

$i=0;

foreach($human as $login => $issues)
  {
  $open = array();
  $close = array();
  foreach ($issues as $issue)
    {
    if ($issue->state == "open") $open[]=$issue;
    else $close[]=$issue;
    }
  $i++;
  print "<div class=cell>\n";
  print "<tt>$login</tt>\n<br>\n";
  print "<li>open:";
  foreach ($open as $o)
    {
    unset($spanL);
    $antes = new DateTime(substr($o->created_at,0,10));
    $diff = $today->diff($antes);
    $dias = $diff->days+0;
    if ($dias > 14)
      {
      $spanL="<span style='background-color:red'>";
      }
    elseif ($dias > 7)
      {
      $spanL="<span style='background-color:yellow'>";
      }
    if(isset($spanL)) $spanR="</span>";
    else $spanL="";

    print  $spanL."[<a href=".$o->html_url." title=\"".$o->title."\">".$o->number."</a>]".
$spanR;
    unset($antes);unset($diff);
    }
  print "</li><li>closed:\n";
  foreach ($close as $o)
    {
    unset($spanL);
    $despues = new DateTime(substr($o->closed_at,0,10));
    $antes = new DateTime(substr($o->created_at,0,10));
    $diff = $despues->diff($antes);
    $dias = $diff->days+0;
    if ($dias > 14)
      {
      $spanL="<span style='background-color:red'>";
      }
    elseif ($dias > 7)
      {
      $spanL="<span style='background-color:yellow'>";
      }
    if(isset($spanL)) $spanR="</span>";
    else $spanL="";

    print $spanL."[<a href=".$o->html_url." title=\"".$o->title."\">".$o->number."</a>]$sp
anR";

    unset($antes);unset($diff);unset($despues);
    }

  print "</li></div><br>\n";
  unset($open);unset($close);
  if ($i % 3 == 0) print "</div><div class=row>\n";
  }

?>
</div>
</div>

<h1>Non-user Tickets</h1>

<?php

unset($open);unset($close);
$open = array();
$close = array();

foreach ($nohuman as $issue)
  {
  if ($issue->state == "open") $open[]=$issue;
  else $close[]=$issue;
  }
print "<li>open:";
foreach ($open as $o)
  {
  unset($spanL);
  $antes = new DateTime(substr($o->created_at,0,10));
  $diff = $today->diff($antes);
  $dias = $diff->days+0;
  if ($dias > 14)
    {
    $spanL="<span style='background-color:red'>";
    }
  elseif ($dias > 7)
    {
    $spanL="<span style='background-color:yellow'>";
    }
  if(isset($spanL)) $spanR="</span>";
  print  $spanL."[<a href=".$o->html_url." title=\"".$o->title."\">".$o->number."</a>]".$s
panR;
  unset($antes);unset($diff);
  }
print "</li><li>closed:\n";
foreach ($close as $o)
  {
  unset($spanL);
  $despues = new DateTime(substr($o->closed_at,0,10));
  $antes = new DateTime(substr($o->created_at,0,10));
  $diff = $despues->diff($antes);
  $dias = $diff->days+0;
  if ($dias > 14)
    {
    $spanL="<span style='background-color:red'>";
    }
  elseif ($dias > 7)
    {
    $spanL="<span style='background-color:yellow'>";
    }
  if(isset($spanL)) $spanR="</span>";
  else $spanL="";

  print $spanL."[<a href=".$o->html_url." title=\"".$o->title."\">".$o->number."</a>]$span
R";

  unset($antes);unset($diff);unset($despues);
  }

print "</li></div><br>\n";
unset($open);unset($close);

?>
</div>
<h1>10 older issues</h1>

<?php
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
  if ($issue->state=="open" && !isset($issue->pull_request) && (strpos($labels,"idea")===f
alse)) 
    {
    print "<a href=".$issue->html_url.">[$i]</a> (".substr($issue->created_at,0,10).") ".$
issue->repository->name." issue ".$issue->closed_at." ".$issue->title." ".$issue->labels."
<br>\n";
    if($i++ >= 10) break;
    }
  else
    {
    }
  }


?>
