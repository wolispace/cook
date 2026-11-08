const ZOOM_KEY = 'cook_zoom';
const pageId = extractPageIdFromUrl();

// The show starts here
document.addEventListener('DOMContentLoaded', async () => {

  const parsedUrl = new URL(window.location);  
  // Extract parameters mapped in manifest.json
  const sharedTitle = parsedUrl.searchParams.get('title');
  const sharedText = parsedUrl.searchParams.get('text');
  const sharedUrl = parsedUrl.searchParams.get('url');

  if (sharedUrl) {
    console.log("Received a shared URL:", sharedUrl);
    // Process the URL (e.g., save it to a list, display it, etc.)
  }

  const saved = localStorage.getItem(ZOOM_KEY);
  if (saved) document.documentElement.style.fontSize = saved + 'px';
});

function extractPageIdFromUrl() {
  let queryParam = window.location.href;
  return queryParam.replace(window.location.origin, '').replace('/cook/', '').replace('?', '');
}

async function editSection(section) {
  const buttons = [
    {caption: "Delete", 
      onclick: "deleteForm()",
    },
    {caption: "Save", 
      onclick: "saveForm()",
    },
    {caption: "Cancel", 
      onclick: "closeDialog()",
    }
  ];
  const params = {action: 'load', type: 'section', page: pageId, section: section, buttons: buttons}
  const json = await getJson(params);
  showDialog(json.html);
}

async function editPage() {
  const buttons = [
    {caption: "Delete", 
      onclick: "deleteForm()",
    },
    {caption: "Save", 
      onclick: "saveForm()",
    },
    {caption: "Cancel", 
      onclick: "closeDialog()",
    }
  ];
  const params = {action: 'load', type: 'page', page: pageId, buttons: buttons}
  const json = await getJson(params);
  showDialog(json.html);
}

async function addThing(section) {
  const thingCount = document.querySelectorAll('.thing').length;
  const params = {action: 'load', type: 'thing', page: pageId, section: section, id: thingCount};
  const json = await getJson(params);
  const form = document.querySelector('form');
  form.insertAdjacentHTML('beforeend', json.html);
}

function showAll(yearsBack) {
  const base = window.location.href.split('&')[0];
  window.location = yearsBack >= 1 ? `${base}&all=${yearsBack}` : base;
}

function newPage() {
  editPage();
}

async function getJson(params) {
    moveOverlay(4);
    const response = await fetch(`?j=${JSON.stringify(params)}`);
    const result = await response.json();
    moveOverlay(2);
    return result;
}

function showDialog(html) {
  window.scrollTo({ top: 0, behavior: 'smooth' });
  document.querySelector('.overlay').classList.add('visible');
  const dialog = document.querySelector('.dialog');

  dialog.innerHTML = `<div class="dialog-close" 
  onclick="closeDialog()"><i class="fas fa-close"></i></div>
  ${html}`;
  dialog.classList.add('visible');
}

function closeDialog() {
  document.querySelector('.overlay').classList.remove('visible');
  document.querySelector('.dialog').classList.remove('visible');
}

function moveOverlay(zIndex) {
  // show overlay with a loading spinner or something
  const overlay = document.querySelector('.overlay');
  overlay.style.zIndex = zIndex;
}

async function saveForm() {
  moveOverlay(4);
  const form = document.querySelector('form');
  const formData = new FormData(form);
  const response = await fetch('', { method: 'POST', body: formData });
  const result = await response.json();
  window.location.reload();
}

async function deleteForm() {
  if (!confirm('Are you sure you want to delete this?')) {
    return;
  }
  const section = document.querySelector('#section').value;
  const params = {action: 'delete', page: pageId, section: section};
  const json = await getJson(params);
  window.location.reload();
}



function zoom(amount) {
  const current = parseFloat(getComputedStyle(document.documentElement).fontSize);
  const newSize = Math.min(Math.max(current + amount, 12), 32);
  document.documentElement.style.fontSize = newSize + 'px';
  localStorage.setItem(ZOOM_KEY, newSize);
  window.scrollTo({top: document.body.scrollHeight});
}
