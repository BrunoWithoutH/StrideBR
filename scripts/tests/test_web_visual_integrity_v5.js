'use strict'
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm')
const listeners={document:{},window:{}},timers=[]
class HTMLFormElement { constructor(){this.target=''} hasAttribute(){return false} }
const indicator={hidden:true,setAttribute(){},innerHTML:''}
const document={body:{append(){}},createElement(){return indicator},addEventListener(n,f){listeners.document[n]=f}}
const window={location:{href:'http://local/current',origin:'http://local'},addEventListener(n,f){listeners.window[n]=f},setTimeout(f){timers.push(f);return timers.length},clearTimeout(){}}
const context={window,document,HTMLFormElement,URL,console};vm.runInNewContext(fs.readFileSync('public/assets/js/page-loading.js','utf8'),context)
const flush=()=>{while(timers.length)timers.shift()()}
const link={target:'',href:'http://local/next',hasAttribute:()=>false,getAttribute:n=>n==='href'?'/next':''}
let event={defaultPrevented:false,button:0,metaKey:false,ctrlKey:false,shiftKey:false,altKey:false,target:{closest:()=>link}}
listeners.document.click(event);flush();assert.equal(indicator.hidden,false,'native link shows loading')
listeners.window.pageshow();assert.equal(indicator.hidden,true,'pageshow resets loading')
event={...event,defaultPrevented:true};listeners.document.click(event);flush();assert.equal(indicator.hidden,true,'prevented click does not show loading')
const form=new HTMLFormElement();event={defaultPrevented:false,target:form};listeners.document.submit(event);flush();assert.equal(indicator.hidden,false,'native submit shows loading')
listeners.window.pageshow();event={defaultPrevented:false,target:form};listeners.document.submit(event);event.defaultPrevented=true;flush();assert.equal(indicator.hidden,true,'later submit listener can prevent global loading')
listeners.document.submit(event);flush();assert.equal(indicator.hidden,true,'AJAX submit remains local')
console.log('✓ Web Visual Integrity V5 page-loading lifecycle')
