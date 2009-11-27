<?php
require_once "init.php";

$people = array();
$orphans = array();
$everyone = array();

$tree = new MozillaTreeAdapter($everyone);

$search = ldap_search(
  $ldapconn,
  $tree->conf["ldap_search_base"],
  $tree->conf["ldap_search_filter"],
  $tree->conf["ldap_search_attributes"]
);
$data = ldap_get_entries($ldapconn, $search);

$tree_view_roots = $tree->roots;

foreach ($data as $person) {
  $mail = $person['mail'][0];
  $everyone[$mail] = $tree->process_entry($person);

  // If a user has a manager, try to find their place in the tree.
  if (!empty($person["manager"][0])) {
    $manager = explode(',', $person["manager"][0]);
    $manager = explode('=', $manager[0]);
    $manager = $manager[1];

    if (empty($people[$manager])) {
      $people[$manager] = array($mail);
    } else {
      $people[$manager][] = $mail;
    }
  } elseif (!empty($mail) && !in_array($mail, $tree_view_roots)) {
    // Person is an orphan.
    $orphans[] = $mail;
  }
}

function make_tree($level, $root, $nodes=NULL) {
  global $people;
  global $everyone;
  global $tree;

  print "\n". $tree->format_item($everyone, $root, ($nodes == NULL));

  if (is_array($nodes)) {
    print "\n<ul>";
    usort($nodes, array($tree, "sort_items"));
    foreach ($nodes as $node) {
      if (!empty($people[$node])) {
        make_tree($level + 1, $node, $people[$node]);
      } else {
        make_tree($level + 1, $node);
      }
    }
    print "\n</ul>";
  }
}

require_once "templates/header.php";
?>

<div id="page">

<div id="orgchart" class="tree">
<ul>
<?php
  foreach ($tree_view_roots as $root) {
    if (!isset($people[$root])) {
      make_tree(0, $root);
    } else {
      make_tree(0, $root, $people[$root]);
    }
  }
?>
</ul>
</div>
<br />
<div id="orphans" class="tree">
<ul>
  <li>People who need to set their manager</li>
  <ul>
<?php
foreach ($orphans as $orphan) {
  print "\n". $tree->format_item($everyone, $orphan, TRUE);
}
?>
  </ul>
</ul>
</div>

<div id="person">
</div>

</div>

<script type="text/javascript" src="js/view-tree.js"></script>

<?php require_once "templates/footer.php"; ?>
