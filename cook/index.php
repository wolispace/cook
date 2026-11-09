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
  // no pages, use all on home page
  $params['template'] = 'home';
  $params = getContent($params); 
  $footerParams = ['template'=> 'footer', 'webroot' => $app['webRoot']];
  $params['footer'] = buildHtml($footerParams);
  $params['name'] = $app['name'];
  $params['version'] = rand(10, 99999);
  return $params;
}

function getContent($params) {
  global $app;
  $params['content'] = formatSections($app);
  $params['pagebuttons'] = pageButtons($app);
  return $params;
}

function getUrlFilters() {
  $filter = [];
  if (isset($_REQUEST['filter'])) {
    $filter = explode(',', $_REQUEST['filter']);
  }
  return $filter;
}

function formatSections($data) {
  global $app;
  // work out which filters are enabled
  $html = getFilterTags($data);
  $html .= '<div class="sections">';
  // loop through the sections and build them from html templates
  // if any of the $section['tags'] match the filter, then show it
  $filter = getUrlFilters();

  foreach($data['sections'] as $sectionId => $section) {
    if (count($filter) > 0 && count(array_intersect($filter, $section['tags'])) !== count($filter)) {
      continue;
    }

    $section['sectionId'] = $sectionId;
    $section['template'] = 'section';
    $section['tags'] = empty($section['tags']) ? '' : buildTags($section['tags'], 'tag');
    $html .= buildHtml($section);
  }
  $html .= pageButtons($app);
  $html .= '</div>';
  return $html;
}

function getFilterTags($data) {
  $html = '<div class="filtertags">';
  $params = [];
  $filter = getUrlFilters();
  
  $html .= buildEditTags($filter, 'filter');
  $html .= '</div>';
  return $html;
}

function pageButtons($data) {
  $html = '';
  $buttons = [];
  $params = [
  'template' => 'page_button',
  'sectionCaption' => $data['sectionCaption']
  ];

  $params['onclick'] = 'editSection()'; 
  $params['caption'] = "Add a new {$params['sectionCaption']}";
  $html .= buildHtml($params);
  
  return $html;
}

function getSelected($list, $selected) {
  if ($selected === '') {
    return '';
  }
  return $list[$selected];
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
  if (!is_array($params)) {
    logIt("renderTemplate: params is not an array: " . json_encode($params));
    return $html;
  }
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
    case 'pageList':
      $json = getPages();
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

function loadDataForEditing($params) {
  global $app;
  $data = $app;
  $html = '';
  logIt("Loading " . json_encode($params));
  
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
      $section = $data['sections'][$params['section']];
      $section['section'] = $params['section'];
      $params['buttons'] = buildButtons($params['buttons']);
      $params['edittags'] = buildEditTags($section['tags']);   
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
  $data['tags'] = implode("\n", $data['tags']);
  return $data;
}

function formatSection($section, $data, $params) {
  $section = array_merge($section, $params);
  $section['template'] = 'edit_section';

  $section['tags'] = buildTags($section['tags']);
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

function buildTags($tags) {
  $html = '';
  if (!is_array($tags) || count($tags) < 1) return $html;
  $params = [];
  $params['template'] = 'tag';
  foreach($tags as $tag) {
    $params['tag'] = $tag;
    $html .= buildHtml($params);
  }
  return $html;

}

function buildEditTags($selectedtags, $click = '') {
  global $app;
  $html = '';
  // loop throuhg all tags from app['tags']
  $params = [];
  $params['template'] = 'edit_tag';
  foreach($app['tags'] as $tag) {
    $params['checked'] = (in_array($tag, $selectedtags)) ? 'checked' : '';
    //$params['selected'] = 'selected';
    $params['tag'] = $tag;
    $params['click'] = 'blank';
    if (!empty($click)) {
      $params['click'] = "onclick=\"filter('{$tag}')\"";
    }
    // = empty($click) ? '' : $click; // ` Y onclick="filter('{$tag}')"`;
    $html .= buildHtml($params);
  }
  return $html;
}

function saveDataFromEditing($params) {
  global $app;
  $data = $app;
  logIt("Saving " . json_encode($params));
  switch ($params['type']) {
  case 'page':
    $data = array_merge($data, $params);
    $data['members'] = encodeList($data['members']);
    $data['locations'] = encodeList($data['locations']);
    $data = stripKeys($data,"{$params['page']},test,action,type,page");
    break;
  case 'section':
    $key = $params['section'];
    if (empty($key)) {
       $key = uniqid();
    }
    // convert assoc array into string array
    $tagarray = [];
    foreach ($params['tags'] as $tag=>$value) {
      $tagarray[] = $tag;
    }
    $params['tags'] = $tagarray;
    if (!empty($params['newtags'])) {
      $newtags = explode(' ', $params['newtags']);
      $params['tags'] = array_merge($newtags, $tagarray);
      $data['tags'] = array_unique(array_merge($data['tags'], $newtags));
    }

    $params = stripKeys($params, "newtags,test,action,type,page,section");
    // add new section
    $params = handleUrl($params);
    $data['sections'][$key] = $params;

    break;
  default:
    return ["status" => "nothing to save"];;
  };

  $file = '_data.json';
  saveJson($data, $file);
  return ["status" => "ok"];
}

function buildHandle($params) {
  global $app;
  $page = preg_replace("\W", '', $params['page']);
  if (empty($params['oldpage'])) {
    // new so make sure its not clashing..
    $counter = 0;
    $file = "{$app['pageFolder']}{$page}.json";
    while (file_exists($file)) {
      $counter++;
      $file = "{$app['pageFolder']}{$page}{$counter}.json";
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
  $data = $app;
  unset($data['sections'][$params['section']]);
  $file = '_data.json';
  saveJson($data, $file);
  logIt("deleted {section {$params['section']}");
  return ["status" => "ok"];
}

function fetchUrl($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
  ]);
  $content = curl_exec($ch);
  curl_close($ch);
  return $content;
}


function matchFirst($pattern, $content) {
  return preg_match($pattern, $content, $m) ? $m[1] : null;
}

function handleUrl($params) {
  if (empty($params['url'])) return $params;
  $content = fetchUrl($params['url']);
  $params['title'] = matchFirst('/<title>(.+?)<\/title>/i', $content);
  $params['image'] = matchFirst('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\']/i', $content)
    ?: matchFirst('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\'](.*?)["\']/i', $content)
    ?: matchFirst('/"image"\s*:\s*"([^"]+)"/i', $content);
  
  return $params;
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

