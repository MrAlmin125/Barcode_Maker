// ============================================================
// PHP-BRIDGED GLOBALS
// ============================================================
const isAdminUser = document.body.getAttribute('data-admin') === 'true';
const loggedInUsername = document.body.getAttribute('data-username') || '';
const loggedInUserId = parseInt(document.body.getAttribute('data-user-id') || '0', 10);

// ============================================================
// LOADING POPUP
// ============================================================
let loadingTimerInterval = null;
let loadingAutoCloseTimeout = null;
let isLoadingDataComplete = false;

function showLoadingPopup(){
  var popup = document.getElementById('loading-popup');
  if(popup){ popup.style.display = 'flex'; }
  startLoadingTimer(5);
}
function updateLoadingMessage(msg, pct){
  var m = document.getElementById('loading-message');
  var p = document.getElementById('loading-progress');
  if(m) m.innerHTML = msg;
  if(p && pct !== undefined) p.style.width = pct + '%';
}
function startLoadingTimer(seconds){
  var left = seconds;
  var span = document.getElementById('timer-seconds-loading');
  if(loadingTimerInterval) clearInterval(loadingTimerInterval);
  if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout);
  loadingTimerInterval = setInterval(function(){
    left--;
    if(span) span.textContent = left;
    if(left <= 0){ clearInterval(loadingTimerInterval); loadingTimerInterval = null; if(!isLoadingDataComplete) forceCloseLoadingPopup(); }
  }, 1000);
  loadingAutoCloseTimeout = setTimeout(function(){ if(!isLoadingDataComplete) forceCloseLoadingPopup(); }, seconds * 1000);
}
function forceCloseLoadingPopup(){
  var popup = document.getElementById('loading-popup');
  if(popup){ popup.style.opacity='0'; popup.style.transition='opacity 0.3s ease'; setTimeout(function(){ popup.style.display='none'; },300); }
  if(loadingTimerInterval) clearInterval(loadingTimerInterval);
  if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout);
}
function closeLoadingPopupManually(){ if(loadingTimerInterval) clearInterval(loadingTimerInterval); if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout); forceCloseLoadingPopup(); }
function closeLoadingPopupAndWait(){ if(loadingTimerInterval) clearInterval(loadingTimerInterval); if(loadingAutoCloseTimeout) clearTimeout(loadingAutoCloseTimeout); forceCloseLoadingPopup(); }
function closeLoadingPopup(){ isLoadingDataComplete = true; forceCloseLoadingPopup(); }

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMobileNav();
    }
});

document.addEventListener('touchmove', function(e) {
    if (document.getElementById('mobile-nav-drawer').classList.contains('open')) {
        e.preventDefault();
    }
}, { passive: false });

// ============================================================
// GLOBAL STATE
// ============================================================
const API_BASE = 'api/';
let USE_PHP = false;
let barcodes = [];
let printQueue = JSON.parse(localStorage.getItem('bc-queue') || '[]');
let shopName = 'الضيافة';
let logoUrl = 'image/logo.png';
let defaultPrefix = '8801234';
let selectedLabelSize = localStorage.getItem('selected_label_size') || '6x4';
let currentEditId = null;
let currentLanguage = localStorage.getItem('language') || 'ar';
let categories = [];
let selectedCategoryFilter = '';
let selectedCurrency = localStorage.getItem('bc-currency') || 'ريال';
let labelLanguage = localStorage.getItem('label_language') || 'default';
let priceNumeralSystem = localStorage.getItem('price_numeral_system') || 'en';
let weightNumeralSystem = localStorage.getItem('weight_numeral_system') || 'en';
let pendingDeleteId = null;
let currentPreviewCode = '';
let currentPreviewTitle = '';
let currentPreviewPrice = '';
let currentPreviewWeight = '';
let currentPreviewExpDate = '';
let usersData = [];
let pendingDeleteUserId = null;
let currentEditUserId = null;

// ============================================================
// PHP API
// ============================================================
async function phpGet(ep){ const r=await fetch(API_BASE+ep,{cache:'no-store'}); if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); }
async function phpPost(ep,data){ const r=await fetch(API_BASE+ep,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}); if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); }

// ============================================================
// INDEXEDDB
// ============================================================
const IDB_NAME='BarcodeAppDB',IDB_VER=1;
let _idb=null;
function openIDB(){return new Promise((res,rej)=>{if(_idb){res(_idb);return;}const req=indexedDB.open(IDB_NAME,IDB_VER);req.onupgradeneeded=e=>{const db=e.target.result;if(!db.objectStoreNames.contains('barcodes'))db.createObjectStore('barcodes',{keyPath:'id'});if(!db.objectStoreNames.contains('categories'))db.createObjectStore('categories',{keyPath:'name'});if(!db.objectStoreNames.contains('settings'))db.createObjectStore('settings',{keyPath:'key'});};req.onsuccess=e=>{_idb=e.target.result;res(_idb);};req.onerror=e=>rej(e.target.error);});}
async function idbGetAll(store){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readonly').objectStore(store).getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
async function idbPut(store,obj){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readwrite').objectStore(store).put(obj);r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});}
async function idbDelete(store,key){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readwrite').objectStore(store).delete(key);r.onsuccess=()=>res();r.onerror=()=>rej(r.error);});}
async function idbClear(store){const db=await openIDB();return new Promise((res,rej)=>{const r=db.transaction(store,'readwrite').objectStore(store).clear();r.onsuccess=()=>res();r.onerror=()=>rej(r.error);});}
async function idbGetSetting(key,fallback){try{const db=await openIDB();return new Promise(res=>{const r=db.transaction('settings','readonly').objectStore('settings').get(key);r.onsuccess=()=>res(r.result?r.result.value:fallback);r.onerror=()=>res(fallback);});}catch(e){return fallback;}}
async function idbSetSetting(key,value){try{await idbPut('settings',{key,value});}catch(e){}localStorage.setItem(key,value);}

// ============================================================
// DB CRUD
// ============================================================
async function dbSaveBarcode(bc){if(USE_PHP)return phpPost('save.php',bc);return idbPut('barcodes',bc);}
async function dbUpdateBarcode(bc){if(USE_PHP)return phpPost('update.php',bc);return idbPut('barcodes',bc);}
async function dbDeleteBarcode(id){if(USE_PHP)return phpPost('delete.php',{id});return idbDelete('barcodes',id);}
async function dbSaveCategory(name){if(USE_PHP)return phpPost('categories.php',{action:'add',name});return idbPut('categories',{name});}
async function dbDeleteCategory(name){if(USE_PHP)return phpPost('categories.php',{action:'delete',name});return idbDelete('categories',name);}
async function dbSaveSetting(key,value){if(USE_PHP){phpPost('settings.php',{[key]:value}).catch(()=>{});}idbSetSetting(key,value);}
async function dbSaveSettings(obj){if(USE_PHP){phpPost('settings.php',obj).catch(()=>{});}for(const[k,v]of Object.entries(obj))idbSetSetting(k,v);}

// ============================================================
// RIPPLE
// ============================================================
document.addEventListener('click',function(e){const btn=e.target.closest('.btn');if(!btn)return;const ex=btn.querySelector('.ripple');if(ex)ex.remove();const rip=document.createElement('span');rip.className='ripple';const rect=btn.getBoundingClientRect();const size=Math.max(rect.width,rect.height);rip.style.cssText=`width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;`;btn.appendChild(rip);setTimeout(()=>rip.remove(),600);});

// ============================================================
// SAUDI RIYAL SYMBOL
// ============================================================
var RIYAL_SVG_PATH = 'M750,1119 C730,1163,716,1211,711,1262 C711,1262,1136,1172,1136,1172 C1156,1127,1169,1079,1174,1028 C1174,1028,750,1119,750,1119Z M1136,901 C1136,901,1136,901,1136,901 C1156,857,1169,809,1174,758 C1174,758,843,828,843,828 C843,828,843,693,843,693 C843,693,1136,631,1136,631 C1156,587,1169,538,1174,488 C1174,488,843,558,843,558 C843,558,843,72,843,72 C793,100,748,138,711,183 C711,183,711,586,711,586 C711,586,579,614,579,614 C579,614,579,6,579,6 C528,34,483,72,447,117 C447,117,447,642,447,642 C447,642,151,705,151,705 C131,750,117,798,112,849 C112,849,447,777,447,777 C447,777,447,948,447,948 C447,948,88,1024,88,1024 C68,1068,55,1117,50,1167 C50,1167,425,1088,425,1088 C456,1081,482,1063,499,1038 C499,1038,568,936,568,936 C568,936,568,936,568,936 C575,926,579,913,579,899 C579,899,579,749,579,749 C579,749,711,721,711,721 C711,721,711,992,711,992 C711,992,1136,901,1136,901Z';
function riyalIcon(){return'<svg viewBox="0 0 1256 1256" style="width:0.95em;height:0.95em;vertical-align:-0.08em;display:inline-block;font-weight:900;" fill="currentColor" aria-label="SAR"><path d="'+RIYAL_SVG_PATH+'"/></svg>';}

function calcCheckDigit(d12){let s=0;for(let i=0;i<12;i++)s+=parseInt(d12[i])*(i%2===0?1:3);return(10-(s%10))%10;}
function validateEAN13(code){if(!/^\d{13}$/.test(code))return false;return calcCheckDigit(code.slice(0,12))===parseInt(code[12]);}
function completeEAN13(code){code=code.replace(/\D/g,'');if(code.length===12)return code+calcCheckDigit(code);if(code.length===13&&validateEAN13(code))return code;return null;}
function generateRandomEAN13(){const prefix=defaultPrefix.slice(0,7);let base=prefix;while(base.length<12)base+=Math.floor(Math.random()*10);return completeEAN13(base);}

// ============================================================
// EAN-13 SVG
// ============================================================
function generateEAN13SVG(code,opts={}){
  const{width=200,height=100,color='#000',bg='#fff',showText=true}=opts;
  const PATTERNS={L:['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'],G:['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'],R:['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100']};
  const FIRST_DIGIT=['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];
  const d=code.split('').map(Number);const pattern=FIRST_DIGIT[d[0]];
  let bars='101';for(let i=0;i<6;i++)bars+=PATTERNS[pattern[i]][d[i+1]];bars+='01010';for(let i=7;i<13;i++)bars+=PATTERNS.R[d[i]];bars+='101';
  const quietZone=7;
  const margin = width < 150 ? 4 : 20;
  const totalUnits = bars.length + quietZone * 2;
  const unitW = (width - margin) / totalUnits;
  const barHeight = height - (showText ? 24 : 6);
  const startX = (margin/2) + quietZone * unitW;
  let rects='';let x=startX;
  for(let i=0;i<bars.length;i++){
    const isGuard=(i<3||(i>=45&&i<=49)||i>=92);
    const h=isGuard?barHeight+5:barHeight;
    if(bars[i]==='1')rects+=`<rect x="${x.toFixed(2)}" y="4" width="${unitW.toFixed(2)}" height="${h}" fill="${color}"/>`;
    x+=unitW;
  }
  let textEls='';
  if(showText){
    const ty=height-4;
    const fs=Math.max(5,Math.min(10,unitW*6));
    const cx=width/2;
    textEls+=`<text x="${cx}" y="${ty}" text-anchor="middle" font-family="'OCR-B','JetBrains Mono',monospace" font-size="${fs}" fill="${color}" font-weight="500">${code}</text>`;
  }
  return`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}"><rect width="${width}" height="${height}" fill="${bg}"/>${rects}${textEls}</svg>`;
}

function formatExpDate(d){if(!d||d==='')return'';var dt=new Date(d+'T00:00:00');return String(dt.getDate()).padStart(2,'0')+'/'+String(dt.getMonth()+1).padStart(2,'0')+'/'+dt.getFullYear();}

// ============================================================
// DD/MM/YYYY <-> ISO
// ============================================================
function formatDateTyping(el){
  let digits = el.value.replace(/\D/g,'').slice(0,8);
  let out = digits;
  if(digits.length > 4) out = digits.slice(0,2)+'/'+digits.slice(2,4)+'/'+digits.slice(4);
  else if(digits.length > 2) out = digits.slice(0,2)+'/'+digits.slice(2);
  el.value = out;
}
function ddmmyyyyToISO(str){
  if(!str) return '';
  var m = str.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if(!m) return '';
  var dd=parseInt(m[1],10), mm=parseInt(m[2],10), yyyy=parseInt(m[3],10);
  if(mm<1||mm>12||dd<1||dd>31) return '';
  var iso = yyyy+'-'+String(mm).padStart(2,'0')+'-'+String(dd).padStart(2,'0');
  var d = new Date(iso+'T00:00:00');
  if(d.getFullYear()!==yyyy || d.getMonth()+1!==mm || d.getDate()!==dd) return '';
  return iso;
}
function isoToDDMMYYYY(iso){
  if(!iso) return '';
  var parts = iso.split('-');
  if(parts.length!==3) return '';
  return parts[2]+'/'+parts[1]+'/'+parts[0];
}

// ============================================================
// DUPLICATE CHECK
// ============================================================
function isDuplicateBarcode(code,excludeId){return barcodes.some(b=>b.code===code&&String(b.id)!==String(excludeId));}
function getDuplicateItem(code,excludeId){return barcodes.find(b=>b.code===code&&String(b.id)!==String(excludeId));}
function showDupWarning(cid,code,excId){const el=document.getElementById(cid);if(!el)return;const dup=getDuplicateItem(code,excId);if(!dup){el.style.display='none';el.innerHTML='';return;}const msg=currentLanguage==='ar'?`⚠️ هذا الرقم مستخدم مسبقاً في: <strong style="color:orange;">${dup.title}</strong>`:`⚠️ Already exists: <strong style="color:orange;">${dup.title}</strong>`;el.innerHTML=`<div class="dup-warning">${msg}</div>`;el.style.display='block';}
function hideDupWarning(cid){const el=document.getElementById(cid);if(el){el.style.display='none';el.innerHTML='';}}
function showInputError(inp,isErr){if(!inp)return;if(isErr){inp.classList.add('input-error','input-shake');setTimeout(()=>inp.classList.remove('input-shake'),400);}else{inp.classList.remove('input-error');}}

// ============================================================
// EXPIRED HELPERS
// ============================================================
function isExpired(bc){if(!bc.expDate||bc.expDate==='')return false;var d=new Date(bc.expDate+'T00:00:00');var now=new Date();now.setHours(0,0,0,0);return d<=now;}
function isExpiringSoon(bc){if(!bc.expDate||bc.expDate==='')return false;var d=new Date(bc.expDate+'T00:00:00');var now=new Date();now.setHours(0,0,0,0);var dl=Math.floor((d-now)/(864e5));return dl>=0&&dl<=7;}
function isExpiredOrSoon(bc){return isExpired(bc)||isExpiringSoon(bc);}
function getExpiredCount(){return barcodes.filter(b=>isExpiredOrSoon(b)).length;}
const EXPIRED_FILTER='__EXPIRED__';
const DISABLED_FILTER='__DISABLED__';

// ============================================================
// LABEL SIZES
// ============================================================
let rawSizes = localStorage.getItem('label_sizes');
let labelSizes;
if (rawSizes) {
  try {
    labelSizes = JSON.parse(rawSizes);
    delete labelSizes['4x2.5'];
    delete labelSizes['2x3.8'];
    if (Object.keys(labelSizes).length === 0) {
      labelSizes = { '6x4': {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:14,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'} };
    }
  } catch(e) {
    labelSizes = { '6x4': {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:14,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'} };
  }
} else {
  labelSizes = { '6x4': {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:14,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'} };
}
if (!labelSizes['6x4']) {
  labelSizes['6x4'] = {width:6,height:4,barcodeWidth:195,barcodeHeight:65,titleFont:14,shopFont:13,priceFont:12,weightFont:8,expFont:8,padding:5,custom:false,nameAr:'ملصق كبير',nameEn:'Large Label'};
} else {
  if (!labelSizes['6x4'].weightFont) labelSizes['6x4'].weightFont = 8;
  if (!labelSizes['6x4'].expFont) labelSizes['6x4'].expFont = 8;
  if (!labelSizes['6x4'].priceFont) labelSizes['6x4'].priceFont = 12;
  if (!labelSizes['6x4'].titleFont) labelSizes['6x4'].titleFont = 14;
  if (!labelSizes['6x4'].shopFont) labelSizes['6x4'].shopFont = 13;
}
if (selectedLabelSize === '4x2.5' || selectedLabelSize === '2x3.8' || !labelSizes[selectedLabelSize]) {
  selectedLabelSize = '6x4';
  localStorage.setItem('selected_label_size', selectedLabelSize);
}
localStorage.setItem('label_sizes', JSON.stringify(labelSizes));

// ============================================================
// LABEL HTML
// ============================================================
function resolveLabelLang(){return (labelLanguage==='default'||!labelLanguage)?currentLanguage:labelLanguage;}
function formatWeightForLabel(weight,lblLang,numSys){
  if(!weight||weight==='')return '';
  if(numSys===undefined)numSys=lblLang;
  function toAr(str){return String(str).replace(/\d/g,d=>'٠١٢٣٤٥٦٧٨٩'[d]);}
  function toEn(str){return String(str).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d));}
  var m=String(weight).match(/^([\d.]+)(\s*)(.*)$/);
  if(!m)return (numSys==='ar')?toAr(weight):toEn(weight);
  var num=m[1],sep=m[2],unitRaw=m[3];
  var numOut=(numSys==='ar')?toAr(num):toEn(num);
  var unitOut=unitRaw;
  var sepOut=sep;
  if(lblLang==='ar'&&unitRaw){
    var unitMap={'kg':'كيلو','كيلو':'كيلو','g':'جم','gm':'جم','gram':'جم','جم':'جم','ml':'مل','مل':'مل','l':'لتر','لتر':'لتر','lb':'رطل','oz':'أوقية'};
    unitOut=unitMap[unitRaw.toLowerCase().trim()]||unitRaw;
    sepOut=' ';
  }
  return numOut+sepOut+unitOut;
}

// ============================================================
// LABEL HTML — OPTIMIZED FOR TINY / 4x2.4
// ============================================================
function generateLabelHTML(barcode,productTitle,price,weight,sizeKey,expDate){
  var size=labelSizes[sizeKey];if(!size)return'<div>Error</div>';
  var lblLang=resolveLabelLang();
  var numSys=priceNumeralSystem||'en';
  function toAr(str){return String(str).replace(/\d/g,d=>'٠١٢٣٤٥٦٧٨٩'[d]);}
  function toEn(str){return String(str).replace(/[٠-٩]/g,d=>'٠١٢٣٤٥٦٧٨٩'.indexOf(d));}
  var arFont='font-family:"Tajawal","Noto Sans Arabic",Arial,sans-serif;';
  var val=(price&&price!==''&&price!=='0')?price:'';
  var displayVal=(numSys==='ar'&&val)?toAr(val):(val?toEn(val):val);
  var priceStr='';
  if(val){switch(selectedCurrency){case'SAR':priceStr=(lblLang==='ar')?displayVal+' ﷼':'SAR '+displayVal;break;case'ريال':priceStr=displayVal+' ريال';break;case'riyal_new':priceStr=riyalIcon()+' '+displayVal;break;case'BDT':priceStr='BDT '+displayVal;break;case'BDT_symbol':priceStr='৳ '+displayVal;break;default:priceStr='SAR '+displayVal;}}
  var weightStr=formatWeightForLabel(weight,lblLang,weightNumeralSystem);
  var expStr=expDate?formatExpDate(expDate):'';if(lblLang==='ar'&&expStr)expStr=toAr(expStr);
  var expLabel=(lblLang==='ar')?'انتهاء':'EXP';var expDisplay=expStr?(expLabel+': '+expStr):'';
  var expColor='#e07800';
  if(expDate&&expDate!==''){var expCheckD=new Date(expDate+'T00:00:00');var nowCheck=new Date();nowCheck.setHours(0,0,0,0);var daysLeftLabel=Math.floor((expCheckD-nowCheck)/(864e5));expColor=(daysLeftLabel<=7)?'#cc0000':'#e07800';}
  var expClass=(expColor==='#cc0000')?'exp-date-text exp-date-urgent':'exp-date-text';
  var displayTitle=(productTitle&&productTitle.trim()!=='')?productTitle:(lblLang==='ar'?'اسم المنتج':'Product Name');
  
  var shopFs = size.shopFont || 14;
  var titleFs = size.titleFont || 14;
  var priceFs = size.priceFont || 10;
  var weightFs = size.weightFont || 6;
  var expFs = size.expFont || 6;
  var labelClass = size.custom ? 'custom-label' : 'default-label';
  var svg = generateEAN13SVG(barcode, {width: size.barcodeWidth, height: size.barcodeHeight});

  var isSmallLabel = (size.width <= 4.5 || size.height <= 3.0);

  var bottomRow='';
  if(priceStr||expDisplay||weightStr){
    var rowStyle = isSmallLabel ? 'gap:1px;margin-bottom:0px;padding-bottom:0px;' : 'gap:2px;';
    var leftItem='<span class="price-text" style="font-size:'+priceFs+'px;font-weight:900;flex:1;min-width:0;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'+arFont+'">'+(priceStr||'')+'</span>';
    var midItem=expDisplay?'<span class="'+expClass+'" style="font-size:'+expFs+'px;flex:1;min-width:0;text-align:center;white-space:nowrap;direction:ltr;unicode-bidi:plaintext;overflow:hidden;text-overflow:ellipsis;'+arFont+'">'+expDisplay+'</span>':'<span style="flex:1;min-width:0;"></span>';
    var rightItem='<span class="weight-text" style="font-size:'+weightFs+'px;font-weight:700;flex:1;min-width:0;text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'+arFont+'">'+weightStr+'</span>';
    bottomRow='<div class="price-weight-row" style="display:flex;justify-content:space-between;align-items:center;width:100%;font-size:'+priceFs+'px;flex-shrink:0;'+rowStyle+'">'+leftItem+midItem+rightItem+'</div>';
  }

  var topPad = isSmallLabel ? 1 : ((size.padding||5) + 4);
  var bottomPad = isSmallLabel ? 1 : (size.padding||5);

  var containerStyle = isSmallLabel
    ? 'width:'+size.width+'cm;height:'+size.height+'cm;padding:1px;padding-top:'+topPad+'px;padding-bottom:'+bottomPad+'px;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;align-items:center;box-sizing:border-box;'
    : 'width:'+size.width+'cm;height:'+size.height+'cm;padding:'+size.padding+'px;padding-top:'+topPad+'px;padding-bottom:'+bottomPad+'px;overflow:hidden;';

  return'<div class="label-container '+labelClass+'" style="'+containerStyle+'">'
    +'<div class="shop-name-text" style="font-size:'+shopFs+'px;width:100%;text-align:center;line-height:1;overflow:hidden;'+arFont+'">'+shopName+'</div>'
    +'<div class="product-title-text" style="font-size:'+titleFs+'px !important;width:100%;text-align:center;line-height:1;overflow:hidden;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;'+arFont+'">'+displayTitle+'</div>'
    +'<div class="barcode-wrapper" style="width:100%;text-align:center;flex-shrink:0;">'+svg+'</div>'
    +bottomRow+'</div>';
}

// ============================================================
// TRANSLATIONS
// ============================================================
const translations={
  ar:{currency:'﷼',shopName:'الضيافة',subtitle:'نظام طباعة الباركود',navGenerator:'المولد',navLibrary:'باركود',navPrint:'الطباعة',navUsers:'المستخدمون',navSettings:'الإعدادات',genTitle:'إنشاء باركود',productName:'اسم المنتج *',price:'السعر',weight:'الوزن',barcodeNumber:'رقم الباركود EAN-13',barcodeHint:'12 أو 13 رقم، يتم حساب الرقم الاختباري تلقائياً',addToQueue:'إضافة إلى المكتبة',addAndPrint:'إضافة وطباعة مباشرة',previewTitle:'معاينة مباشرة',libraryTitle:'مكتبة الباركود',searchPlaceholder:'بحث بالاسم أو رقم الباركود...',printSizeTitle:'حجم الملصق',recommended:'موصى به',forSmall:'للعناصر الصغيرة',customSizeTitle:'➕ إضافة حجم مخصص',customNamePlaceholder:'الاسم (مثال: ملصق خاص)',customWidthPlaceholder:'العرض (سم)',customHeightPlaceholder:'الارتفاع (سم)',addCustom:'➕ إضافة حجم جديد',printSettings:'ℹ️ إعدادات الطباعة',printInfo1:'• كل باركود يطبع على ملصق منفصل',printInfo2:'• اضبط الطابعة على حجم الملصق المحدد',printInfo3:'• استخدم "الحجم الفعلي" في مربع حوار الطباعة',printAll:'🖨️ طباعة الكل',clearQueue:'🗑️ تفريغ القائمة',queueTitle:'قائمة الطباعة',emptyQueue:'قائمة الطباعة فارغة',editLabel:'تعديل',addLabel:'طباعة',deleteLabel:'حذف',saveChanges:'💾 حفظ التغييرات',cancel:'إلغاء',editTitle:'✏️ تعديل الباركود',valid:'باركود صالح',invalid:'❌ باركود غير صالح',productPlaceholder:'مثال: زيت زيتون ممتاز',weightPlaceholder:'500g',barcodePlaceholder:'966XXXXXXXXXX',settingsHeader:'إعدادات الرأس والمتجر',shopNameLabel:'اسم المتجر',logoUrlLabel:'رابط الشعار',logoHint:'أدخل رابط الصورة المباشر (png, jpg, svg)',logoPreview:'معاينة الشعار:',saveHeader:'💾 حفظ إعدادات الرأس',defaultSettings:'الإعدادات الافتراضية',defaultPrefixLabel:'البادئة الافتراضية',prefixHint:'تستخدم عند النقر على "إنشاء رقم عشوائي"',saveDefaults:'💾 حفظ الإعدادات الافتراضية',dataManagement:'إدارة البيانات',exportData:'📤 تصدير البيانات',importData:'📥 استيراد البيانات',deleteAll:'🗑️ حذف جميع البيانات',editProduct:'اسم المنتج',editPrice:'السعر',editWeight:'الوزن',editBarcode:'رقم الباركود',saveEdit:'💾 حفظ التغييرات',cancelEdit:'إلغاء',noBarcodes:'لا توجد باركودات',alreadyInQueue:'الباركود موجود بالفعل في القائمة',addedToQueue:'تمت الإضافة إلى قائمة الطباعة',queueCleared:'تم تفريغ القائمة',barcodeSaved:'تم حفظ الباركود',invalidBarcode:'رقم باركود غير صالح',enterProductName:'أدخل اسم المنتج',randomGenerated:'تم إنشاء باركود عشوائي',dataExported:'تم تصدير البيانات',dataImported:'تم استيراد البيانات',invalidFile:'ملف غير صالح',allDataDeleted:'تم حذف جميع البيانات',headerSaved:'تم حفظ إعدادات الرأس',defaultsSaved:'تم حفظ الإعدادات الافتراضية',barcodeUpdated:'تم تحديث الباركود بنجاح',barcodeDeleted:'تم حذف الباركود',confirmDelete:'هل تريد حذف هذا الباركود؟',confirmClearQueue:'تفريغ قائمة الطباعة بالكامل؟',confirmDeleteAll:'حذف جميع الباركودات؟ لا يمكن التراجع!',customAdded:'تم إضافة الحجم المخصص',printingStarted:'جار فتح نافذة الطباعة...',clickHint:'💡 اضغط على أي باركود للذهاب إلى صفحة الطباعة',categoriesTitle:'إدارة التصنيفات',categoryInputPlaceholder:'اسم التصنيف الجديد...',addCategoryBtn:'➕ إضافة',noCategoriesYet:'لا توجد تصنيفات بعد',categoryExists:'التصنيف موجود مسبقاً',categoryAdded:'تم إضافة التصنيف',categoryLabel:'التصنيف',noCategoryOption:'-- بدون تصنيف --',allCategories:'📋 الكل',expiredCategory:'⏰ منتهية / قريباً',currencyTitle:'إعداد العملة',currencySelectLabel:'اختر العملة / الرمز الذي يظهر على الملصق',currencySaved:'تم حفظ إعداد العملة',currencyPreviewLabel:'معاينة:',printAllInline:'طباعة الكل',expdateLabel:'تاريخ الانتهاء',expdateOptional:'(اختياري)',downloadHighRes:'تحميل PNG',duplicateBarcode:'هذا الرقم مستخدم مسبقاً!',deletePopupTitle:'تأكيد الحذف',deletePopupMsg:'هل تريد حذف هذا الباركود؟',deletePopupConfirmText:'نعم، احذف',deletePopupCancelText:'إلغاء',expiredLabel:'⏰ منتهي',logoutBtn:'تسجيل خروج',statusLabel:'حالة الباركود',statusActive:'مفعّل',statusDisabled:'معطّل',unitGram:'غرام',unitKg:'كيلوغرام',unitMl:'مل',unitLiter:'لتر',weightUnitLabel:'الوحدة',printLabelSettingsTitle:'إعدادات طباعة الملصق',labelLangSelectLabel:'لغة تخطيط الملصق المطبوع',labelLangDefaultOption:'افتراضي (Default)',labelLangArOption:'العربية',labelLangEnOption:'English',priceNumSelectLabel:'نظام أرقام السعر',priceNumArOption:'أرقام عربية (٠١٢٣٤٥٦٧٨٩)',priceNumEnOption:'أرقام إنجليزية (0123456789)',labelPreviewLabel:'معاينة الملصق:',labelLangSaved:'تم حفظ لغة الملصق',priceNumSaved:'تم حفظ نظام أرقام السعر',priceNumHint:'يتبع لغة الملصق تلقائياً، ويمكنك تغييره يدوياً هنا',weightNumSelectLabel:'نظام أرقام الوزن',weightNumArOption:'أرقام عربية (٠١٢٣٤٥٦٧٨٩)',weightNumEnOption:'أرقام إنجليزية (0123456789)',weightNumSaved:'تم حفظ نظام أرقام الوزن',weightNumHint:'يتبع لغة الملصق تلقائياً، ويمكنك تغييره يدوياً هنا',
    usersPageTitle:'إدارة المستخدمين',addUserLabel:'إضافة مستخدم جديد',addUserBtn:'إضافة',passHint:'كلمة المرور: أرقام فقط (6 أو أكثر) · المشرف يملك صلاحيات كاملة بما فيها إدارة المستخدمين',thId:'الرقم',thUsername:'المستخدم',thRole:'الصلاحية',thActions:'الإجراءات',editUserModalTitle:'تعديل المستخدم',editUserNameLabel:'اسم المستخدم',editUserPassLabel:'كلمة المرور الجديدة (6+ أرقام)',editUserRoleLabel:'الصلاحية / Role',saveUserEdit:'حفظ التغييرات',cancelUserEdit:'إلغاء',noUsersText:'لا يوجد مستخدمون بعد',errDuplicate:'اسم المستخدم مستخدم مسبقاً',errPass:'كلمة المرور يجب أن تكون 6 أرقام على الأقل',welcomeGreeting:'مرحباً',roleLabelAdmin:'مشرف',roleLabelUser:'مستخدم'
  },
  en:{currency:'SAR',shopName:'Al-Diyafa',subtitle:'Barcode Label System',navGenerator:'Generator',navLibrary:'Barcodes',navPrint:'Print',navUsers:'Users',navSettings:'Settings',genTitle:'Create Barcode',productName:'Product Name *',price:'Price',weight:'Weight',barcodeNumber:'EAN-13 Barcode Number',barcodeHint:'12 or 13 digits, check digit auto-calculated',addToQueue:'Add to Library',addAndPrint:'Add & Print Now',previewTitle:'Live Preview',libraryTitle:'Barcode Library',searchPlaceholder:'Search by name or barcode...',printSizeTitle:'Label Size',recommended:'Recommended',forSmall:'For small items',customSizeTitle:'➕ Add Custom Size',customNamePlaceholder:'Name (e.g., Custom Label)',customWidthPlaceholder:'Width (cm)',customHeightPlaceholder:'Height (cm)',addCustom:'➕ Add Custom Size',printSettings:'ℹ️ Print Settings',printInfo1:'• Each barcode prints on a separate label',printInfo2:'• Set printer to match selected label size',printInfo3:'• Use "Actual Size" in print dialog',printAll:'🖨️ Print All',clearQueue:'🗑️ Clear Queue',queueTitle:'Print Queue',emptyQueue:'Queue is empty',editLabel:'Edit',addLabel:'Print',deleteLabel:'Delete',saveChanges:'💾 Save Changes',cancel:'Cancel',editTitle:'✏️ Edit Barcode',valid:'Valid EAN-13',invalid:'❌ Invalid Barcode',productPlaceholder:'e.g., Premium Olive Oil',weightPlaceholder:'500g',barcodePlaceholder:'966XXXXXXXXXX',settingsHeader:'Header & Shop Settings',shopNameLabel:'Shop Name',logoUrlLabel:'Logo URL',logoHint:'Enter direct image URL (png, jpg, svg)',logoPreview:'Logo Preview:',saveHeader:'💾 Save Header Settings',defaultSettings:'Default Settings',defaultPrefixLabel:'Default Prefix',prefixHint:'Used when clicking Generate Random',saveDefaults:'💾 Save Default Settings',dataManagement:'Data Management',exportData:'📤 Export Data',importData:'📥 Import Data',deleteAll:'🗑️ Delete All Data',editProduct:'Product Name',editPrice:'Price',editWeight:'Weight',editBarcode:'Barcode Number',saveEdit:'💾 Save Changes',cancelEdit:'Cancel',noBarcodes:'No barcodes found',alreadyInQueue:'Already in queue',addedToQueue:'Added to print queue',queueCleared:'Queue cleared',barcodeSaved:'Barcode saved',invalidBarcode:'Invalid barcode number',enterProductName:'Enter product name',randomGenerated:'Random barcode generated',dataExported:'Data exported',dataImported:'Data imported',invalidFile:'Invalid file',allDataDeleted:'All data cleared',headerSaved:'Header settings saved',defaultsSaved:'Default settings saved',barcodeUpdated:'Barcode updated successfully',barcodeDeleted:'Barcode Deleted',confirmDelete:'Delete this Barcode?',confirmClearQueue:'Clear entire print queue?',confirmDeleteAll:'Delete ALL Barcodes? This cannot be undone.',customAdded:'Custom size added',printingStarted:'Opening print dialog...',clickHint:'💡 Click any barcode to go to print page',categoriesTitle:'Manage Categories',categoryInputPlaceholder:'New category name...',addCategoryBtn:'➕ Add',noCategoriesYet:'No categories yet',categoryExists:'Category already exists',categoryAdded:'Category added',categoryLabel:'Category',noCategoryOption:'-- No Category --',allCategories:'📋 All',expiredCategory:'⏰ Expired / Soon',currencyTitle:'Currency Settings',currencySelectLabel:'Choose the currency / symbol shown on labels',currencySaved:'Currency saved',currencyPreviewLabel:'Preview:',printAllInline:'Print All',expdateLabel:'Expiry Date',expdateOptional:'(optional)',downloadHighRes:'Download PNG',duplicateBarcode:'This barcode number already exists!',deletePopupTitle:'Confirm Delete',deletePopupMsg:'Are you sure you want to delete this barcode?',deletePopupConfirmText:'Yes, Delete',deletePopupCancelText:'Cancel',expiredLabel:'⏰ Expired',logoutBtn:'Logout',statusLabel:'Barcode Status',statusActive:'Active',statusDisabled:'Disabled',unitGram:'g',unitKg:'kg',unitMl:'ml',unitLiter:'liter',weightUnitLabel:'Unit',printLabelSettingsTitle:'Print Label Settings',labelLangSelectLabel:'Printed label layout language',labelLangDefaultOption:'Default',labelLangArOption:'Arabic',labelLangEnOption:'English',priceNumSelectLabel:'Price number system',priceNumArOption:'Arabic',priceNumEnOption:'English',labelPreviewLabel:'Label preview:',labelLangSaved:'Label language saved',priceNumSaved:'Price number system saved',priceNumHint:'Follows the label language automatically — override it manually here',weightNumSelectLabel:'Weight number system',weightNumArOption:'Arabic',weightNumEnOption:'English',weightNumSaved:'Weight number system saved',weightNumHint:'Follows the label language automatically — override it manually here',
    usersPageTitle:'User Management',addUserLabel:'Add New User',addUserBtn:'Add',passHint:'Password: digits only (6+) · Admin has full access including user management',thId:'ID',thUsername:'User',thRole:'Role',thActions:'Actions',editUserModalTitle:'Edit User',editUserNameLabel:'Username',editUserPassLabel:'New Password (6+ digits)',editUserRoleLabel:'Role / Access',saveUserEdit:'Save Changes',cancelUserEdit:'Cancel',noUsersText:'No users yet',errDuplicate:'Username already taken',errPass:'Password must be 6+ digits',welcomeGreeting:'Hello',roleLabelAdmin:'Admin',roleLabelUser:'User'
  }
};
let currentTexts = translations.ar;

// ============================================================
// USER MANAGEMENT HELPERS
// ============================================================
function toggleEye(inputId, iconId){
  var inp=document.getElementById(inputId);
  var btn=inp?inp.parentElement.querySelector('.eye-btn'):null;
  if(!inp)return;
  if(inp.type==='password'){inp.type='text';document.getElementById(iconId).innerHTML='<i class="fas fa-eye-slash"></i>';if(btn)btn.classList.add('active');}
  else{inp.type='password';document.getElementById(iconId).innerHTML='<i class="fa-regular fa-eye"></i>';if(btn)btn.classList.remove('active');}
}
function onRoleSelectChange(sel){
  if(sel.value==='1'){sel.classList.add('is-admin-role');var warn=document.getElementById('role-admin-warning');if(warn)warn.classList.add('visible');}
  else{sel.classList.remove('is-admin-role');var warn=document.getElementById('role-admin-warning');if(warn)warn.classList.remove('visible');}
}

function toggleCustomSelect(wrapId,e){
  if(e)e.stopPropagation();
  document.querySelectorAll('.custom-select-wrap.open').forEach(function(w){if(w.id!==wrapId)w.classList.remove('open');});
  var wrap=document.getElementById(wrapId);if(wrap)wrap.classList.toggle('open');
}
function selectCustomOption(wrapId,value,e){
  if(e)e.stopPropagation();
  var wrap=document.getElementById(wrapId);if(!wrap)return;
  var nativeSelect=wrap.querySelector('select');
  if(nativeSelect)nativeSelect.value=value;
  syncCustomSelectLabel(wrapId);
  wrap.classList.remove('open');
  if(nativeSelect)onRoleSelectChange(nativeSelect);
}
function syncCustomSelectLabel(wrapId){
  var wrap=document.getElementById(wrapId);if(!wrap)return;
  var nativeSelect=wrap.querySelector('select');if(!nativeSelect)return;
  var value=nativeSelect.value;
  var opt=wrap.querySelector('.custom-select-option[data-value="'+value+'"]');
  var labelEl=wrap.querySelector('.custom-select-label');
  if(opt&&labelEl)labelEl.innerHTML=opt.innerHTML;
  wrap.querySelectorAll('.custom-select-option').forEach(function(o){o.classList.toggle('selected',o.getAttribute('data-value')===value);});
  var trigger=wrap.querySelector('.custom-select-trigger');
  if(trigger)trigger.classList.toggle('is-admin-role',value==='1');
}
document.addEventListener('click',function(e){
  if(!e.target.closest('.custom-select-wrap')){document.querySelectorAll('.custom-select-wrap.open').forEach(function(w){w.classList.remove('open');});}
});
document.addEventListener('keydown',function(e){
  if(e.key==='Escape'){document.querySelectorAll('.custom-select-wrap.open').forEach(function(w){w.classList.remove('open');});}
});
function showUserAlert(msg,type){var box=document.getElementById('user-alert-box');if(!box)return;box.innerHTML='<div class="user-alert-'+type+'">'+msg+'</div>';box.style.display='block';if(type==='success')setTimeout(function(){box.style.display='none';},3500);}
function showEditAlert(msg,type){var box=document.getElementById('user-edit-alert');if(!box)return;box.innerHTML='<div class="user-alert-'+type+'">'+msg+'</div>';box.style.display='block';if(type==='success')setTimeout(function(){box.style.display='none';},2500);}
function escHtml(str){return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function renderUsersTable(){
  if(!isAdminUser)return;
  var container=document.getElementById('users-cards-container');
  var badge=document.getElementById('users-count-badge');
  if(badge)badge.textContent=usersData.length;
  if(!container)return;
  if(usersData.length===0){
    container.innerHTML='<div class="empty-state" style="padding:40px 20px;"><div style="font-size:48px;"><i class="fa-solid fa-users" style="color: rgb(255, 199, 0);"></i></div><div style="margin-top:12px;font-size:14px;color:var(--text2);">'+(currentLanguage==='ar'?'لا يوجد مستخدمون بعد':'No users yet')+'</div></div>';
    return;
  }
  var t=currentTexts;
  var rows=usersData.map(function(u){
    var safeUser=escHtml(u.username).replace(/'/g,"\\'");
    var isAdm=u.is_admin;
    var isSelf=(u.id===loggedInUserId);
    var avatarClass=isAdm?'user-avatar-admin':'user-avatar-user';
    var avatarIcon=isAdm?'<i class="fa-brands fa-amilia" style="color: rgb(255, 199, 0);"></i>':'<i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i>';
    var subText=isAdm?(currentLanguage==='ar'?'صلاحيات كاملة':'Full Access'):(currentLanguage==='ar'?'مستخدم عادي':'Standard User');
    var roleBadge=isAdm
      ?'<span class="role-badge role-badge-admin"><i class="fa-brands fa-amilia" style="color: rgb(255, 199, 0);"></i> '+(currentLanguage==='ar'?'مشرف':'Admin')+'</span>'
      :'<span class="role-badge role-badge-user"><i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i> '+(currentLanguage==='ar'?'مستخدم':'User')+'</span>';
    var selfTag=isSelf?' <span class="role-badge" style="background:var(--accent);color:#0a0a0f;">'+(currentLanguage==='ar'?'أنت':'You')+'</span>':'';
    var deleteBtn=isSelf
      ?'<button class="btn btn-danger btn-sm" disabled title="'+(currentLanguage==='ar'?'لا يمكنك حذف حسابك الخاص':"You can't delete your own account")+'" style="opacity:.4;cursor:not-allowed;">'+
          '<i class="fas fa-trash"></i> '+(currentLanguage==='ar'?'حذف':'Delete')+
        '</button>'
      :'<button class="btn btn-danger btn-sm" onclick="askDeleteUser('+u.id+',\''+safeUser+'\')">'+
          '<i class="fas fa-trash"></i> '+(currentLanguage==='ar'?'حذف':'Delete')+
        '</button>';
    return '<tr>'+
  '<td><span class="user-id-cell">#'+u.id+'</span></td>'+
  '<td><div class="user-name-cell">'+
    '<div class="user-avatar-sm '+avatarClass+'">'+avatarIcon+'</div>'+
    '<div class="user-info">'+
      '<div class="user-name-text">'+escHtml(u.username)+selfTag+'</div>'+
      '<div class="user-sub-text">'+subText+'</div>'+
    '</div>'+
  '</div></td>'+
  '<td style="text-align:center;">'+roleBadge+'</td>'+
  '<td><div class="action-btns">'+
    '<button class="btn btn-warning btn-sm" onclick="openUserEdit('+u.id+',\''+safeUser+'\','+(isAdm?'true':'false')+')">'+
      '<i class="fas fa-edit"></i> '+(currentLanguage==='ar'?'تعديل':'Edit')+
    '</button>'+
    deleteBtn+
  '</div></td>'+
'</tr>';
  }).join('');
  container.innerHTML=
    '<div class="users-table-wrap">'+
      '<table class="users-table">'+
        '<thead><tr>'+
          '<th style="width:80px;">'+t.thId+'</th>'+
          '<th>'+t.thUsername+'</th>'+
          '<th class="th-role" style="width:120px;">'+t.thRole+'</th>'+
          '<th style="width:190px;text-align:center;">'+t.thActions+'</th>'+
        '</tr></thead>'+
        '<tbody>'+rows+'</tbody>'+
      '</table>'+
    '</div>';
}

async function ajaxAddUser(){
  if(!isAdminUser)return;
  var usernameInput=document.getElementById('new-username-input');
  var passwordInput=document.getElementById('new-password-input');
  var roleSelect=document.getElementById('new-role-select');
  var username=usernameInput.value.trim();
  var password=passwordInput.value.trim();
  var isAdmin=roleSelect?roleSelect.value==='1':false;
  var lang=currentLanguage;
  showInputError(usernameInput,false);showInputError(passwordInput,false);
  if(!username){showInputError(usernameInput,true);showUserAlert('⚠️ '+(lang==='ar'?'أدخل اسم المستخدم':'Enter username'),'error');return;}
  if(!/^\d{6,}$/.test(password)){showInputError(passwordInput,true);showUserAlert('⚠️ '+(lang==='ar'?'كلمة المرور أرقام فقط (6+)':'Password must be digits only (6+)'),'error');return;}
  if(usersData.some(function(u){return u.username.toLowerCase()===username.toLowerCase();})){showInputError(usernameInput,true);showUserAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');return;}
  try{
    var resp=await fetch('user_ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add',username:username,password:password,is_admin:isAdmin})});
    var data=await resp.json();
    if(data.success){
      usersData.unshift({id:data.id,username:username,is_admin:!!data.is_admin});
      renderUsersTable();
      usernameInput.value='';passwordInput.value='';passwordInput.type='password';
      var eyeNew=document.getElementById('eye-new');if(eyeNew)eyeNew.innerHTML='<i class="fa-regular fa-eye"></i>';
      if(roleSelect){roleSelect.value='0';onRoleSelectChange(roleSelect);syncCustomSelectLabel('new-role-wrap');}
      showUserAlert('✅ '+(lang==='ar'?'تم إضافة المستخدم بنجاح':'User added successfully')+(isAdmin?(lang==='ar'?' (مشرف)':' (Admin)'):''),'success');
    } else {
      if(data.error==='duplicate'){showInputError(usernameInput,true);showUserAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');}
      else if(data.error==='invalid_password'){showInputError(passwordInput,true);showUserAlert('⚠️ '+(lang==='ar'?'كلمة المرور 6 أرقام على الأقل':'Password must be 6 digits minimum'),'error');}
      else{showUserAlert('⚠️ '+(lang==='ar'?'خطأ في الحفظ':'Save error'),'error');}
    }
  }catch(e){showUserAlert('❌ '+(lang==='ar'?'خطأ في الاتصال':'Connection error'),'error');}
}

function openUserEdit(id,username,isAdminFlag){
  if(!isAdminUser)return;
  currentEditUserId=id;
  document.getElementById('edit-user-name-input').value=username;
  document.getElementById('edit-user-pass-input').value='';
  document.getElementById('edit-user-pass-input').type='password';
  document.getElementById('eye-edit').innerHTML='<i class="fa-regular fa-eye"></i>';
  var roleSelect=document.getElementById('edit-user-role-select');
  if(roleSelect){roleSelect.value=isAdminFlag?'1':'0';onRoleSelectChange(roleSelect);syncCustomSelectLabel('edit-role-wrap');}
  showInputError(document.getElementById('edit-user-name-input'),false);
  showInputError(document.getElementById('edit-user-pass-input'),false);
  var box=document.getElementById('user-edit-alert');if(box)box.style.display='none';
  document.getElementById('user-edit-modal').classList.add('open');
}
function closeUserEditModal(){
  document.getElementById('user-edit-modal').classList.remove('open');
  currentEditUserId=null;
  var warn=document.getElementById('role-admin-warning');if(warn)warn.classList.remove('visible');
}

async function ajaxEditUser(){
  if(!isAdminUser)return;
  var usernameInput=document.getElementById('edit-user-name-input');
  var passwordInput=document.getElementById('edit-user-pass-input');
  var roleSelect=document.getElementById('edit-user-role-select');
  var username=usernameInput.value.trim();
  var password=passwordInput.value.trim();
  var isAdmin=roleSelect?roleSelect.value==='1':false;
  var lang=currentLanguage;
  showInputError(usernameInput,false);showInputError(passwordInput,false);
  if(!username){showInputError(usernameInput,true);showEditAlert('⚠️ '+(lang==='ar'?'أدخل اسم المستخدم':'Enter username'),'error');return;}
  if(!/^\d{6,}$/.test(password)){showInputError(passwordInput,true);showEditAlert('⚠️ '+(lang==='ar'?'كلمة المرور أرقام فقط (6+)':'Password digits only (6+)'),'error');return;}
  if(usersData.some(function(u){return u.id!==currentEditUserId&&u.username.toLowerCase()===username.toLowerCase();})){showInputError(usernameInput,true);showEditAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');return;}
  try{
    var resp=await fetch('user_ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'edit',id:currentEditUserId,username:username,password:password,is_admin:isAdmin})});
    var data=await resp.json();
    if(data.success){
      usersData=usersData.map(function(u){return u.id===currentEditUserId?{id:u.id,username:username,is_admin:isAdmin}:u;});
      renderUsersTable();
      showEditAlert('✅ '+(lang==='ar'?'تم التحديث بنجاح':'Updated successfully'),'success');
      setTimeout(closeUserEditModal,1200);
    } else {
      if(data.error==='duplicate'){showInputError(usernameInput,true);showEditAlert('⚠️ '+(lang==='ar'?'اسم المستخدم مستخدم مسبقاً':'Username already taken'),'error');}
      else if(data.error==='invalid_password'){showInputError(passwordInput,true);showEditAlert('⚠️ '+(lang==='ar'?'كلمة المرور 6 أرقام على الأقل':'Password must be 6 digits minimum'),'error');}
      else{showEditAlert('⚠️ '+(lang==='ar'?'خطأ في التحديث':'Update error'),'error');}
    }
  }catch(e){showEditAlert('❌ '+(lang==='ar'?'خطأ في الاتصال':'Connection error'),'error');}
}

function askDeleteUser(id,username){
  if(!isAdminUser)return;
  pendingDeleteUserId=id;
  var lang=currentLanguage;
  var user=usersData.find(function(u){return u.id===id;});
  var roleLabel=user&&user.is_admin?(lang==='ar'?'مشرف':'Admin'):(lang==='ar'?'مستخدم':'User');
  document.getElementById('udel-title').textContent=lang==='ar'?'تأكيد الحذف':'Confirm Delete';
  document.getElementById('udel-msg').textContent=lang==='ar'?'هل تريد حذف هذا المستخدم؟':'Delete this user?';
  var udelIcon=(user&&user.is_admin)?'<i class="fa-brands fa-amilia" style="color: rgb(255, 199, 0);"></i> ':'<i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i> ';
  document.getElementById('udel-name').innerHTML=udelIcon+escHtml(username)+' — '+roleLabel+' (ID: '+id+')';
  document.getElementById('udel-confirm-text').textContent=lang==='ar'?'نعم، احذف':'Yes, Delete';
  document.getElementById('udel-cancel-text').textContent=lang==='ar'?'إلغاء':'Cancel';
  document.getElementById('user-delete-popup').style.display='flex';
}
function closeUserDeletePopup(){document.getElementById('user-delete-popup').style.display='none';pendingDeleteUserId=null;}
async function confirmDeleteUser(){
  if(!isAdminUser||!pendingDeleteUserId)return;
  var lang=currentLanguage;
  try{
    var resp=await fetch('user_ajax.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id:pendingDeleteUserId})});
    var data=await resp.json();
    if(data.success){
      usersData=usersData.filter(function(u){return u.id!==pendingDeleteUserId;});
      renderUsersTable();
      showUserAlert('✅ '+(lang==='ar'?'تم حذف المستخدم':'User deleted'),'success');
    } else {
      var errMsg=data.error==='cannot_delete_self'?(lang==='ar'?'لا يمكنك حذف حسابك الخاص':'Cannot delete your own account'):(lang==='ar'?'خطأ في الحذف':'Delete error');
      showUserAlert('❌ '+errMsg,'error');
    }
  }catch(e){showUserAlert('❌ '+(lang==='ar'?'خطأ في الاتصال':'Connection error'),'error');}
  closeUserDeletePopup();
}

// ============================================================
// CATEGORIES
// ============================================================
async function addCategory(){const input=document.getElementById('new-category-input');const name=input.value.trim();if(!name)return;if(categories.includes(name)){toast(currentTexts.categoryExists,'error');return;}try{await dbSaveCategory(name);categories.push(name);input.value='';renderCategoriesList();renderCategoryDropdowns();renderLibrary();toast(currentTexts.categoryAdded);}catch(e){toast('Error','error');}}
async function deleteCategory(name){try{await dbDeleteCategory(name);for(const bc of barcodes){if(bc.category===name){bc.category='';await dbUpdateBarcode(bc);}}}catch(e){}categories=categories.filter(c=>c!==name);barcodes=barcodes.map(b=>b.category===name?{...b,category:''}:b);if(selectedCategoryFilter===name)selectedCategoryFilter='';renderCategoriesList();renderCategoryDropdowns();renderLibrary();}
function renderCategoriesList(){const el=document.getElementById('categories-list');if(!el)return;if(categories.length===0){el.innerHTML=`<span style="color:var(--text2);font-size:12px">${currentTexts.noCategoriesYet}</span>`;return;}el.innerHTML=categories.map(c=>`<div class="category-chip" style="cursor:default">🏷️ ${c}<span class="chip-del" onclick="deleteCategory('${c.replace(/'/g,"\\'")}')">✕</span></div>`).join('');}
function renderCategoryDropdowns(){['m-category','edit-category'].forEach(id=>{const sel=document.getElementById(id);if(!sel)return;const cur=sel.value;sel.innerHTML=`<option value="">${currentTexts.noCategoryOption}</option>`;categories.forEach(c=>{const opt=document.createElement('option');opt.value=c;opt.textContent=c;sel.appendChild(opt);});sel.value=cur;});}
function renderLibraryCategoryFilter(){
  const bar=document.getElementById('lib-category-filter');if(!bar)return;
  var allCount=barcodes.length;var expCount=getExpiredCount();var disabledCount=barcodes.filter(b=>b.disabled===true).length;
  var html='<div class="category-chip'+(selectedCategoryFilter===''?' active':'')+'" onclick="filterByCategory(\'\')">'+(currentTexts.allCategories||'📋 الكل')+' <span class="badge-count" style="font-size:10px;padding:0 5px;">'+allCount+'</span></div>';
  html+='<div class="category-chip expired-chip'+(selectedCategoryFilter===EXPIRED_FILTER?' active':'')+'" onclick="filterByCategory(\''+EXPIRED_FILTER+'\')">'+(currentTexts.expiredCategory||'⏰ منتهية')+' <span class="badge-count-red" style="font-size:10px;padding:0 5px;">'+expCount+'</span></div>';
  html+='<div class="category-chip disabled-chip'+(selectedCategoryFilter===DISABLED_FILTER?' active':'')+'" onclick="filterByCategory(\''+DISABLED_FILTER+'\')">'+(currentLanguage==='ar'?'⛔ معطّلة':'⛔ Disabled')+' <span class="badge-count" style="font-size:10px;padding:0 5px;background:rgba(100,100,120,0.25);color:#888;">'+disabledCount+'</span></div>';
  categories.slice().sort((a,b)=>a.localeCompare(b,undefined,{sensitivity:'base'})).forEach(c=>{var count=barcodes.filter(b=>b.category===c).length;var safeC=c.replace(/\\/g,'\\\\').replace(/'/g,"\\'");html+='<div class="category-chip'+(selectedCategoryFilter===c?' active':'')+'" onclick="filterByCategory(\''+safeC+'\')">'+'🏷️ '+c+' <span class="badge-count" style="font-size:10px;padding:0 5px;">'+count+'</span></div>';});
  bar.innerHTML=html;
}
function filterByCategory(cat){selectedCategoryFilter=cat;renderLibraryCategoryFilter();renderLibrary();}

// ============================================================
// WEIGHT UNIT DROPDOWN
// ============================================================
function renderWeightUnitOptions(){
  const t=currentTexts;
  const optionsHtml=
    '<option value="g">'+(t.unitGram||'g')+'</option>'+
    '<option value="kg">'+(t.unitKg||'kg')+'</option>'+
    '<option value="ml">'+(t.unitMl||'ml')+'</option>'+
    '<option value="l">'+(t.unitLiter||'liter')+'</option>';
  [ 'm-weight-unit', 'edit-weight-unit' ].forEach(function(id){
    const sel=document.getElementById(id);
    if(!sel)return;
    const cur=sel.value;
    sel.innerHTML=optionsHtml;
    sel.value=cur||'g';
  });
}

// ============================================================
// CURRENCY
// ============================================================
function applyNumeralSystem(val){if(val===null||val===undefined||val==='')return val;const sys=priceNumeralSystem||'en';if(sys==='ar')return String(val).replace(/\d/g,d=>'٠١٢٣٤٥٦٧٨٩'[d]);return String(val).replace(/[٠-٩]/g,d=>'٠١٢٣৪৫৬৭৮৯'.indexOf(d));}
function formatCurrency(price,wrapBadge){const raw=(price&&price!==''&&price!=='0')?price:null;if(!raw)return'';const val=applyNumeralSystem(raw);let str='';switch(selectedCurrency){case'SAR':str=`SAR ${val}`;break;case'ريال':str=`${val} ريال`;break;case'riyal_new':str=riyalIcon()+` ${val}`;break;case'BDT':str=`BDT ${val}`;break;case'BDT_symbol':str=`৳ ${val}`;break;default:str=`SAR ${val}`;}return wrapBadge?`<span class="badge badge-green">💰 ${str}</span>`:str;}
async function saveCurrencySetting(){const sel=document.getElementById('currency-select');if(!sel)return;selectedCurrency=sel.value;await dbSaveSetting('bc-currency',selectedCurrency);updateCurrencyPreview();updatePreviewFromManual();renderLibrary();renderPrintQueue();toast(currentTexts.currencySaved);}
function updateCurrencyPreview(){const el=document.getElementById('currency-preview-text');if(!el)return;const sel=document.getElementById('currency-select');if(sel)sel.value=selectedCurrency;el.innerHTML=formatCurrency('99.00',false)||'SAR 99.00';}
async function saveLabelLanguageSetting(){const sel=document.getElementById('label-language-select');if(!sel)return;labelLanguage=sel.value;localStorage.setItem('label_language',labelLanguage);
  const resolved=resolveLabelLang();
  priceNumeralSystem=resolved;localStorage.setItem('price_numeral_system',priceNumeralSystem);
  weightNumeralSystem=resolved;localStorage.setItem('weight_numeral_system',weightNumeralSystem);
  const numSel=document.getElementById('price-numeral-select');if(numSel)numSel.value=priceNumeralSystem;
  const wSel=document.getElementById('weight-numeral-select');if(wSel)wSel.value=weightNumeralSystem;
  await dbSaveSetting('label_language',labelLanguage);await dbSaveSetting('price_numeral_system',priceNumeralSystem);await dbSaveSetting('weight_numeral_system',weightNumeralSystem);
  updatePriceLabelPreview();updatePreviewFromManual();updateCurrencyPreview();renderLibrary();renderPrintQueue();toast(currentTexts.labelLangSaved||(currentLanguage==='ar'?'تم حفظ لغة الملصق':'Label language saved'));}
async function savePriceNumeralSetting(){const sel=document.getElementById('price-numeral-select');if(!sel)return;priceNumeralSystem=sel.value;localStorage.setItem('price_numeral_system',priceNumeralSystem);await dbSaveSetting('price_numeral_system',priceNumeralSystem);updatePriceLabelPreview();updateCurrencyPreview();updatePreviewFromManual();renderLibrary();renderPrintQueue();toast(currentTexts.priceNumSaved||(currentLanguage==='ar'?'تم حفظ نظام أرقام السعر':'Price number system saved'));}
async function saveWeightNumeralSetting(){const sel=document.getElementById('weight-numeral-select');if(!sel)return;weightNumeralSystem=sel.value;localStorage.setItem('weight_numeral_system',weightNumeralSystem);await dbSaveSetting('weight_numeral_system',weightNumeralSystem);updatePriceLabelPreview();updatePreviewFromManual();renderLibrary();renderPrintQueue();toast(currentTexts.weightNumSaved||(currentLanguage==='ar'?'تم حفظ نظام أرقام الوزن':'Weight number system saved'));}
function updatePriceLabelPreview(){
  const langSel=document.getElementById('label-language-select');if(langSel)langSel.value=labelLanguage;
  const numSel=document.getElementById('price-numeral-select');if(numSel)numSel.value=priceNumeralSystem;
  const wSel=document.getElementById('weight-numeral-select');if(wSel)wSel.value=weightNumeralSystem;
  const el=document.getElementById('label-print-preview-text');if(!el)return;
  const lblLang=resolveLabelLang();
  const sampleName=(lblLang==='ar')?'اسم المنتج التجريبي':'Sample Product Name';
  const sampleWeight=formatWeightForLabel('500g',lblLang,weightNumeralSystem);
  const priceHtml=formatCurrency('99.00',false)||('SAR '+applyNumeralSystem('99.00'));
  el.innerHTML=sampleName+' — '+priceHtml+(sampleWeight?' — <span style="color:var(--text2);font-weight:600;">'+sampleWeight+'</span>':'');
  el.setAttribute('dir',lblLang==='ar'?'rtl':'ltr');
}

// ============================================================
// CUSTOM LABEL SIZES
// ============================================================
function addCustomLabelSize(){
  const nameAr=document.getElementById('custom-name')?.value.trim();
  const width=parseFloat(document.getElementById('custom-width')?.value);
  const height=parseFloat(document.getElementById('custom-height')?.value);
  if(!nameAr||isNaN(width)||isNaN(height)||width<=0||height<=0){
    toast(currentLanguage==='ar'?'الرجاء إدخال اسم وأبعاد صحيحة':'Please enter valid name and dimensions','error');
    return;
  }
  const id='custom_'+Date.now();

  const isSmall = (width <= 4.5 || height <= 3.0);

  const barcodeWidth = isSmall
    ? Math.round(width * 32)
    : Math.min(180, Math.max(90, width * 28));
  const barcodeHeight = isSmall
    ? Math.max(38, Math.round(height * 18))
    : Math.min(90,  Math.max(30, height * 16));

  const titleFont = isSmall
    ? Math.max(4.5, Math.min(6.5, height * 2.2))
    : Math.min(13, Math.max(7, height * 3.5));

  const shopFont = isSmall
    ? Math.max(6, Math.min(8, height * 3.0))
    : Math.min(16, Math.max(8, height * 4.0));

  const priceFont = isSmall
    ? Math.max(5, Math.min(7, height * 2.4))
    : Math.min(11, Math.max(7, height * 3));
  const weightFont = isSmall
    ? Math.max(4, Math.min(5.5, height * 1.8))
    : Math.min(9, Math.max(4.5, height * 2.2));
  const expFont = isSmall
    ? Math.max(4, Math.min(5.5, height * 1.9))
    : Math.min(9, Math.max(4.5, height * 2.5));

  const padding = isSmall
    ? Math.max(1.5, Math.min(3, height * 1.2))
    : Math.min(8, Math.max(1.5, height * 1.1));

  labelSizes[id]={
    id, width, height,
    barcodeWidth, barcodeHeight,
    titleFont, shopFont, priceFont, weightFont, expFont,
    padding,
    custom:true,
    nameAr, nameEn:nameAr
  };
  localStorage.setItem('label_sizes',JSON.stringify(labelSizes));
  renderLabelSizes();
  toast(currentTexts.customAdded);
  document.getElementById('custom-name').value='';
  document.getElementById('custom-width').value='';
  document.getElementById('custom-height').value='';
}
function deleteCustomLabelSize(id){if(labelSizes[id]&&labelSizes[id].custom){delete labelSizes[id];localStorage.setItem('label_sizes',JSON.stringify(labelSizes));if(selectedLabelSize===id){selectedLabelSize='6x4';localStorage.setItem('selected_label_size',selectedLabelSize);}renderLabelSizes();renderPrintQueue();updatePreviewFromManual();toast(currentLanguage==='ar'?'تم حذف الحجم المخصص':'Custom size deleted');}}
function renderLabelSizes(){const container=document.getElementById('paper-size-list');if(!container)return;container.innerHTML='';for(const[id,size]of Object.entries(labelSizes)){const name=currentLanguage==='ar'?size.nameAr:(size.nameEn||size.nameAr);const isSelected=selectedLabelSize===id;const btn=document.createElement('div');btn.className='label-size-btn'+(isSelected?' selected':'');let rightEl='';if(id==='6x4'){rightEl=`<div class="badge badge-purple">${currentTexts.recommended||'موصى به'}</div>`;}else if(size.custom){rightEl=`<button class="btn btn-danger btn-sm" style="padding:4px 8px;font-size:11px;" onclick="event.stopPropagation();deleteCustomLabelSize('${id}')">✕</button>`;}btn.innerHTML=`<div><div class="label-size-name">${size.custom?'⭐ ':'📏 '}${name}</div><div class="label-size-dim">${size.width}cm × ${size.height}cm</div></div>${rightEl}`;btn.onclick=()=>selectLabelSize(id);container.appendChild(btn);}}

// ============================================================
// LANGUAGE
// ============================================================
function setLanguage(lang){
  currentLanguage=lang;localStorage.setItem('language',lang);dbSaveSetting('language',lang);currentTexts=translations[lang];
  if(lang==='ar'){document.body.classList.remove('ltr');document.body.classList.add('rtl');document.documentElement.setAttribute('dir','rtl');}
  else{document.body.classList.remove('rtl');document.body.classList.add('ltr');document.documentElement.setAttribute('dir','ltr');}
  const btnAr=document.getElementById('btn-ar');if(btnAr)btnAr.classList.toggle('active',lang==='ar');
  const btnEn=document.getElementById('btn-en');if(btnEn)btnEn.classList.toggle('active',lang==='en');
  _applyAllLangTexts(lang);
  renderCategoryDropdowns();renderCategoriesList();updateCurrencyPreview();updatePriceLabelPreview();renderLabelSizes();renderWeightUnitOptions();updatePreviewFromManual();renderLibrary();renderPrintQueue();updateThemeDrawerLabel();renderUsersTable();
  if(typeof window.pageOnLanguageChange==='function')window.pageOnLanguageChange(lang);
}

// ============================================================
// THEME
// ============================================================
function toggleTheme(){var isLight=document.body.classList.toggle('light-mode');localStorage.setItem('theme',isLight?'light':'dark');updateThemeDrawerLabel();}
function applyTheme(){var saved=localStorage.getItem('theme')||'dark';if(saved==='light')document.body.classList.add('light-mode');else document.body.classList.remove('light-mode');updateThemeDrawerLabel();}
function updateThemeDrawerLabel(){var isLight=document.body.classList.contains('light-mode');var el=document.getElementById('d-txt-theme');if(!el)return;if(currentLanguage==='ar'){el.textContent=isLight?'☀️ الوضع النهاري':'🌙 الوضع الليلي';}else{el.textContent=isLight?'☀️ Light Mode':'🌙 Dark Mode';}}

// ============================================================
// MOBILE NAV / MISC
// ============================================================
function toggleMobileNav(){const btn=document.getElementById('hamburger-btn');const overlay=document.getElementById('mobile-nav-overlay');const drawer=document.getElementById('mobile-nav-drawer');if(!btn||!overlay||!drawer)return;const isOpen=drawer.classList.contains('open');if(isOpen){closeMobileNav();}else{btn.classList.add('open');overlay.classList.add('open');drawer.classList.add('open');}}
function closeMobileNav(){const btn=document.getElementById('hamburger-btn');const overlay=document.getElementById('mobile-nav-overlay');const drawer=document.getElementById('mobile-nav-drawer');if(btn)btn.classList.remove('open');if(overlay)overlay.classList.remove('open');if(drawer)drawer.classList.remove('open');}
function selectLabelSize(size){if(labelSizes[size]){selectedLabelSize=size;localStorage.setItem('selected_label_size',size);renderLabelSizes();renderPrintQueue();updatePreviewFromManual();}}
function toast(msg,type='success'){const c=document.getElementById('toast-container');if(!c)return;const t=document.createElement('div');t.className='toast';t.innerHTML=`<span>${type==='success'?'✅':'❌'}</span><span>${msg}</span>`;c.appendChild(t);setTimeout(()=>t.remove(),3000);}
function saveData(){localStorage.setItem('bc-queue',JSON.stringify(printQueue));}

// ============================================================
// HEADER SETTINGS
// ============================================================
function updateHeaderPreview(){const inp=document.getElementById('shop-name-setting');const el=document.getElementById('header-shop-name');if(!inp||!el)return;const n=inp.value;el.textContent=n||(currentLanguage==='ar'?'الضيافة':'Al-Diyafa');}
function updateLogoPreview(){const inp=document.getElementById('logo-url-setting');const img=document.getElementById('logo-preview-img');if(!inp||!img)return;const n=inp.value;if(n){img.src=n;img.onerror=()=>{img.src='image/logo.png';};}}
async function saveHeaderSettings(){const sEl=document.getElementById('shop-name-setting');const lEl=document.getElementById('logo-url-setting');const newShopName=sEl?sEl.value.trim():'';const newLogo=lEl?lEl.value.trim():'';if(newShopName){shopName=newShopName;const el=document.getElementById('header-shop-name');if(el)el.textContent=shopName;}if(newLogo){logoUrl=newLogo;const el=document.getElementById('header-logo');if(el)el.src=logoUrl;}await dbSaveSettings({shop_name:shopName,logo_url:logoUrl});toast(currentTexts.headerSaved);updatePreviewFromManual();}
async function saveBarcodeDefaults(){const inp=document.getElementById('default-prefix');if(!inp)return;const newPrefix=inp.value.trim();if(newPrefix&&newPrefix.length>=4)defaultPrefix=newPrefix;await dbSaveSetting('default_prefix',defaultPrefix);toast(currentTexts.defaultsSaved);}
function updateToggleLabel(){const chk=document.getElementById('edit-status-toggle');const txt=document.getElementById('toggle-status-text');if(!chk||!txt)return;if(chk.checked){txt.textContent=currentTexts.statusActive||'Active';txt.className='toggle-status on';}else{txt.textContent=currentTexts.statusDisabled||'Disabled';txt.className='toggle-status off';}}

// ============================================================
// EDIT BARCODE MODAL
// ============================================================
function openEditModal(id){
  const sid=String(id);
  const bc=barcodes.find(b=>String(b.id)===sid);
  if(!bc)return;
  currentEditId=sid;
  document.getElementById('edit-title').value=bc.title||'';
  document.getElementById('edit-price').value=bc.price||'';
  const editWeightSplit=splitWeightString(bc.weight);
  document.getElementById('edit-weight-value').value=editWeightSplit.value;
  document.getElementById('edit-weight-unit').value=editWeightSplit.unit;
  document.getElementById('edit-weight').value=bc.weight||'';
  document.getElementById('edit-barcode').value=bc.code||'';

  var eedPicker=document.getElementById('edit-expdate-picker');
  var eed=document.getElementById('edit-expdate');
  if(eedPicker) eedPicker.value = bc.expDate || '';
  if(eed) eed.value = bc.expDate ? isoToDDMMYYYY(bc.expDate) : '';

  renderCategoryDropdowns();
  document.getElementById('edit-category').value=bc.category||'';
  const toggle=document.getElementById('edit-status-toggle');
  if(toggle){toggle.checked=(bc.disabled!==true);updateToggleLabel();}
  hideDupWarning('dup-warning-edit');
  document.getElementById('edit-modal').classList.add('open');
  document.getElementById('edit-barcode').oninput=function(){
    const raw=this.value.replace(/\D/g,'');
    this.value=raw;
    if(raw.length>=12){
      const full=completeEAN13(raw);
      if(full&&isDuplicateBarcode(full,currentEditId)){showDupWarning('dup-warning-edit',full,currentEditId);}
      else{hideDupWarning('dup-warning-edit');}
    }else{hideDupWarning('dup-warning-edit');}
  };
}
function closeEditModal(){document.getElementById('edit-modal').classList.remove('open');currentEditId=null;hideDupWarning('dup-warning-edit');}
async function saveEdit(){const newTitle=document.getElementById('edit-title').value.trim();if(!newTitle){toast(currentTexts.enterProductName,'error');return;}let newCode=document.getElementById('edit-barcode').value.trim();const fullCode=completeEAN13(newCode);if(!fullCode||!validateEAN13(fullCode)){toast(currentTexts.invalidBarcode,'error');return;}if(isDuplicateBarcode(fullCode,currentEditId)){const inp=document.getElementById('edit-barcode');inp.classList.add('input-shake');setTimeout(()=>inp.classList.remove('input-shake'),500);showDupWarning('dup-warning-edit',fullCode,currentEditId);toast(currentTexts.duplicateBarcode,'error');return;}const index=barcodes.findIndex(b=>String(b.id)===String(currentEditId));if(index!==-1){var eedSave=document.getElementById('edit-expdate');const toggleSave=document.getElementById('edit-status-toggle');const originalCreator=barcodes[index].created_by||loggedInUsername;const updated={...barcodes[index],title:newTitle,price:document.getElementById('edit-price').value,weight:document.getElementById('edit-weight').value,expDate:eedSave?ddmmyyyyToISO(eedSave.value):'',code:fullCode,category:document.getElementById('edit-category').value,disabled:toggleSave?!toggleSave.checked:false,date:new Date().toISOString(),created_by:originalCreator};try{await idbPut('barcodes',updated);let serverSaved=false;try{const ctrl=new AbortController();const timer=setTimeout(()=>ctrl.abort(),8000);const res=await fetch(API_BASE+'update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(updated),signal:ctrl.signal,cache:'no-store'});clearTimeout(timer);if(res.ok){const j=await res.json();if(j&&j.success){serverSaved=true;USE_PHP=true;}}}catch(serverErr){}barcodes[index]=updated;renderLibrary();toast(serverSaved?currentTexts.barcodeUpdated:' تم الحفظ محلياً فقط');closeEditModal();}catch(e){toast('❌ خطأ في الحفظ','error');}}}
function genRandomBarcodeForEdit(){const code=generateRandomEAN13();if(code){const input=document.getElementById('edit-barcode');input.value=code;if(isDuplicateBarcode(code,currentEditId)){showDupWarning('dup-warning-edit',code,currentEditId);}else{hideDupWarning('dup-warning-edit');}}}

// ============================================================
// DELETE BARCODE POPUP
// ============================================================
function deleteBarcode(id){const sid=String(id);const bc=barcodes.find(b=>String(b.id)===sid);if(!bc)return;pendingDeleteId=sid;document.getElementById('del-popup-title').textContent=currentTexts.deletePopupTitle;document.getElementById('del-popup-msg').textContent=currentTexts.deletePopupMsg;document.getElementById('del-popup-confirm-text').textContent=currentTexts.deletePopupConfirmText;document.getElementById('del-popup-cancel-text').textContent=currentTexts.deletePopupCancelText;const nameEl=document.getElementById('del-popup-name');nameEl.innerHTML='<i class="fa-solid fa-magnifying-glass" style="color: rgb(255, 199, 0);"></i> '+bc.title+' · <span style="font-family:monospace;font-size:12px;">'+bc.code+'</span><br><span style="font-size:10px;color:var(--text2);">'+(currentLanguage==='ar'?'اضغط لعرض الباركود':'Click to preview barcode')+'</span>';nameEl._bcCode=bc.code;const preview=document.getElementById('del-popup-barcode-preview');preview.style.display='none';preview.innerHTML='';document.getElementById('delete-popup-overlay').style.display='flex';}
function toggleDeletePopupBarcode(){var nameEl=document.getElementById('del-popup-name');var preview=document.getElementById('del-popup-barcode-preview');var code=nameEl._bcCode;if(!code)return;if(preview.style.display==='none'||preview.style.display===''){preview.innerHTML=generateEAN13SVG(code,{width:200,height:70,color:'#000',bg:'#fff',showText:true});preview.style.display='block';}else{preview.style.display='none';preview.innerHTML='';}}
async function confirmDeleteBarcode(){if(!pendingDeleteId)return;try{await dbDeleteBarcode(pendingDeleteId);barcodes=barcodes.filter(b=>String(b.id)!==String(pendingDeleteId));printQueue=printQueue.filter(q=>String(q.id)!==String(pendingDeleteId));saveData();renderLibrary();renderPrintQueue();toast(currentTexts.barcodeDeleted);}catch(e){toast('Error deleting','error');}closeDeletePopup();}
function closeDeletePopup(){document.getElementById('delete-popup-overlay').style.display='none';var preview=document.getElementById('del-popup-barcode-preview');if(preview){preview.style.display='none';preview.innerHTML='';}pendingDeleteId=null;}
function handleDeletePopupOverlayClick(e){if(e.target===document.getElementById('delete-popup-overlay'))closeDeletePopup();}

// ============================================================
// LIBRARY RENDER
// ============================================================
function renderLibrary(){
  const grid=document.getElementById('barcode-grid');if(!grid)return;
  if(!barcodes||barcodes.length===undefined){
    const fallback=localStorage.getItem('bc-barcodes-fallback');
    if(fallback){try{barcodes=JSON.parse(fallback);}catch(e){}}
    if(!barcodes||barcodes.length===0){grid.innerHTML='<div class="empty-state">⏳ جاري تحميل الباركودات...</div>';return;}
  }
  const search=document.getElementById('lib-search')?.value.toLowerCase()||'';
  let filtered=barcodes.filter(b=>b.title.toLowerCase().includes(search)||b.code.includes(search));
  if(selectedCategoryFilter===EXPIRED_FILTER)filtered=filtered.filter(b=>isExpiredOrSoon(b));
  else if(selectedCategoryFilter===DISABLED_FILTER)filtered=filtered.filter(b=>b.disabled===true);
  else if(selectedCategoryFilter)filtered=filtered.filter(b=>b.category===selectedCategoryFilter);
  renderLibraryCategoryFilter();
  const countBadge=document.getElementById('library-count-badge');
  if(countBadge){if((search||selectedCategoryFilter)&&filtered.length!==barcodes.length){countBadge.textContent=`${filtered.length} / ${barcodes.length}`;}else{countBadge.textContent=barcodes.length;}}
  if(filtered.length===0){grid.innerHTML=`<div class="empty-state">${currentTexts.noBarcodes}</div>`;return;}
  function renderCard(bc){
    const val=(bc.price&&bc.price!==''&&bc.price!=='0')?bc.price:null;
    let priceHtml=val?formatCurrency(val,true):'';
    const catHtml=bc.category?`<div class="cat-badge">🏷️ ${bc.category}</div>`:'';
    var expBadge='';var expiredCard='';var expiredOverlay='';
    if(bc.expDate&&bc.expDate!==''){var expD=new Date(bc.expDate+'T00:00:00');var expFormatted=String(expD.getDate()).padStart(2,'0')+'/'+String(expD.getMonth()+1).padStart(2,'0')+'/'+expD.getFullYear();var now=new Date();now.setHours(0,0,0,0);var daysLeft=Math.floor((expD-now)/(864e5));var expColor=(daysLeft<=0)?'#cc0000':(daysLeft<=7?'#cc0000':'#e07800');var expBg=(daysLeft<=7)?'rgba(204,0,0,0.12)':'rgba(224,120,0,0.12)';expBadge='<span class="badge" style="background:'+expBg+';color:'+expColor+';">📅 EXP: '+expFormatted+'</span>';if(daysLeft<=0){expiredCard=' expired-card';expiredOverlay='<div class="expired-badge-overlay">'+(currentLanguage==='ar'?'⏰ منتهي':'⏰ Expired')+'</div>';}else if(daysLeft<=7){expiredCard=' expired-card';expiredOverlay='<div class="expired-badge-overlay" style="background:#e07800;">'+(currentLanguage==='ar'?'⚠️ ينتهي قريباً':'⚠️ Expiring Soon')+'</div>';}}
    const creatorName=bc.created_by||(currentLanguage==='ar'?'غير معروف':'Unknown');
    const creatorLabel=currentLanguage==='ar'?'بواسطة:':'By:';
    return'<div class="barcode-card'+expiredCard+(bc.disabled?' disabled-card':'')+'" onclick="goToPrintWithBarcode(\''+bc.id+'\')">'
      +expiredOverlay+(bc.disabled?'<div class="disabled-badge-overlay">'+(currentLanguage==='ar'?'⛔ معطّل':'⛔ Disabled')+'</div>':'')
      +'<div style="background:#fff;padding:12px;text-align:center;">'+generateEAN13SVG(bc.code,{width:140,height:50})+'</div>'
      +'<div style="padding:12px;padding-bottom:14px;"><div class="lib-product-title">'+bc.title+'</div>'+catHtml+'<div class="lib-barcode-number">'+bc.code+'</div>'
      +'<div class="lib-creator"><i class="fa-solid fa-user-tie" style="color: rgb(255, 199, 0);"></i> '+creatorLabel+' '+creatorName+'</div>'
      +'<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;justify-content:center;">'+priceHtml+(bc.weight?'<span class="badge badge-purple">⚖️ '+formatWeightForLabel(bc.weight,resolveLabelLang(),weightNumeralSystem)+'</span>':'')+expBadge+'</div></div>'
      +'<button class="card-dl-btn" onclick="event.stopPropagation();downloadBarcode(\''+bc.id+'\')" title="'+(currentLanguage==='ar'?'تحميل PNG':'Download PNG')+'"><i class="fa-solid fa-download fa-bounce" style="color: rgb(255, 255, 255);"></i></button>'
      +'<div class="card-actions" onclick="event.stopPropagation()">'
      +'<button class="btn btn-warning btn-sm" onclick="openEditModal(\''+bc.id+'\')" style="flex:1;">✏️ '+currentTexts.editLabel+'</button>'
      +'<button class="btn btn-success btn-sm" onclick="addOneToQueueAndPrint(\''+bc.id+'\')" style="flex:1;">🖨️ '+currentTexts.addLabel+'</button>'
      +'<button class="btn btn-danger btn-sm" onclick="deleteBarcode(\''+bc.id+'\')" style="flex:1;">🗑️ '+currentTexts.deleteLabel+'</button>'
      +'</div></div>';
  }
  grid.innerHTML=filtered.map(renderCard).join('');
}

// ============================================================
// DOWNLOAD
// ============================================================
function downloadBarcode(id){const bc=barcodes.find(b=>String(b.id)===String(id));if(!bc)return;const SCALE=3;const W=400;const BW=360;const BH=120;const NH=52;const H=BH+NH;const svgStr=generateEAN13SVG(bc.code,{width:BW,height:BH,color:'#000000',bg:'#ffffff',showText:false});const canvas=document.createElement('canvas');canvas.width=W*SCALE;canvas.height=H*SCALE;const ctx=canvas.getContext('2d');ctx.scale(SCALE,SCALE);ctx.fillStyle='#ffffff';ctx.fillRect(0,0,W,H);const svgBlob=new Blob([svgStr],{type:'image/svg+xml;charset=utf-8'});const url=URL.createObjectURL(svgBlob);const img=new Image();img.onload=function(){URL.revokeObjectURL(url);ctx.drawImage(img,(W-BW)/2,0,BW,BH);const drawText=function(){ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillStyle='#000000';let fs=36;for(;fs>=10;fs--){ctx.font='bold '+fs+'px "OCR-B","Courier New",monospace';if(ctx.measureText(bc.code).width<=W-16)break;}ctx.font='bold '+fs+'px "OCR-B","Courier New",monospace';ctx.fillText(bc.code,W/2,BH+NH/2);canvas.toBlob(function(blob){const dlName=(bc.title&&bc.title.trim()!=='')?bc.title.trim().replace(/[\\/:*?"<>|]/g,'_').substring(0,80):bc.code;const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=dlName+'.png';document.body.appendChild(a);a.click();document.body.removeChild(a);setTimeout(()=>URL.revokeObjectURL(a.href),1000);toast(currentLanguage==='ar'?'✅ تم تحميل الباركود':'✅ Barcode downloaded');},'image/png',1.0);};const fontsReady=(document.fonts&&document.fonts.load)?document.fonts.load('bold 36px "OCR-B"').then(()=>document.fonts.ready):Promise.resolve();fontsReady.then(drawText).catch(drawText);};img.src=url;}

function goToPrintWithBarcode(id){const bc=barcodes.find(b=>String(b.id)===String(id));if(bc){printQueue=[bc];saveData();toast(currentTexts.addedToQueue);window.location.href='print.php';}}
function addOneToQueueAndPrint(id){const bc=barcodes.find(b=>String(b.id)===String(id));if(bc){printQueue=[bc];saveData();renderPrintQueue();toast(currentTexts.addedToQueue);setTimeout(()=>printAllInQueue(),300);}}

// ============================================================
// QUEUE & GENERATOR
// ============================================================
function resetGeneratorForm(){const t=document.getElementById('m-title');if(!t)return;t.value='';document.getElementById('m-price').value='';document.getElementById('m-weight').value='';document.getElementById('m-expdate').value='';document.getElementById('m-category').value='';hideDupWarning('dup-warning-gen');const nb=generateRandomEAN13();if(nb){document.getElementById('m-barcode').value=nb;currentPreviewCode=nb;}else{document.getElementById('m-barcode').value='';currentPreviewCode='';}currentPreviewTitle='';currentPreviewPrice='';currentPreviewWeight='';currentPreviewExpDate='';renderLivePreview();}
async function _buildAndSaveFromForm(){const titleInput=document.getElementById('m-title');const title=titleInput.value.trim();if(!title){toast(currentTexts.enterProductName,'error');showInputError(titleInput,true);return null;}showInputError(titleInput,false);if(!currentPreviewCode){toast(currentLanguage==='ar'?'أنشئ باركوداً أولاً':'Generate a barcode first','error');return null;}if(isDuplicateBarcode(currentPreviewCode,null)){const inp=document.getElementById('m-barcode');showInputError(inp,true);showDupWarning('dup-warning-gen',currentPreviewCode,null);toast(currentTexts.duplicateBarcode,'error');return null;}const edEl=document.getElementById('m-expdate');const newItem={id:Date.now(),code:currentPreviewCode,title,price:document.getElementById('m-price').value,weight:document.getElementById('m-weight').value,expDate:edEl?ddmmyyyyToISO(edEl.value):'',category:document.getElementById('m-category').value,date:new Date().toISOString(),created_by:loggedInUsername};try{await dbSaveBarcode(newItem);return newItem;}catch(e){toast('Error saving barcode','error');return null;}}
async function addToQueueOnly(){const newItem=await _buildAndSaveFromForm();if(!newItem)return;barcodes.unshift(newItem);printQueue.push(newItem);saveData();toast(currentTexts.barcodeSaved);resetGeneratorForm();}
async function addToQueueAndPrint(){const nb=await _buildAndSaveFromForm();if(!nb)return;barcodes.unshift(nb);printQueue=[nb];saveData();toast(currentTexts.addedToQueue);resetGeneratorForm();setTimeout(()=>printAllInQueue(),300);}
function removeFromQueue(id){printQueue=printQueue.filter(q=>String(q.id)!==String(id));saveData();renderPrintQueue();}
function clearQueue(){if(printQueue.length===0)return;if(!confirm(currentTexts.confirmClearQueue))return;printQueue=[];saveData();renderPrintQueue();toast(currentTexts.queueCleared);}
function renderPrintQueue(){const container=document.getElementById('print-queue-list');const count=printQueue.length;const pce=document.getElementById('queue-count-print');const rce=document.getElementById('queue-count-right');if(pce)pce.textContent=count;if(rce)rce.textContent=count;if(!container)return;if(printQueue.length===0){container.innerHTML=`<div class="empty-state" style="padding:20px">${currentTexts.emptyQueue}</div>`;return;}container.innerHTML=printQueue.map((bc,i)=>{let priceDisplay=bc.price?`<span>💰 ${formatCurrency(bc.price,false)}</span>`:'';const catHtml=bc.category?`<span style="color:var(--accent);font-size:9px;">🏷️ ${bc.category}</span>`:'';const isDisabled=bc.disabled===true;const disabledStyle=isDisabled?'filter:grayscale(100%) opacity(0.5);':'';const disabledBadge=isDisabled?`<span style="font-size:9px;background:#555;color:#aaa;padding:1px 6px;border-radius:10px;margin-inline-start:4px;">${currentLanguage==='ar'?'معطّل':'Disabled'}</span>`:'';return`<div class="pq-item" style="${disabledStyle}"><span style="color:var(--text2);font-size:11px">${i+1}.</span><div style="flex:1;padding:0 10px"><div style="font-size:13px;font-weight:700;color:var(--accent2)">${bc.title}${disabledBadge}</div>${catHtml}<div style="font-size:10px;font-family:monospace">${bc.code}</div><div style="font-size:9px;display:flex;gap:10px;margin-top:2px">${priceDisplay}${bc.weight?`<span>⚖️ ${formatWeightForLabel(bc.weight,resolveLabelLang(),weightNumeralSystem)}</span>`:''}</div></div><button class="btn btn-danger btn-sm" onclick="removeFromQueue('${bc.id}')">✕</button></div>`;}).join('');}

// ============================================================
// PRINT (print.php)
// ============================================================
function printAllInQueue(){
  if(printQueue.length===0){toast(currentTexts.emptyQueue,'error');return;}
  const size=labelSizes[selectedLabelSize];if(!size){toast('Label size error','error');return;}
  toast(currentTexts.printingStarted);

  var labelW_cm = size.width;
  var labelH_cm = size.height;

  var pw_cm = labelW_cm + 'cm';
  var ph_cm = labelH_cm + 'cm';

  var labelsHtml = printQueue.map(function(bc){
    var inner = generateLabelHTML(
      bc.code,
      bc.title || (currentLanguage==='ar'?'اسم المنتج':'Product Name'),
      bc.price,
      bc.weight,
      selectedLabelSize,
      bc.expDate || ''
    );
    return '<div class="page-wrap">' + inner + '</div>';
  }).join('');

  var printCSS = [
    '@page { size: ' + pw_cm + ' ' + ph_cm + '; margin: 0; }', 
    '* { margin:0; padding:0; box-sizing:border-box; }',
    'html, body { margin:0; padding:0; background:white; font-family:"Noto Sans Arabic",Arial,sans-serif; width: 100%; height: 100%; }',
    
    '.page-wrap { width: 100%; height: 100%; display: flex; flex-direction: column; page-break-after: always; break-after: page; overflow: hidden; }',
    '.page-wrap:last-child { page-break-after: auto; break-after: auto; }',
    
    '.label-container { background:white; display:flex; flex-direction:column; justify-content:space-between; align-items:center; overflow:hidden; box-sizing:border-box; width: 100%; height: 100%; }',
    '.shop-name-text { font-family:"Noto Sans Arabic",Arial,sans-serif; font-weight:900; color:#1e3c72; text-align:center; width:100%; line-height:1; overflow:hidden; }',
    '.product-title-text { font-family:"Tajawal","Noto Sans Arabic",Arial,sans-serif; font-weight:500; text-align:center; color:#1a1a2e; line-height:1; width:100%; word-break:break-word; overflow:hidden; }',
    '.barcode-wrapper { text-align:center; width:100%; flex-shrink:0; }',
    '.barcode-wrapper svg { display:block; margin:0 auto; max-width:100%; height:auto; }',
    '.price-weight-row { display:flex; justify-content:space-between; align-items:center; width:100%; margin-top:1px; flex-shrink:0; }',
    '.price-text { font-weight:900; color:#2c7a2c; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }',
    '.weight-text { font-weight:bold; color:#a05e00; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }',
    '.exp-date-text { font-weight:bold; color:#000; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }',
    '.exp-date-urgent { color:#cc0000 !important; }',
    '.custom-label .shop-name-text { color:#1a3c6a !important; }',
    '.custom-label .product-title-text { color:#2c3e50 !important; font-weight:700 !important; }',
    '.custom-label .price-text { color:#27ae60 !important; }',
    '.custom-label .weight-text { color:#d35400 !important; }',
    
    '@media print {',
    '  html, body { width: ' + pw_cm + '; height: ' + ph_cm + '; }',
    '  * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }',
    '  .barcode-wrapper svg text { font-family:"OCR-B","JetBrains Mono",monospace !important; }',
    '}'
  ].join('');

  var htmlContent = '<!DOCTYPE html><html dir="ltr" lang="'+currentLanguage+'"><head><meta charset="UTF-8"><title>'+shopName+'</title>'
    +'<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700;900&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">'
    +'<style>'+printCSS+'</style></head><body>'+labelsHtml+'</body></html>';

  var oldFrame=document.getElementById('__print_iframe__');
  if(oldFrame&&oldFrame.parentNode)oldFrame.parentNode.removeChild(oldFrame);
  var iframe=document.createElement('iframe');
  iframe.id='__print_iframe__';
  iframe.setAttribute('aria-hidden','true');
  iframe.style.cssText='position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:none;opacity:0;pointer-events:none;';
  document.body.appendChild(iframe);
  
  iframe.onload=function(){
    iframe.onload=null;
    var printed=false;
    function doPrint(){
      if(printed)return;printed=true;
      try{iframe.contentWindow.focus();iframe.contentWindow.print();}
      catch(e){var pw=window.open('','_blank','width=800,height=600');if(pw){pw.document.open();pw.document.write(htmlContent);pw.document.close();pw.focus();setTimeout(function(){pw.print();},500);}}
      setTimeout(function(){if(iframe&&iframe.parentNode)iframe.parentNode.removeChild(iframe);},3000);
    }
    try{
      var idoc=iframe.contentDocument||iframe.contentWindow.document;
      if(idoc&&idoc.fonts&&idoc.fonts.ready){
        idoc.fonts.load('bold 20px "OCR-B"').catch(function(){});
        idoc.fonts.ready.then(function(){setTimeout(doPrint,50);}).catch(function(){setTimeout(doPrint,400);});
        setTimeout(doPrint,1200);
      }else{
        setTimeout(doPrint,400);
      }
    }catch(e){setTimeout(doPrint,400);}
  };
  try{var doc=iframe.contentDocument||iframe.contentWindow.document;doc.open('text/html','replace');doc.write(htmlContent);doc.close();}
  catch(e){iframe.srcdoc=htmlContent;}
}

// ============================================================
// LIVE PREVIEW
// ============================================================
function updatePreviewFromManual(){const mt=document.getElementById('m-title');if(!mt)return;currentPreviewTitle=mt.value.trim();currentPreviewPrice=document.getElementById('m-price').value;currentPreviewWeight=document.getElementById('m-weight').value;var edEl=document.getElementById('m-expdate');currentPreviewExpDate=edEl?ddmmyyyyToISO(edEl.value):'';if(currentPreviewCode)renderLivePreview();else renderLivePreviewPlaceholder();}
function renderLivePreview(){const container=document.getElementById('live-preview-container');if(!container)return;if(!currentPreviewCode){renderLivePreviewPlaceholder();return;}const isValid=validateEAN13(currentPreviewCode);const valEl=document.getElementById('preview-validity');if(valEl)valEl.innerHTML=isValid?`<span class="badge badge-green">✅ ${currentTexts.valid}</span>`:`<span class="badge" style="background:rgba(255,107,107,0.15);color:var(--accent3)">❌ ${currentTexts.invalid}</span>`;var displayTitle=currentPreviewTitle||(currentLanguage==='ar'?'اسم المنتج':'Product Name');container.innerHTML=generateLabelHTML(currentPreviewCode,displayTitle,currentPreviewPrice,currentPreviewWeight,selectedLabelSize,currentPreviewExpDate);var dlBtn=document.getElementById('download-barcode-btn');if(dlBtn)dlBtn.style.display=isValid?'flex':'none';}
function renderLivePreviewPlaceholder(){const container=document.getElementById('live-preview-container');if(!container)return;container.innerHTML='<div style="color:#999;font-size:12px;padding:40px;">'+(currentLanguage==='ar'?'أدخل بيانات المنتج لمعاينة الملصق':'Enter product details to see preview')+'</div>';const valEl=document.getElementById('preview-validity');if(valEl)valEl.innerHTML='';var dlBtn=document.getElementById('download-barcode-btn');if(dlBtn)dlBtn.style.display='none';}
function onManualInput(){const mb=document.getElementById('m-barcode');if(!mb)return;const raw=mb.value.replace(/\D/g,'');mb.value=raw;if(raw.length>=12){const full=completeEAN13(raw);if(full){currentPreviewCode=full;if(isDuplicateBarcode(full,null)){showDupWarning('dup-warning-gen',full,null);}else{hideDupWarning('dup-warning-gen');}renderLivePreview();}}else{currentPreviewCode='';hideDupWarning('dup-warning-gen');renderLivePreviewPlaceholder();}}
function genRandomBarcode(){let full=generateRandomEAN13();let attempts=0;while(isDuplicateBarcode(full,null)&&attempts<100){full=generateRandomEAN13();attempts++;}if(!full)return;currentPreviewCode=full;const mb=document.getElementById('m-barcode');if(mb)mb.value=full;hideDupWarning('dup-warning-gen');renderLivePreview();toast(currentTexts.randomGenerated);}

// ============================================================
// EXPORT / IMPORT / CLEAR
// ============================================================
function exportData(){const data={barcodes,shopName,logoUrl,defaultPrefix,labelSizes};const a=document.createElement('a');a.href='data:application/json,'+encodeURIComponent(JSON.stringify(data,null,2));a.download='barcode-export.json';a.click();toast(currentTexts.dataExported);}
function importData(input){const file=input.files[0];if(!file)return;input.value='';const reader=new FileReader();reader.onload=async e=>{let data;try{data=JSON.parse(e.target.result);}catch(e){toast(currentTexts.invalidFile,'error');return;}if(data.shopName)shopName=data.shopName;if(data.logoUrl)logoUrl=data.logoUrl;if(data.defaultPrefix)defaultPrefix=data.defaultPrefix;if(data.labelSizes){labelSizes=data.labelSizes;localStorage.setItem('label_sizes',JSON.stringify(labelSizes));}const incoming=data.barcodes||data.library||[];if(incoming.length===0){toast(currentLanguage==='ar'?'الملف لا يحتوي على باركودات':'No barcodes found','error');return;}
  const items=incoming.map((raw,i)=>{const bc={...raw};bc.title=bc.title||bc.name||'';bc.code=bc.code||bc.barcode||'';bc.price=bc.price||'';bc.weight=bc.weight||'';bc.category=bc.category||'';bc.expDate=bc.expDate||'';bc.date=bc.date||new Date().toISOString();if(!bc.created_by)bc.created_by=currentLanguage==='ar'?'غير معروف':'Unknown';if(!bc.id)bc.id=Date.now()+i+Math.floor(Math.random()*9999);return bc;});
  let saved=0,skipped=0;
  if(USE_PHP){
    try{
      const res=await phpPost('import.php',{items});
      if(res.success){saved=res.saved||0;skipped=res.skipped||0;
        const bRes=await phpGet('load.php');
        if(bRes.success){barcodes=bRes.barcodes||[];barcodes.sort((a,b)=>new Date(b.date||0)-new Date(a.date||0));}
      }else{
        toast(currentLanguage==='ar'?'فشل الاستيراد: غير مصرح':'Import failed: not permitted','error');
        return;
      }
    }catch(err){toast(currentLanguage==='ar'?'فشل الاستيراد':'Import failed','error');return;}
  }else{
    for(const bc of items){
      if(barcodes.some(b=>b.code===bc.code)){skipped++;continue;}
      try{await dbSaveBarcode(bc);barcodes.unshift(bc);saved++;}catch(e){skipped++;}
    }
    barcodes.sort((a,b)=>new Date(b.date||0)-new Date(a.date||0));
  }
  await dbSaveSettings({shop_name:shopName,logo_url:logoUrl,default_prefix:defaultPrefix});
  const hs=document.getElementById('header-shop-name');if(hs)hs.textContent=shopName;const hl=document.getElementById('header-logo');if(hl)hl.src=logoUrl;
  toast(currentLanguage==='ar'?'تم استيراد '+saved+' باركود'+(skipped>0?' (تخطي '+skipped+')':''):'Imported '+saved+' barcodes'+(skipped>0?' ('+skipped+' skipped)':''));
  renderLibrary();renderLabelSizes();renderCategoryDropdowns();renderCategoriesList();
};reader.readAsText(file);}
async function clearAllData(){
  if(!confirm(currentTexts.confirmDeleteAll))return;
  if(USE_PHP){
    const ids=barcodes.map(b=>b.id);
    if(ids.length)await phpPost('delete.php',{ids:ids}).catch(()=>{});
  }else{
    await idbClear('barcodes');
  }
  barcodes=[];printQueue=[];saveData();renderLibrary();renderPrintQueue();toast(currentTexts.allDataDeleted);
}

// ============================================================
// HIGH-RES DOWNLOAD
// ============================================================
function downloadBarcodeHighRes(){if(!currentPreviewCode||!validateEAN13(currentPreviewCode)){toast(currentLanguage==='ar'?'باركود غير صالح':'Invalid barcode','error');return;}const SCALE=3;const W=400;const BW=360;const BH=120;const NH=52;const H=BH+NH;var svgStr=generateEAN13SVG(currentPreviewCode,{width:BW,height:BH,color:'#000000',bg:'#ffffff',showText:false});var canvas=document.createElement('canvas');canvas.width=W*SCALE;canvas.height=H*SCALE;var ctx=canvas.getContext('2d');ctx.scale(SCALE,SCALE);ctx.fillStyle='#ffffff';ctx.fillRect(0,0,W,H);var svgBlob=new Blob([svgStr],{type:'image/svg+xml;charset=utf-8'});var url=URL.createObjectURL(svgBlob);var img=new Image();img.onload=function(){URL.revokeObjectURL(url);ctx.drawImage(img,(W-BW)/2,0,BW,BH);var drawText=function(){ctx.textAlign='center';ctx.textBaseline='middle';ctx.fillStyle='#000000';var fs=36;for(;fs>=10;fs--){ctx.font='bold '+fs+'px "OCR-B","Courier New",monospace';if(ctx.measureText(currentPreviewCode).width<=W-16)break;}ctx.font='bold '+fs+'px "OCR-B","Courier New",monospace';ctx.fillText(currentPreviewCode,W/2,BH+NH/2);var title=(currentPreviewTitle&&currentPreviewTitle.trim()!=='')?currentPreviewTitle.trim().replace(/[\\/:*?"<>|]/g,'_').substring(0,80):currentPreviewCode;canvas.toBlob(function(blob){var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=title+'.png';document.body.appendChild(a);a.click();document.body.removeChild(a);setTimeout(function(){URL.revokeObjectURL(a.href);},1000);toast(currentLanguage==='ar'?'✅ تم تحميل PNG':'✅ PNG downloaded');},'image/png',1.0);};var fontsReady=(document.fonts&&document.fonts.load)?document.fonts.load('bold 36px "OCR-B"').then(function(){return document.fonts.ready;}):Promise.resolve();fontsReady.then(drawText).catch(drawText);};img.onerror=function(){URL.revokeObjectURL(url);var a=document.createElement('a');a.download=(currentPreviewTitle||currentPreviewCode)+'.svg';a.href='data:image/svg+xml;charset=utf-8,'+encodeURIComponent(svgStr);document.body.appendChild(a);a.click();document.body.removeChild(a);};img.src=url;}

// ============================================================
// PWA
// ============================================================
(function(){if('serviceWorker'in navigator)navigator.serviceWorker.register('./service-worker.js').catch(()=>{});let deferredPrompt=null;window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferredPrompt=e;const btn=document.getElementById('pwa-install-btn');if(btn){btn.style.display='inline-flex';}});window.addEventListener('appinstalled',function(){const btn=document.getElementById('pwa-install-btn');if(btn)btn.style.display='none';deferredPrompt=null;});window.triggerPWAInstall=function(){if(deferredPrompt){deferredPrompt.prompt();deferredPrompt.userChoice.then(function(c){if(c.outcome==='accepted'){deferredPrompt=null;const btn=document.getElementById('pwa-install-btn');if(btn)btn.style.display='none';}});}};})();

document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeDeletePopup();closeEditModal();closeUserEditModal();closeUserDeletePopup();}});
document.addEventListener('DOMContentLoaded',function(){
  const editModal=document.getElementById('edit-modal');if(editModal)editModal.addEventListener('click',function(e){if(e.target===this)closeEditModal();});
  const userEditModal=document.getElementById('user-edit-modal');if(userEditModal)userEditModal.addEventListener('click',function(e){if(e.target===this)closeUserEditModal();});
});

// ============================================================
// LOAD FROM DB
// ============================================================
async function loadFromDB(){
  await loadFromIDB();
  try{
    const ctrl=new AbortController();const timer=setTimeout(()=>ctrl.abort(),5000);
    const res=await fetch(API_BASE+'bootstrap.php',{signal:ctrl.signal,cache:'no-store'});
    clearTimeout(timer);
    if(res.ok){
      const j=await res.json();
      if(j&&j.success){
        USE_PHP=true;
        const st=j.settings||{};
        if(st.shop_name)shopName=st.shop_name;if(st.logo_url)logoUrl=st.logo_url;if(st.default_prefix)defaultPrefix=st.default_prefix;if(st['bc-currency'])selectedCurrency=st['bc-currency'];if(st.language){currentLanguage=st.language;localStorage.setItem('language',st.language);}if(st.label_language){labelLanguage=st.label_language;localStorage.setItem('label_language',st.label_language);}if(st.price_numeral_system){priceNumeralSystem=st.price_numeral_system;localStorage.setItem('price_numeral_system',st.price_numeral_system);}if(st.weight_numeral_system){weightNumeralSystem=st.weight_numeral_system;localStorage.setItem('weight_numeral_system',st.weight_numeral_system);}
        categories=j.categories||[];
        barcodes=j.barcodes||[];
        barcodes.sort((a,b)=>new Date(b.date||0)-new Date(a.date||0));
      }else{USE_PHP=false;}
    }else{USE_PHP=false;}
  }catch(e){USE_PHP=false;}
}
async function loadFromIDB(){
  try{shopName=await idbGetSetting('shop_name',shopName);logoUrl=await idbGetSetting('logo_url',logoUrl);defaultPrefix=await idbGetSetting('default_prefix',defaultPrefix);selectedCurrency=await idbGetSetting('bc-currency',selectedCurrency);const lang=await idbGetSetting('language',currentLanguage);currentLanguage=lang;localStorage.setItem('language',lang);labelLanguage=await idbGetSetting('label_language',labelLanguage);localStorage.setItem('label_language',labelLanguage);priceNumeralSystem=await idbGetSetting('price_numeral_system',priceNumeralSystem);localStorage.setItem('price_numeral_system',priceNumeralSystem);weightNumeralSystem=await idbGetSetting('weight_numeral_system',weightNumeralSystem);localStorage.setItem('weight_numeral_system',weightNumeralSystem);categories=(await idbGetAll('categories')).map(c=>c.name);barcodes=await idbGetAll('barcodes');barcodes.sort((a,b)=>new Date(b.date||0)-new Date(a.date||0));try{localStorage.setItem('bc-barcodes-fallback',JSON.stringify(barcodes));}catch(e){}try{localStorage.setItem('bc-categories-fallback',JSON.stringify(categories));}catch(e){}}catch(e){barcodes=JSON.parse(localStorage.getItem('bc-barcodes-fallback')||'[]');categories=JSON.parse(localStorage.getItem('bc-categories-fallback')||'[]');}
}

// ============================================================
// APPLY ALL LANG TEXTS
// ============================================================
function _applyAllLangTexts(lang){
  const t=currentTexts;
  const $=id=>document.getElementById(id);
  const setText=(id,txt)=>{const el=$(id);if(el)el.textContent=txt;};
  const setHTML=(id,html)=>{const el=$(id);if(el)el.innerHTML=html;};
  const sbRole=$('sb-user-role-text');if(sbRole)sbRole.textContent=isAdminUser?(lang==='ar'?'مدير النظام':'System Admin'):(lang==='ar'?'مستخدم نشط':'Active User');
  ['generator','library','print','users','settings'].forEach(k=>{const v=t['nav'+k.charAt(0).toUpperCase()+k.slice(1)];if(v){setText('sb-txt-'+k,v);setText('d-txt-'+k,v);}});
  setText('sb-txt-logout',t.logoutBtn);setText('d-txt-logout',t.logoutBtn);
  const statusLbl=$('edit-status-label');if(statusLbl)statusLbl.textContent=t.statusLabel;
  updateToggleLabel();
  setText('gen-title',t.genTitle);setText('label-product-name',t.productName);setText('label-price',t.price);setText('label-weight',t.weight);setText('label-weight-unit',t.weightUnitLabel);setText('label-barcode',t.barcodeNumber);setText('barcode-hint',t.barcodeHint);
  setHTML('save-btn','➕ '+t.addToQueue);setHTML('add-and-print-btn','🖨️ '+t.addAndPrint);
  setText('preview-title',t.previewTitle);setText('library-title',t.libraryTitle);
  const ls=$('lib-search');if(ls)ls.placeholder=t.searchPlaceholder;
  setText('click-hint-text',t.clickHint);setText('print-size-title',t.printSizeTitle);setText('custom-size-title',t.customSizeTitle);
  $('custom-name')?.setAttribute('placeholder',t.customNamePlaceholder);
  $('custom-width')?.setAttribute('placeholder',t.customWidthPlaceholder);
  $('custom-height')?.setAttribute('placeholder',t.customHeightPlaceholder);
  setText('print-settings-title',t.printSettings);setText('print-info1',t.printInfo1);setText('print-info2',t.printInfo2);setText('print-info3',t.printInfo3);
  setHTML('print-all-btn',`${t.printAll} (<span id="queue-count-print">0</span>)`);
  setHTML('clear-queue-btn',t.clearQueue);
  setHTML('queue-title',`${t.queueTitle} (<span id="queue-count-right">0</span>)`);
  setText('settings-header-title',t.settingsHeader);setText('shop-name-label',t.shopNameLabel);setText('logo-url-label',t.logoUrlLabel);setText('logo-hint',t.logoHint);setText('logo-preview-text',t.logoPreview);
  setHTML('save-header-btn',t.saveHeader);setText('default-settings-title',t.defaultSettings);setText('default-prefix-label',t.defaultPrefixLabel);setText('prefix-hint',t.prefixHint);setHTML('save-defaults-btn',t.saveDefaults);
  setText('data-mgmt-title',t.dataManagement);setHTML('export-btn',t.exportData);setHTML('import-btn',t.importData);setHTML('delete-all-btn',t.deleteAll);
  setText('edit-modal-title',t.editTitle);setText('edit-product-label',t.editProduct);setText('edit-price-label',t.editPrice);setText('edit-weight-label',t.editWeight);setText('edit-weight-unit-label',t.weightUnitLabel);setText('edit-barcode-label',t.editBarcode);setHTML('save-edit-btn',t.saveEdit);setHTML('cancel-edit-btn',t.cancelEdit);
  const mt=$('m-title');if(mt)mt.placeholder=t.productPlaceholder;const mw=$('m-weight');if(mw)mw.placeholder=t.weightPlaceholder;const mb=$('m-barcode');if(mb)mb.placeholder=t.barcodePlaceholder;
  const lc=$('label-category');if(lc)lc.textContent=t.categoryLabel;const ecl=$('edit-category-label');if(ecl)ecl.textContent=t.categoryLabel;
  setText('categories-settings-title',t.categoriesTitle);const nci=$('new-category-input');if(nci)nci.placeholder=t.categoryInputPlaceholder;
  setText('currency-settings-title',t.currencyTitle);setText('currency-label',t.currencySelectLabel);setText('currency-preview-label',t.currencyPreviewLabel);
  setText('print-label-settings-title',t.printLabelSettingsTitle);setText('label-language-label',t.labelLangSelectLabel);setText('price-numeral-label',t.priceNumSelectLabel);setText('price-numeral-hint',t.priceNumHint);setText('weight-numeral-label',t.weightNumSelectLabel);setText('weight-numeral-hint',t.weightNumHint);setText('label-preview-label',t.labelPreviewLabel);
  const lls=$('label-language-select');if(lls&&lls.options.length===3){lls.options[0].text=t.labelLangDefaultOption;lls.options[1].text=t.labelLangEnOption;lls.options[2].text=t.labelLangArOption;}
  const pns=$('price-numeral-select');if(pns&&pns.options.length===2){pns.options[0].text=t.priceNumArOption;pns.options[1].text=t.priceNumEnOption;}
  const wns=$('weight-numeral-select');if(wns&&wns.options.length===2){wns.options[0].text=t.weightNumArOption;wns.options[1].text=t.weightNumEnOption;}
  if(typeof updatePriceLabelPreview==='function')updatePriceLabelPreview();
  const pait=$('print-all-inline-text');if(pait)pait.textContent=t.printAllInline;
  const led=$('label-expdate');if(led&&led.childNodes[0])led.childNodes[0].nodeValue=t.expdateLabel+' ';
  const exo=$('expdate-optional');if(exo)exo.textContent=t.expdateOptional;
  const eel=$('edit-expdate-label');if(eel&&eel.childNodes[0])eel.childNodes[0].nodeValue=t.expdateLabel+' ';
  const dbt=$('download-btn-text');if(dbt)dbt.textContent=t.downloadHighRes;
  setText('del-popup-title',t.deletePopupTitle);setText('del-popup-msg',t.deletePopupMsg);setText('del-popup-confirm-text',t.deletePopupConfirmText);setText('del-popup-cancel-text',t.deletePopupCancelText);
  setText('users-page-title', t.usersPageTitle);
  var addUserLabel = document.getElementById('add-user-label');
  if (addUserLabel) {
    addUserLabel.innerHTML =
        '<i class="fa-solid fa-user-plus" style="color: rgb(255, 212, 59);"></i> ' +
        t.addUserLabel;
  }
  const nui=$('new-username-input');if(nui)nui.placeholder=lang==='ar'?'اسم المستخدم':'Username';
  const npi=$('new-password-input');if(npi)npi.placeholder=lang==='ar'?'كلمة المرور (6+ أرقام)':'Password (6+ digits)';
  const nrs=$('new-role-select');
  if(nrs){
    const userTxt=(lang==='ar'?'مستخدم':'User');
    const adminTxt=(lang==='ar'?'مشرف':'Admin');
    nrs.options[0].text=userTxt;nrs.options[1].text=adminTxt;
    const nrsWrap=$('new-role-wrap');
    if(nrsWrap){
      const o0=nrsWrap.querySelector('.custom-select-option[data-value="0"] .opt-text');if(o0)o0.textContent=userTxt;
      const o1=nrsWrap.querySelector('.custom-select-option[data-value="1"] .opt-text');if(o1)o1.textContent=adminTxt;
      syncCustomSelectLabel('new-role-wrap');
    }
  }
  const ers=$('edit-user-role-select');
  if(ers){
    const userTxt=(lang==='ar'?'مستخدم (User) — صلاحيات محدودة':'User — limited access');
    const adminTxt=(lang==='ar'?'مشرف (Admin) — صلاحيات كاملة':'Admin — full access');
    ers.options[0].text=userTxt;ers.options[1].text=adminTxt;
    const ersWrap=$('edit-role-wrap');
    if(ersWrap){
      const o0=ersWrap.querySelector('.custom-select-option[data-value="0"] .opt-text');if(o0)o0.textContent=userTxt;
      const o1=ersWrap.querySelector('.custom-select-option[data-value="1"] .opt-text');if(o1)o1.textContent=adminTxt;
      syncCustomSelectLabel('edit-role-wrap');
    }
  }
  setText('add-user-btn-text',t.addUserBtn);setText('pass-hint-text',t.passHint);
  setText('user-edit-modal-title',t.editUserModalTitle);setText('edit-user-name-label',t.editUserNameLabel);setText('edit-user-pass-label',t.editUserPassLabel);setText('edit-user-role-label',t.editUserRoleLabel);
  setText('save-user-edit-text',t.saveUserEdit);setText('cancel-user-edit-text',t.cancelUserEdit);
  updateThemeDrawerLabel();
  renderPrintQueue();
}

// ============================================================
// PAGE-SPECIFIC FUNCTIONS
// ============================================================
function combineWeight(){
  const val  = document.getElementById('m-weight-value').value.trim();
  const unit = document.getElementById('m-weight-unit').value;
  const hidden = document.getElementById('m-weight');
  hidden.value = val ? (val + unit) : '';
  updatePreviewFromManual();
}
function combineEditWeight(){
  const val  = document.getElementById('edit-weight-value').value.trim();
  const unit = document.getElementById('edit-weight-unit').value;
  const hidden = document.getElementById('edit-weight');
  hidden.value = val ? (val + unit) : '';
}
function splitWeightString(weightStr){
  const s = (weightStr || '').trim();
  const m = s.match(/^([\d.]+)\s*([a-zA-Z]*)$/);
  const validUnits = ['g','kg','ml','l'];
  if(m){
    const unit = validUnits.includes(m[2].toLowerCase()) ? m[2].toLowerCase() : 'g';
    return { value: m[1], unit: unit };
  }
  return { value: '', unit: 'g' };
}
function convertExpDate(){
  const picker = document.getElementById('m-expdate-picker').value;
  const hidden = document.getElementById('m-expdate');
  if(picker){
    const [y, m, d] = picker.split('-');
    hidden.value = d + '/' + m + '/' + y;
  } else {
    hidden.value = '';
  }
  updatePreviewFromManual();
}
let _libSearchDebounce = null;
function handleLibSearchInput(){
  const input = document.getElementById('lib-search');
  const clearBtn = document.getElementById('lib-search-clear');
  clearBtn.classList.toggle('show', input.value.length > 0);
  clearTimeout(_libSearchDebounce);
  _libSearchDebounce = setTimeout(renderLibrary, 200);
}
function clearLibSearch(){
  const input = document.getElementById('lib-search');
  input.value = '';
  document.getElementById('lib-search-clear').classList.remove('show');
  input.focus();
  renderLibrary();
}
function convertEditExpDate(){
  const picker = document.getElementById('edit-expdate-picker').value;
  const hidden = document.getElementById('edit-expdate');
  if(picker){
    const [y, m, d] = picker.split('-');
    hidden.value = d + '/' + m + '/' + y;
  } else {
    hidden.value = '';
  }
}
function setEditExpDateFromDDMMYYYY(ddmmyyyy){
  const picker = document.getElementById('edit-expdate-picker');
  const hidden = document.getElementById('edit-expdate');
  hidden.value = ddmmyyyy || '';
  if(ddmmyyyy && ddmmyyyy.includes('/')){
    const [d, m, y] = ddmmyyyy.split('/');
    picker.value = y + '-' + m.padStart(2,'0') + '-' + d.padStart(2,'0');
  } else {
    picker.value = '';
  }
}

// ============================================================
// PAGE INIT SELECTION
// ============================================================
if(document.getElementById('gen-title')){
  window.pageInit = async function(){
    let initBarcode = generateRandomEAN13();
    let tryCount = 0;
    while(initBarcode && isDuplicateBarcode(initBarcode,null) && tryCount<100){ initBarcode = generateRandomEAN13(); tryCount++; }
    if(initBarcode){
      currentPreviewCode = initBarcode;
      const bcInput = document.getElementById('m-barcode');
      if(bcInput) bcInput.value = initBarcode;
      renderLivePreview();
    }
  };
} else if(document.getElementById('library-title')){
  window.pageInit = async function(){ renderLibrary(); };
} else if(document.getElementById('print-size-title')){
  window.pageInit = async function(){ renderLabelSizes(); renderPrintQueue(); };
} else if(document.getElementById('users-page-title')){
  window.pageInit = async function(){
    usersData = window.__usersDataFromServer || [];
    renderUsersTable();
    syncCustomSelectLabel('new-role-wrap');
  };
} else if(document.getElementById('categories-settings-title')){
  window.pageInit = async function(){
    const shopEl = document.getElementById('shop-name-setting'); if(shopEl) shopEl.value = shopName;
    const logoEl = document.getElementById('logo-url-setting');  if(logoEl) logoEl.value = logoUrl;
    const prefixEl = document.getElementById('default-prefix');  if(prefixEl) prefixEl.value = defaultPrefix;
    const logoPrevEl = document.getElementById('logo-preview-img'); if(logoPrevEl) logoPrevEl.src = logoUrl;
    renderCategoriesList();
    updateCurrencyPreview();
    updatePriceLabelPreview();
  };
}

// ============================================================
// LOGIN PAGE
// ============================================================
function initLoginPage(){
  window.addEventListener('load', function(){
    const preloader = document.getElementById('login-preloader');
    if(preloader) preloader.classList.add('hidden');
  });

  const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  function updateClock() {
    const now = new Date();
    let h = now.getHours();
    const m = now.getMinutes();
    const s = now.getSeconds();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    const pad = n => String(n).padStart(2, '0');
    const timeEl = document.getElementById('clock-time');
    if(timeEl) timeEl.innerHTML = pad(h) + '<span class="colon">:</span>' + pad(m) +
        '<span style="font-size:0.55em;opacity:0.6;margin-inline-start:4px;">' + pad(s) + '</span>';
    const ampmEl = document.getElementById('clock-ampm'); if(ampmEl) ampmEl.textContent = ampm;
    const dateEl = document.getElementById('clock-date');
    if(dateEl) dateEl.textContent = DAYS[now.getDay()] + ', ' + MONTHS[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
  }
  updateClock();
  setInterval(updateClock, 1000);

  function setGreeting() {
    const h = new Date().getHours();
    let timeText, timeEmoji;
    if (h >= 5 && h < 12) { timeText = 'GOOD MORNING'; timeEmoji = '🌅'; }
    else if (h >= 12 && h < 17) { timeText = 'GOOD AFTERNOON'; timeEmoji = '☀️'; }
    else if (h >= 17 && h < 21) { timeText = 'GOOD EVENING'; timeEmoji = '🌆'; }
    else { timeText = 'GOOD NIGHT'; timeEmoji = '🌙'; }
    const labelEl = document.getElementById('welcome-label');
    if(labelEl) labelEl.textContent = timeEmoji + ' ' + timeText;
    const greetEl = document.getElementById('welcome-greeting');
    if(greetEl) greetEl.innerHTML = 'WELCOME BACK, <span>Friend!</span>';
  }
  setGreeting();

  window.togglePassword = function(){
    const inp = document.getElementById('password');
    const icon = document.querySelector('#toggleIcon i');
    const wrap = document.getElementById('toggleIcon');
    const isPass = inp.type === 'password';
    inp.type = isPass ? 'text' : 'password';
    icon.classList.toggle('fa-eye', !isPass);
    icon.classList.toggle('fa-eye-slash', isPass);
    wrap.classList.toggle('active', isPass);
  };

  const STORAGE_KEY = 'barcode_saved_creds';
  window.handleRemember = function() {
    if (!document.getElementById('rememberMe').checked) {
      localStorage.removeItem(STORAGE_KEY);
    }
  };
  (function loadSaved() {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
      if (saved && saved.u && saved.p) {
        document.getElementById('username').value = saved.u;
        document.getElementById('password').value = saved.p;
        document.getElementById('rememberMe').checked = true;
      }
    } catch(e) {}
  })();

  const form = document.getElementById('loginForm');
  const button = document.getElementById('loginBtn');
  form.addEventListener('submit', function(){
    if (document.getElementById('rememberMe').checked) {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
          u: document.getElementById('username').value,
          p: document.getElementById('password').value
        }));
      } catch(e) {}
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
    button.classList.add('loading');
    button.innerHTML = 'Logging in';
  });

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./service-worker.js').catch(() => {});
  }
}

// ============================================================
// COMMON INIT
// ============================================================
async function init(){
  if(document.getElementById('loginForm')){ initLoginPage(); return; }
  showLoadingPopup();
  updateLoadingMessage('جاري تحميل الإعدادات...', 8);
  applyTheme();
  updateLoadingMessage('جاري تحميل البيانات...', 20);
  await loadFromDB();
  updateLoadingMessage('جاري تحميل اللغة...', 38);
  currentTexts=translations[currentLanguage];
  if(currentLanguage==='ar'){document.body.classList.remove('ltr');document.body.classList.add('rtl');document.documentElement.setAttribute('dir','rtl');}
  else{document.body.classList.remove('rtl');document.body.classList.add('ltr');document.documentElement.setAttribute('dir','ltr');}
  const btnAr=document.getElementById('btn-ar');if(btnAr)btnAr.classList.toggle('active',currentLanguage==='ar');
  const btnEn=document.getElementById('btn-en');if(btnEn)btnEn.classList.toggle('active',currentLanguage==='en');
  updateLoadingMessage('جاري تجهيز الواجهة...', 52);
  const logoEl=document.getElementById('header-logo');
  if(logoEl){logoEl.src=logoUrl;logoEl.onerror=()=>{logoEl.src='image/logo.png';};}
  const shopNameEl2=document.getElementById('header-shop-name');if(shopNameEl2)shopNameEl2.textContent=shopName||currentTexts.shopName;
  const subtitleEl=document.getElementById('header-subtitle');if(subtitleEl)subtitleEl.textContent=currentTexts.subtitle;
  updateLoadingMessage('جاري تحميل التصنيفات...', 65);
  renderCategoryDropdowns();renderCategoriesList();updateCurrencyPreview();updatePriceLabelPreview();renderLabelSizes();renderWeightUnitOptions();
  updateLoadingMessage('جاري الانتهاء...', 85);
  renderPrintQueue();
  if(isAdminUser)renderUsersTable();
  _applyAllLangTexts(currentLanguage);
  updateLoadingMessage('✅ تم التحميل بنجاح!', 100);
  if(typeof window.pageInit==='function'){ await window.pageInit(); }
  setTimeout(function(){ closeLoadingPopup(); }, 300);
}
init();

// ============================================================
// QR CODE PAGE (qrcode.php)
// ============================================================
let currentLogoData = null;
let qrHistory = [];
let qrDataUrl = '';
let currentCornerStyle = 'standard';
let currentTitle = '';
let currentSubtitle = '';

function loadQRHistory() {
  try { qrHistory = JSON.parse(localStorage.getItem('qrHistory')) || []; }
  catch { qrHistory = []; }
  renderQRHistory();
}
function saveQRHistory() {
  localStorage.setItem('qrHistory', JSON.stringify(qrHistory));
  updateQRHistoryCount();
}
function updateQRHistoryCount() {
  const countEl = document.getElementById('qr-history-count');
  if (countEl) countEl.textContent = qrHistory.length;
}
function addToQRHistory(data, color, bgColor, size, dataUrl, title, subtitle, corner) {
  const historyItem = {
    id: Date.now(), data: data, color: color || '#000000', bgColor: bgColor || '#ffffff',
    size: size || 300, timestamp: Date.now(), dataUrl: dataUrl || '',
    title: title || '', subtitle: subtitle || '', corner: corner || 'standard'
  };
  qrHistory.push(historyItem);
  if (qrHistory.length > 50) qrHistory = qrHistory.slice(-50);
  saveQRHistory(); renderQRHistory();
}
function renderQRHistory() {
  const container = document.getElementById('qr-history-list');
  if (!container) return;
  updateQRHistoryCount();
  if (qrHistory.length === 0) {
    container.innerHTML = `<div class="empty-history"><span class="icon">📭</span>No QR Code history found</div>`;
    return;
  }
  const reversed = [...qrHistory].reverse();
  container.innerHTML = reversed.map(item => {
    const date = new Date(item.timestamp);
    const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    return `
      <div class="history-item" onclick="loadQRFromHistory(${item.id})">
        ${item.dataUrl ? `<img src="${item.dataUrl}" alt="QR">` : '<div style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;background:#f0f0f0;border-radius:8px;font-size:32px;">📱</div>'}
        <div class="label">${item.title || item.data.substring(0, 15)}${item.data.length > 15 ? '...' : ''}</div>
        <div class="time">${timeStr}</div>
        <button class="delete-btn" onclick="event.stopPropagation();deleteQRHistoryItem(${item.id})">✕</button>
      </div>
    `;
  }).join('');
}
function loadQRFromHistory(id) {
  const item = qrHistory.find(h => h.id === id);
  if (item) {
    document.getElementById('qr-data').value = item.data;
    document.getElementById('qr-fg-color').value = item.color;
    document.getElementById('qr-fg-color-text').value = item.color;
    document.getElementById('qr-bg-color').value = item.bgColor;
    document.getElementById('qr-bg-color-text').value = item.bgColor;
    document.getElementById('qr-size').value = item.size || 300;
    if (item.title) document.getElementById('qr-title').value = item.title;
    if (item.subtitle) document.getElementById('qr-subtitle').value = item.subtitle;
    if (item.corner) setCornerStyle(item.corner);
    generateQR();
    if (typeof showToast === 'function') showToast('✅ QR Code loaded from history', 'success');
  }
}
function deleteQRHistoryItem(id) {
  if (confirm('Are you sure you want to delete this item?')) {
    qrHistory = qrHistory.filter(item => item.id !== id);
    saveQRHistory(); renderQRHistory();
    if (typeof showToast === 'function') showToast('🗑️ Item deleted', 'warning');
  }
}
function clearQRHistory() {
  if (confirm('Are you sure you want to clear all history?')) {
    qrHistory = []; saveQRHistory(); renderQRHistory();
    if (typeof showToast === 'function') showToast('🗑️ History cleared', 'warning');
  }
}
function updateTitle() {
  const title = document.getElementById('qr-title').value;
  const subtitle = document.getElementById('qr-subtitle').value;
  currentTitle = title; currentSubtitle = subtitle;
  const titleDisplay = document.getElementById('qr-title-display');
  titleDisplay.textContent = title;
  titleDisplay.style.display = title ? 'block' : 'none';
  const subtitleDisplay = document.getElementById('qr-subtitle-display');
  subtitleDisplay.textContent = subtitle;
  subtitleDisplay.style.display = subtitle ? 'block' : 'none';
}
function updateTitleFontSize() {
  const size = document.getElementById('title-font-size').value;
  document.getElementById('title-font-display').textContent = size + 'px';
  document.getElementById('qr-title-display').style.fontSize = size + 'px';
}
function updateSubtitleFontSize() {
  const size = document.getElementById('subtitle-font-size').value;
  document.getElementById('subtitle-font-display').textContent = size + 'px';
  document.getElementById('qr-subtitle-display').style.fontSize = size + 'px';
}
function setCornerStyle(style) {
  currentCornerStyle = style;
  document.querySelectorAll('.corner-preset').forEach(el => {
    el.classList.toggle('active', el.dataset.corner === style);
  });
  const names = { 'standard': 'Standard', 'rounded': 'Rounded', 'circle': 'Circle' };
  document.getElementById('qr-corner-display').textContent = `Corner: ${names[style] || 'Standard'}`;
  generateQR();
}
function generateQR() {
  const data = document.getElementById('qr-data').value.trim();
  const fgColor = document.getElementById('qr-fg-color').value;
  const bgColor = document.getElementById('qr-bg-color').value;
  const size = parseInt(document.getElementById('qr-size').value);
  const errorLevel = document.getElementById('qr-error-level').value;
  const statusEl = document.getElementById('qr-status');
  if (!data) {
    statusEl.textContent = '⚠️ Please enter data';
    statusEl.className = 'qr-status-badge warning';
    document.getElementById('qr-image').style.display = 'none';
    return;
  }
  try {
    const container = document.createElement('div');
    container.id = 'qr-temp-container';
    container.style.display = 'none';
    document.body.appendChild(container);
    container.innerHTML = '';
    const qr = new QRCode(container, {
      text: data, width: size, height: size,
      colorDark: fgColor, colorLight: bgColor,
      correctLevel: getErrorLevel(errorLevel)
    });
    const canvas = container.querySelector('canvas');
    if (canvas) {
      applyCornerStyle(canvas, size);
      if (currentLogoData) addLogoToCanvas(canvas, size);
      const img = document.getElementById('qr-image');
      img.src = canvas.toDataURL('image/png');
      img.style.display = 'block';
      img.style.maxWidth = Math.min(size, 500) + 'px';
      img.style.maxHeight = Math.min(size, 500) + 'px';
      qrDataUrl = canvas.toDataURL('image/png');
      statusEl.textContent = `✅ QR Code ready - ${data.length} characters`;
      statusEl.className = 'qr-status-badge success';
      document.getElementById('qr-version').textContent = `Version: Auto`;
      document.getElementById('qr-level').textContent = `Level: ${errorLevel}`;
      document.getElementById('qr-size-display').textContent = `Size: ${size}px`;
    }
    document.body.removeChild(container);
  } catch (e) {
    console.error('QR Generation Error:', e);
    statusEl.textContent = '❌ Error: ' + e.message;
    statusEl.className = 'qr-status-badge error';
    document.getElementById('qr-image').style.display = 'none';
  }
}
function applyCornerStyle(canvas, size) {
  if (currentCornerStyle === 'standard') return;
  const ctx = canvas.getContext('2d');
  if (currentCornerStyle === 'rounded') return;
  else if (currentCornerStyle === 'circle') {
    ctx.save();
    ctx.globalCompositeOperation = 'destination-in';
    ctx.beginPath();
    ctx.arc(size/2, size/2, size/2 - 5, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }
}
function getErrorLevel(level) {
  switch(level) {
    case 'L': return QRCode.CorrectLevel.L;
    case 'M': return QRCode.CorrectLevel.M;
    case 'Q': return QRCode.CorrectLevel.Q;
    case 'H': return QRCode.CorrectLevel.H;
    default: return QRCode.CorrectLevel.M;
  }
}
function addLogoToCanvas(canvas, size) {
  if (!currentLogoData) return;
  const ctx = canvas.getContext('2d');
  const logoSize = Math.min(size * 0.2, 80);
  const x = (size - logoSize) / 2;
  const y = (size - logoSize) / 2;
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = function() {
    const radius = logoSize / 2 + 8;
    ctx.beginPath();
    ctx.arc(size/2, size/2, radius, 0, Math.PI * 2);
    ctx.fillStyle = '#ffffff';
    ctx.fill();
    ctx.save();
    ctx.beginPath();
    ctx.arc(size/2, size/2, logoSize/2 + 2, 0, Math.PI * 2);
    ctx.clip();
    ctx.drawImage(img, x, y, logoSize, logoSize);
    ctx.restore();
    const imgElement = document.getElementById('qr-image');
    imgElement.src = canvas.toDataURL('image/png');
    qrDataUrl = canvas.toDataURL('image/png');
  };
  img.onerror = function() { console.warn('Logo image failed to load'); };
  img.src = currentLogoData;
}
function uploadLogo(event) {
  const file = event.target.files[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    if (typeof showToast === 'function') showToast('⚠️ Please select a valid image file', 'warning');
    return;
  }
  if (file.size > 2 * 1024 * 1024) {
    if (typeof showToast === 'function') showToast('⚠️ Image is too large (max 2MB)', 'warning');
    return;
  }
  const reader = new FileReader();
  reader.onload = function(e) {
    currentLogoData = e.target.result;
    document.getElementById('remove-logo-btn').style.display = 'block';
    const badge = document.getElementById('logo-badge');
    badge.classList.add('show');
    document.getElementById('logo-badge-img').src = currentLogoData;
    generateQR();
    if (typeof showToast === 'function') showToast('✅ Logo uploaded successfully', 'success');
  };
  reader.readAsDataURL(file);
}
function removeLogo() {
  currentLogoData = null;
  document.getElementById('qr-logo-upload').value = '';
  document.getElementById('remove-logo-btn').style.display = 'none';
  document.getElementById('logo-badge').classList.remove('show');
  generateQR();
  if (typeof showToast === 'function') showToast('🗑️ Logo removed', 'warning');
}
function updateQR() {
  const fgColor = document.getElementById('qr-fg-color').value;
  const bgColor = document.getElementById('qr-bg-color').value;
  document.getElementById('qr-fg-color-text').value = fgColor;
  document.getElementById('qr-bg-color-text').value = bgColor;
  generateQR();
}
function syncColor(type, value) {
  if (type === 'fg') document.getElementById('qr-fg-color').value = value;
  else document.getElementById('qr-bg-color').value = value;
  generateQR();
}
function setQRData(prefix) {
  const textarea = document.getElementById('qr-data');
  textarea.focus();
  textarea.value = prefix;
  generateQR();
}
function resetQR() {
  document.getElementById('qr-data').value = 'https://example.com';
  document.getElementById('qr-fg-color').value = '#000000';
  document.getElementById('qr-fg-color-text').value = '#000000';
  document.getElementById('qr-bg-color').value = '#ffffff';
  document.getElementById('qr-bg-color-text').value = '#ffffff';
  document.getElementById('qr-size').value = '300';
  document.getElementById('qr-error-level').value = 'M';
  document.getElementById('qr-title').value = '';
  document.getElementById('qr-subtitle').value = '';
  document.getElementById('title-font-size').value = '24';
  document.getElementById('title-font-display').textContent = '24px';
  document.getElementById('subtitle-font-size').value = '16';
  document.getElementById('subtitle-font-display').textContent = '16px';
  setCornerStyle('standard');
  removeLogo();
  updateTitle();
  updateTitleFontSize();
  updateSubtitleFontSize();
  generateQR();
  if (typeof showToast === 'function') showToast('🔄 Reset completed', 'info');
}
function printQR() {
  const img = document.getElementById('qr-image');
  if (!img.src || img.style.display === 'none') {
    if (typeof showToast === 'function') showToast('⚠️ Please generate a QR Code first', 'warning');
    return;
  }
  const title = document.getElementById('qr-title').value || '';
  const subtitle = document.getElementById('qr-subtitle').value || '';
  const titleSize = document.getElementById('title-font-size').value || 24;
  const subtitleSize = document.getElementById('subtitle-font-size').value || 16;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html><html><head><title>Print QR Code</title>
    <style>
      body { display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: white; }
      .qr-container { text-align: center; padding: 40px; }
      .qr-title { font-size: ${titleSize}px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
      .qr-image { max-width: 400px; height: auto; display: block; margin: 0 auto; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; background: white; }
      .qr-subtitle { font-size: ${subtitleSize}px; color: #666; margin-top: 15px; }
      @media print { body { margin: 0; padding: 20px; } .qr-container { padding: 20px; } .qr-image { max-width: 350px; } }
    </style></head><body>
    <div class="qr-container">
      ${title ? `<div class="qr-title">${title}</div>` : ''}
      <img src="${img.src}" class="qr-image" alt="QR Code">
      ${subtitle ? `<div class="qr-subtitle">${subtitle}</div>` : ''}
    </div>
    <script>window.onload = function() { window.print(); };<\/script>
    </body></html>
  `);
  printWindow.document.close();
}
function exportPDF() {
  const img = document.getElementById('qr-image');
  if (!img.src || img.style.display === 'none') {
    if (typeof showToast === 'function') showToast('⚠️ Please generate a QR Code first', 'warning');
    return;
  }
  try {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    const title = document.getElementById('qr-title').value || '';
    const subtitle = document.getElementById('qr-subtitle').value || '';
    const titleSize = parseInt(document.getElementById('title-font-size').value) || 24;
    const subtitleSize = parseInt(document.getElementById('subtitle-font-size').value) || 16;
    let yPos = 30;
    if (title) {
      pdf.setFontSize(Math.min(titleSize, 28));
      pdf.setTextColor(26, 26, 46);
      pdf.text(title, 105, yPos, { align: 'center' });
      yPos += Math.min(titleSize, 28) + 10;
    }
    const imgData = img.src;
    const imgWidth = Math.min(130, 200 - yPos);
    const imgHeight = imgWidth;
    const xPos = (210 - imgWidth) / 2;
    pdf.addImage(imgData, 'PNG', xPos, yPos, imgWidth, imgHeight);
    yPos += imgHeight + 10;
    if (subtitle) {
      pdf.setFontSize(Math.min(subtitleSize, 20));
      pdf.setTextColor(102, 102, 102);
      pdf.text(subtitle, 105, yPos, { align: 'center' });
    }
    pdf.save(`qr-code-${Date.now()}.pdf`);
    if (typeof showToast === 'function') showToast('📄 PDF exported successfully', 'success');
  } catch (e) {
    console.error('PDF Export Error:', e);
    if (typeof showToast === 'function') showToast('⚠️ Error exporting PDF: ' + e.message, 'error');
  }
}
function saveQR() {
  const data = document.getElementById('qr-data').value.trim();
  if (!data) {
    if (typeof showToast === 'function') showToast('⚠️ Please generate a QR Code first', 'warning');
    return;
  }
  const fgColor = document.getElementById('qr-fg-color').value;
  const bgColor = document.getElementById('qr-bg-color').value;
  const size = parseInt(document.getElementById('qr-size').value);
  const title = document.getElementById('qr-title').value;
  const subtitle = document.getElementById('qr-subtitle').value;
  const img = document.getElementById('qr-image');
  if (img.src) {
    addToQRHistory(data, fgColor, bgColor, size, img.src, title, subtitle, currentCornerStyle);
    if (typeof showToast === 'function') showToast('💾 QR Code saved to history', 'success');
  }
}
function downloadQR() {
  const img = document.getElementById('qr-image');
  if (!img.src || img.style.display === 'none') {
    if (typeof showToast === 'function') showToast('⚠️ Please generate a QR Code first', 'warning');
    return;
  }
  const link = document.createElement('a');
  link.download = `qr-code-${Date.now()}.png`;
  link.href = img.src;
  link.click();
  if (typeof showToast === 'function') showToast('⬇️ QR Code downloaded', 'success');
}
function copyQR() {
  const img = document.getElementById('qr-image');
  if (!img.src || img.style.display === 'none') {
    if (typeof showToast === 'function') showToast('⚠️ Please generate a QR Code first', 'warning');
    return;
  }
  fetch(img.src).then(res => res.blob()).then(blob => {
    try {
      navigator.clipboard.write([new ClipboardItem({ [blob.type]: blob })])
        .then(function() {
          if (typeof showToast === 'function') showToast('📋 QR Code copied to clipboard', 'success');
        })
        .catch(function() {
          if (typeof showToast === 'function') showToast('⚠️ Failed to copy', 'error');
        });
    } catch(e) {
      if (typeof showToast === 'function') showToast('⚠️ Browser does not support copy', 'error');
    }
  });
}

if(document.getElementById('qr-data')){
  window.pageInit = async function() {
    loadQRHistory();
    setTimeout(generateQR, 500);
  };
}

let qrTimeout;
document.addEventListener('DOMContentLoaded', function() {
  const dataInput = document.getElementById('qr-data');
  if(!dataInput) return;
  dataInput.addEventListener('input', function() {
    clearTimeout(qrTimeout);
    qrTimeout = setTimeout(generateQR, 400);
  });
  document.getElementById('title-font-size').addEventListener('input', updateTitleFontSize);
  document.getElementById('subtitle-font-size').addEventListener('input', updateSubtitleFontSize);
  setTimeout(generateQR, 600);
});

window.generateQR = generateQR;
window.saveQR = saveQR;
window.downloadQR = downloadQR;
window.copyQR = copyQR;
window.printQR = printQR;
window.exportPDF = exportPDF;
window.resetQR = resetQR;
window.updateQR = updateQR;
window.syncColor = syncColor;
window.setQRData = setQRData;
window.uploadLogo = uploadLogo;
window.removeLogo = removeLogo;
window.loadQRFromHistory = loadQRFromHistory;
window.deleteQRHistoryItem = deleteQRHistoryItem;
window.clearQRHistory = clearQRHistory;
window.addToQRHistory = addToQRHistory;
window.renderQRHistory = renderQRHistory;
window.updateTitle = updateTitle;
window.setCornerStyle = setCornerStyle;
window.updateTitleFontSize = updateTitleFontSize;
window.updateSubtitleFontSize = updateSubtitleFontSize;