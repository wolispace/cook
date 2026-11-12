const ZOOM_KEY = 'cook_zoom';
const PAGE_SIZE = 20;
const pageId = extractPageIdFromUrl();

let allSections = [];   // full list fetched from server
let renderedCount = 0;  // how many we've put in the DOM
let isLoading = false;
let observer = null;

// The show starts here
document.addEventListener('DOMContentLoaded', async () => {

  const parsedUrl = new URL(window.location);
  // Extract parameters mapped in manifest.json
  const sharedTitle = parsedUrl.searchParams.get('title');
  const sharedText = parsedUrl.searchParams.get('text');
  const sharedUrl = parsedUrl.searchParams.get('url');

  if (sharedUrl) {
    console.log("Received a shared URL:", sharedUrl);
  }

  const saved = localStorage.getItem(ZOOM_KEY);
  if (saved) document.documentElement.style.fontSize = saved + 'px';

  await initSections();
});

// ---- Infinite scroll / lazy render ----------------------------------------

async function initSections() {
  const container = document.querySelector('.sections');
  if (!container) return;

  const filter = container.dataset.filter || '';

  // Fetch the full (lightweight) sections list from the server
  const params = { action: 'sections', filter };
  const json = await getJson(params);
  allSections = json.sections || [];
  renderedCount = 0;

  // Render first batch
  renderNextBatch(container);

  // Set up sentinel for infinite scroll
  setupIntersectionObserver(container);
}

function renderNextBatch(container) {
  if (isLoading) return;
  const slice = allSections.slice(renderedCount, renderedCount + PAGE_SIZE);
  if (slice.length === 0) return;

  isLoading = true;
  slice.forEach(section => {
    container.insertAdjacentHTML('beforeend', buildSectionHtml(section));
  });
  renderedCount += slice.length;
  isLoading = false;
}

function buildSectionHtml(s) {
  const tags = (s.tags || []).map(t => `<span>${t}</span>`).join('');
  const tagsHtml = tags ? `<div class="tags">${tags}</div>` : '<div class="tags"></div>';
  return `
<section class="section">
  <div class="row">
    <div class="imagebox">
      <img src="${escHtml(s.image)}" loading="lazy" />
    </div>
    <div class="thingbox">
      <div>
        <a href="${escHtml(s.url)}" target="_blank">
          <span class="title">${escHtml(s.title)}</span>
        </a>
      </div>
      <div>${escHtml(s.description)}</div>
    </div>
  </div>
  ${tagsHtml}
  <div class="edit" onclick="editSection('${escHtml(s.id)}')"><i class="fas fa-pencil"></i></div>
</section>`;
}

function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function setupIntersectionObserver(container) {
  // Create a small sentinel div at the bottom
  const sentinel = document.createElement('div');
  sentinel.className = 'scroll-sentinel';
  container.after(sentinel);

  observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        renderNextBatch(container);
        // Stop observing if we've rendered everything
        if (renderedCount >= allSections.length) {
          observer.disconnect();
          sentinel.remove();
        }
      }
    });
  }, { rootMargin: '200px' });

  observer.observe(sentinel);
}

// ---- Existing functions ----------------------------------------------------

function extractPageIdFromUrl() {
  let queryParam = window.location.href;
  return queryParam.replace(window.location.origin, '').replace('/cook/', '').replace('?', '');
}

async function editSection(section) {
  const buttons = [
    {
      caption: "Delete",
      onclick: "deleteForm()",
    },
    {
      caption: "Save",
      onclick: "saveForm()",
    },
    {
      caption: "Cancel",
      onclick: "closeDialog()",
    }
  ];
  const params = { action: 'load', type: 'section', page: pageId, section: section, buttons: buttons }
  const json = await getJson(params);
  showDialog(json.html);
}

async function editPage() {
  const buttons = [
    {
      caption: "Delete",
      onclick: "deleteForm()",
    },
    {
      caption: "Save",
      onclick: "saveForm()",
    },
    {
      caption: "Cancel",
      onclick: "closeDialog()",
    }
  ];
  const params = { action: 'load', type: 'page', page: pageId, buttons: buttons }
  const json = await getJson(params);
  showDialog(json.html);
}

async function addThing(section) {
  const thingCount = document.querySelectorAll('.thing').length;
  const params = { action: 'load', type: 'thing', page: pageId, section: section, id: thingCount };
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
  const params = { action: 'delete', page: pageId, section: section };
  const json = await getJson(params);
  window.location.reload();
}

function zoom(amount) {
  const current = parseFloat(getComputedStyle(document.documentElement).fontSize);
  const newSize = Math.min(Math.max(current + amount, 12), 32);
  document.documentElement.style.fontSize = newSize + 'px';
  localStorage.setItem(ZOOM_KEY, newSize);
  window.scrollTo({ top: document.body.scrollHeight });
}

function filter(tag) {
  const TAGS = 'cook_tags';
  const stored = localStorage.getItem(TAGS) ?? '';
  const tags = stored ? stored.split(',') : [];

  const idx = tags.indexOf(tag);
  if (idx > -1) {
    tags.splice(idx, 1);
  } else {
    tags.push(tag);
  }

  const csv = tags.join(',');
  localStorage.setItem(TAGS, csv);

  window.location.href = csv ? `./?filter=${csv}` : './';
}
