'use strict';
document.querySelectorAll('[data-error-back]').forEach(button=>{button.addEventListener('click',()=>{if(window.history.length>1)window.history.back();else window.location.href='/'})});
document.querySelectorAll('[data-error-retry]').forEach(button=>{button.addEventListener('click',()=>window.location.reload())});
