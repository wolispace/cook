<?php
// load global app settings
$app = loadJson('_data.json');
$app["webRoot"] = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);

$j = $_REQUEST['j'] ?? null;
if ($j !== null || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $j ? json_decode($j, true) : $_REQUEST;
    outputJson(handleData($data));
    exit;
}
// regular page load, e.g. /?about-us
$urlKeys = array_keys($_GET);

$params = pageParams(["page" => $urlKeys[0] ?? '']);
print buildHtml($params);


// --------------------------------------------------- 

function pageParams($params) {
  global $app;
  // page is the first param ?about etc.. Default page of blank is the 'home' page 
  $params['page'] = $params['page'] ?? '';
  // merge url params into our request
  $params = array_merge($params, $_REQUEST); 
  switch ($params['page']) {
    case '': // no page so get homepage content
      $params['content'] = formatClubList(getClubs());
      $params['template'] = 'home';
      $params['name'] = $app['name'];
      $params['footer'] = '<div class="button" onclick="newClub()">Add your club</div>'; 
      break;
    // new case for each special page   
    default: // page defined so get its content
      $params['template'] = $params['template'] ?? 'page';
      $params = getContent($params); 
      $footerParams = ['template'=> 'footer', 'webroot' => $app['webRoot']];
      $params['footer'] = buildHtml($footerParams);
  };

  $params['version'] = rand(10, 99999);
  return $params;
}

function getContent($params) {
  global $app;
  $file = "{$app['clubFolder']}{$params['page']}.json";
  $data = loadJson($file);
  $data = array_merge($params, $data);
  if (empty($data)) {
    $params['name'] = 'New club';
    $params['acronym'] = 'NC';
    $params['content'] = formatSections($data);
    $params['pagebuttons'] = pageButtons($data);
  } else {
    $params['name'] = $data['name'];
    $params['sectionCaption'] = $data['sectionCaption'];
    $params['thingCaption'] = $data['thingCaption'];    
    $params['content'] = formatSections($data);
    $params['pagebuttons'] = pageButtons($data);
  }
  return $params;
}

// $all: 0 = from today onwards, 1 = start of this year, 2+ = years back
function formatSections($data) {
  global $app;
  $html = '<div class="sections">';
  $startDate = date('Ymd');
  if (!empty($data['all'])) {
    // go back to the start of this year
    $startDate = date('Y0101');
    // go beck in years (if 2+ passed then we go back 1 year etc..)
    $startDate = $startDate - ( ( (int)$data['all'] - 1) * 10000 );
  }
  // loop through the events and build them from html templates
  foreach($data['sections'] as $sectionId => $section) {
    if ($sectionId <= $startDate) {
      continue;
    }
    $section['sectionId'] = $sectionId;
    $section['template'] = 'section';
    $section = buildDateBits($sectionId, $section);
    $section['things'] = buildUrls($section['things']);
    $section['things'] = buildSubHtml($section['things'], 'thing');
    $section['host'] = getSelected($data['members'], $section['host']);
    $section['location'] = getSelected($data['locations'], $section['location']);
    $section['location'] = !empty($section['alt']) ? $section['alt'] : $section['location'];
    $section = stripKeys($section, 'dateformat,members');
    $html .= buildHtml($section);
  }

  $html .= '</div>';
  return $html;
}

function pageButtons($data) {
  $html = '';
  $buttons = [];
  $params = [
  'template' => 'page_button',
  'sectionCaption' => $data['sectionCaption'],
  'all' => $data['all']
  ]; 

  if (empty($params['all'])) {
    $params['onclick'] = 'editSection()'; 
    $params['caption'] = "Add a new {$params['sectionCaption']}";
    $html .= buildHtml($params);
    $params['onclick'] = 'showAll(1)'; 
    $params['caption'] = "Show whole year";
    $html .= buildHtml($params);
  } else {
    $params['all']++;
    $params['onclick'] = "showAll({$params['all']})"; 
    $params['caption'] = "Show previous year";
    $html .= buildHtml($params);
    $params['onclick'] = "showAll(0)"; 
    $params['caption'] = "Show current only";
    $html .= buildHtml($params);
  } 
    return $html;
}

function getSelected($list, $selected) {
  if ($selected === '') {
    return '';
  }
  return $list[$selected];
}

function buildDateBits($ymd, $section) {
  $date = DateTime::createFromFormat('Ymd', $ymd);
  $dateBits = explode(' ', $date->format('D j M'));
  $section['weekday'] = $dateBits[0];
  $section['monthday'] = $dateBits[1];
  $section['month'] = $dateBits[2];  
  return $section;
}


function buildUrls($things) {
  global $app;
  foreach($things as $key => &$thing) {
    if (empty($thing['url'])) {
      continue;
    }
    $thing['template'] = 'url';
    $thing['url'] = buildHtml($thing);
  }
  return $things;
}

function buildSubHtml($list, $template) {
  global $app;
  $html = '';
  $counter = 0;
  foreach($list as $key => $thing) {
    $thing['template'] = $template;
    $thing['key'] = $key;
    $thing['id'] = $counter++;
    
    $html .= buildHtml($thing);
  }
  return $html;
}
    
function buildHtml($params) {
  $file = buildTemplatePath($params['template']);
  $html = file_get_contents($file);
  $html = renderTemplate($html, $params);
  // remove all left-over {{vars}}
  $html = preg_replace('/{{\w+}}/', '', $html);
  return $html;
}

function buildTemplatePath($name) {
  global $app;
  return "{$app['templateFolder']}{$name}.html";
}

function renderTemplate($html, $params) {
  foreach ($params as $key => $value) {
    $html = str_replace("{{{$key}}}", $value, $html);
  }
  return $html;
}

// $data is a json object with action=something and other params
// this always returns a json object
function handleData($data) {
  // logIt("handling json data: " . json_encode($data));
  $json = array();
  switch ($data['action'] ?? '') {
    case 'clubList':
      $json = getClubs();
      break;
    case 'load':
      $json = loadDataForEditing($data);
      break;
    case 'save': // builder editing will be form post to handle images and lots of content
      $json = saveDataFromEditing($data);
      break;
    case 'delete':
      $json = deleteSomething($data);
      break;     
  };
  return $json;
}

function getClubs() {
  global $app;
  $clubs = [];
  $files = glob("{$app['clubFolder']}*.json");
  foreach($files as $file) {
    $clubData = loadJson($file);
    $clubId = str_replace([$app['clubFolder'] , '.json'], '', $file);
    $clubs[$clubId] = ["name" => $clubData['name'], "tagline" => $clubData['tagline']];
  }
  return $clubs;
}

function formatClubList($clubs) {
  $clubList = 'Club list: <ul>';
  $clubList .= buildSubHtml($clubs, 'club_list');
  $clubList .= '</ul>';
  return $clubList;
}

function loadDataForEditing($params) {
 global $app;
  $file = "{$app['clubFolder']}{$params['page']}.json";
  $data = loadJson($file);
  if (empty($data)) {
    $data = [];
  }
  $html = '';
  switch ($params['type']) {
    case 'page': 
      $data['template'] = 'edit_page';
      $data['page'] = $params['page'];
      $data['buttons'] = buildSubHtml($params['buttons'], 'dialog_button');
      $data = buildRecurring($data);
      $data = formatPage($data, $params);
      logIt(json_encode($data));
      $html = buildHtml($data);
      break;
    case 'section':
      if (!empty($params['section']) && !empty($data['sections'][$params['section']])) {
        $section = $data['sections'][$params['section']];
      } else {
        $section = [];
        // default section details like next date
        $sectionKeys = array_keys($data['sections']);
        $highestSection = array_pop($sectionKeys);
        $highestSection = empty($highestSection) ? date('Ymd') : $highestSection;
        $params['section'] = nextDate($highestSection, $data);
      }
      $section['section'] = $params['section'];       
      $section = formatSection($section, $data, $params);
      $html = buildHtml($section);
      break;
    case 'thing': 
      $data['template'] = 'edit_thing';
      $section = $data['sections'][$params['section']];
      $data['id'] = $params['id'];
      $data = stripKeys($data, 'dateformat,members,sections,locations');
      $html = buildHtml($data);
      break;
    default: // nothing to get
      $html = 'Nothing to do';
  };

  return ['html'=> $html];
}

function formatPage($data, $params) {
  $data['members'] = implode("\n", $data['members']);
  $data['locations'] = implode("\n", $data['locations']);
  return $data;
}

function formatSection($section, $data, $params) {
  $section = array_merge($section, $params);
  $section['thingCaption'] = $data['thingCaption'];
  $section['date'] = fromYmd($params['section']);
  $section['hosts'] = buildOptions($data['members'], $section['host']); 
  $section['locations'] = buildOptions($data['locations'], $section['location']); 
  $section['buttons'] = buildSubHtml($params['buttons'], 'dialog_button');
  $section['things'] = buildSubHtml($section['things'], 'edit_thing');
  $section['template'] = 'edit_section';
  $section = stripKeys($section, 'dateformat,members,sections');
  return $section;
}

function buildOptions($list, $current) {
  $html = '<option></option>';
  $counter = 0;
  foreach( $list as $item) {
    $selected = "{$counter}" === $current ? 'selected' : '';
    $html .= "<option value=\"{$counter}\" {$selected}>{$item}</option>";
    $counter++;
  }
  return $html;
}

function buildButtons($buttons) {
  $html = '';
  foreach($buttons as $button) {
    $button['template'] = 'dialog_button';
    $html .= buildHtml($button);
  }
  return $html;
}

function saveDataFromEditing($params) {
  global $app;
  $file = "{$app['clubFolder']}{$params['page']}.json";
  $data = loadJson($file);
  logIt("Saving " . json_encode($params));
  if (empty($data)) {
    $params['page'] = buildHandle($params);
    $file = "{$app['clubFolder']}{$params['page']}.json";
    $data = [];
  }
  switch ($params['type']) {
  case 'page':
    $data = array_merge($data, $params);
    $data['members'] = encodeList($data['members']);
    $data['locations'] = encodeList($data['locations']);
    $data = stripKeys($data,"{$params['page']},test,action,type,page");
    break;
  case 'section':
    $oldSectionId = $params['section'];
    $newSectionId = toYmd($params['date']);
    // remove old section
    unset($data['sections'][$oldSectionId]);
    $params['things'] = removeBlankThings($params['things']);
    $params = stripKeys($params,"{$params['page']},test,action,type,page,section");
    // add new section
    $data['sections'][$newSectionId] = $params;
    break;
  default:
    return ["status" => "nothing to save"];;
  };

  saveJson($data, $file);
  return ["status" => "ok"];
}

function buildHandle($params) {
  global $app;
  $page = preg_replace("\W", '', $params['page']);
  if (empty($params['oldpage'])) {
    // new so make sure its not clashing..
    $counter = 0;
    $file = "{$app['clubFolder']}{$page}.json";
    while (file_exists($file)) {
      $counter++;
      $file = "{$app['clubFolder']}{$page}{$counter}.json";
    }
    return "{$page}{$counter}";
  } else {
    // no change
    return $params['oldpage'];
  }
}

function encodeList($list) {
 return explode("\n", str_replace("\r", "", $list));
}

function removeBlankThings($things) {
  $newThings = [];
  foreach ($things as $thing) {
    if (!empty($thing['title'])) {
      $newThings[] = $thing;
    }
  }
  return $newThings;
}

// remove keys we dont want to save
function stripKeys($data, $keys) {
  return array_diff_key($data, array_flip(explode(',', $keys)));
}

function deleteSomething($params) {
  global $app;
  $file = "{$app['clubFolder']}{$params['page']}.json";
  $data = loadJson($file);
  if (empty($data)) {
    return ['error' => 'Club not found'];
  }
  unset($data['sections'][$params['section']]);
  saveJson($data, $file);
  logIt("deleted {$params['page']} {$params['section']}");
  return ["status" => "ok"];
}

// utilities ----------------------------------

function toYmd($str) {
  foreach (['d M Y', 'd/m/Y', 'd-m-Y'] as $format) {
    $date = DateTime::createFromFormat($format, $str);
    if ($date) return $date->format('Ymd');
  }
}

function fromYmd($ymd) {
  $date = DateTime::createFromFormat('Ymd', $ymd);
  return $date->format('d M Y');
}

function logIt($str) {
  $dateTime = date('Ymd H:i:s');
  file_put_contents('_log.txt', "{$dateTime},{$_SERVER['REMOTE_ADDR']},{$str}\n", FILE_APPEND | LOCK_EX);
}

function cleanString($str) {
  $str = preg_replace('/[^a-z0-9-_]/i', '', $str);
  return substr($str, 0, 20);
}

function outputJson($data) {
  header('Content-Type: application/json');
  echo json_encode($data);
}

function loadJson($file) {
  return json_decode(file_get_contents($file), true);
}

function saveJson($data, $file) {
  logIt('save ' . $file . ' ' . json_encode($data));
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function buildRecurring($data) {
  $recurList = buildRecurringLists();
  $data['nths'] = buildOptions($recurList['nths'], $data['nth']);
  $data['days'] = buildOptions($recurList['days'], $data['day']);
  $data['everys'] = buildOptions($recurList['everys'], $data['every']);
  $data['units'] = buildOptions($recurList['units'], $data['unit']);
  return $data;
}
function buildRecurringLists() {
  return [
    'nths' => array_merge(['First','Second','Third','Last'], range(1, 31)),
    'days' => ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
    'everys' => array_merge(['every'], range(1, 12)),
    'units' => ['week','month'],
  ];
}

// $params contains {"part1": "thrid", "part2": "Wednesday", "part3": "1", "part4": "month"}
function nextDate($ymd, $params) {
  $lastDate = DateTime::createFromFormat('Ymd', $ymd);
  $days = ['Sunday','Monday','Tuesday','Wednesday','Thusday','Friday','Saturday'];
  $recur = buildRecurringLists();

  $nth = $params['nth']; // e.g. 0=1st, 1=2nd, 2=3rd, 3=last, 4=1, 5=2 up to 31
  $targetDay = $params['day']; // e.g. 0 = Sun, 1 = Mon etc  or blank
  $every = (int)$params['every']; // 1, 2
  $every = ($every < 1) ? 1 : $every; // minimum always 1 = every week or every month 
  $unit = $params['unit']; // 'week' or 'month'

  $date = clone $lastDate;

  if ($nth > 3) {
    // Fixed day-of-month (e.g. 15 | _ | every 1 | month)
    $day = (int)$nth - 3;
    $date->modify("+{$every} {$unit}s");
    $date->setDate((int)$date->format('Y'), (int)$date->format('n'), $day);
    return $date->format('Ymd');
  }

  $weekday = $days[$targetDay];
  $ordinal = isset($ordinals[$nth]) ? $ordinals[$nth] : (int)$nth;

  // Weekly: day 
  if ($unit == 0) { 
    // e.g. _ | sat | every 2 | week
    $date->modify("+{$every} weeks");
    return $date->format('Ymd');
  }

  // Monthly: nth weekday of month
  $month = (int)$date->format('n');
  $year  = (int)$date->format('Y');

  if ($nth === 3) { // last -  last Monday of 
    $candidate = new DateTime("last {$weekday} of {$date->format('F Y')}");
  } else {
    $candidate = new DateTime("first day of {$year}-{$month}");
    $counter = 0;
    while ($candidate->format('w') != $targetDay) {
      $candidate->modify('+1 day');
      // logIt("A {$candidate->format('w D d M Y')} ? {$targetDay}");
    }
    $candidate->modify('+' . ($nth) . ' weeks');
  }
  // logIt("a1 {$candidate->format('w D d M Y')} <= {$lastDate->format('w D d M Y')}");
  // If that date hasn't passed yet, use it — otherwise advance by $every months
  if ($candidate <= $lastDate) {
    $date->modify("+{$every} months");
    $month = (int)$date->format('n');
    $year  = (int)$date->format('Y');
    if ($nth === 3) { // last
      $candidate = new DateTime("last {$weekday} of {$date->format('F Y')}");
    } else {
      $candidate = new DateTime("first day of {$year}-{$month}");
      while ($candidate->format('w') != $targetDay) {
        $candidate->modify('+1 day');
        // logIt("B {$candidate->format('w D d M Y')} ? {$targetDay}");
      }
      $candidate->modify('+' . ($nth) . ' weeks');
      // logIt("C {$candidate->format('w D d M Y')} + {$nth} weeks ?why?");

    }
  }

  return $candidate->format('Ymd');
}

