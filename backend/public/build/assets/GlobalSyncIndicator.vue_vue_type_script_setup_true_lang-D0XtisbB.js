import{c as T}from"./createLucideIcon-eVHMFrjL.js";import{$ as S,k as A,d as L,U as k,g as y,n as w,a as C,u as R,h as b,t as E,a0 as U,y as c,f as m}from"./app-B9qHq_-8.js";/**
 * @license lucide-vue-next v0.400.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const et=T("ShoppingCartIcon",[["circle",{cx:"8",cy:"21",r:"1",key:"jimo8o"}],["circle",{cx:"19",cy:"21",r:"1",key:"13723u"}],["path",{d:"M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12",key:"9zh506"}]]),v="classicpos_printer_config",f={type:"browser",ip_address:"192.168.1.100",port:9100,drawer_pin:2,printer_name:"",device_id:""},p=A(B());function B(){if(typeof localStorage>"u")return{...f};try{const t=localStorage.getItem(v);if(!t)return{...f};const n=JSON.parse(t);return{...f,...n}}catch{return{...f}}}function z(t){if(!(typeof localStorage>"u"))try{localStorage.setItem(v,JSON.stringify(t))}catch(n){console.warn("[Printer] Failed to save to localStorage:",n)}}async function V(t){try{await S.config.put({key:v,value:t,updated_at:Date.now()})}catch(n){console.warn("[Printer] Failed to save to IndexedDB:",n)}}async function N(){try{const t=await S.config.get(v);t!=null&&t.value&&(p.value={...f,...t.value})}catch{}return p.value}async function P(t){p.value={...f,...t},z(p.value),await V(p.value)}function G(){return{config:p,getPrinterConfig:N,setPrinterConfig:P}}function o(t){return new TextEncoder().encode(t)}const d={INIT:new Uint8Array([27,64]),LF:new Uint8Array([10]),CUT:new Uint8Array([29,86,65,3]),OPEN_DRAWER_PIN_2:new Uint8Array([27,112,0,25,250]),OPEN_DRAWER_PIN_5:new Uint8Array([27,112,1,25,250]),BOLD_ON:new Uint8Array([27,69,1]),BOLD_OFF:new Uint8Array([27,69,0]),ALIGN_CENTER:new Uint8Array([27,97,1]),ALIGN_LEFT:new Uint8Array([27,97,0]),ALIGN_RIGHT:new Uint8Array([27,97,2]),DOUBLE_SIZE_ON:new Uint8Array([29,33,17]),NORMAL_SIZE:new Uint8Array([29,33,0])};function H(...t){const n=t.reduce((r,i)=>r+i.length,0),e=new Uint8Array(n);let a=0;for(const r of t)e.set(r,a),a+=r.length;return e}function g(t,n=2){const e=[];e.push(d.INIT),e.push(d.ALIGN_CENTER),e.push(d.BOLD_ON),e.push(o(t.branchName+`
`)),e.push(d.BOLD_OFF),e.push(o(new Date(t.date).toLocaleString()+`
`)),e.push(o(`Invoice: ${t.invoiceNumber}
`)),t.offline&&e.push(o(`*** OFFLINE SALE - PENDING SYNC ***
`)),e.push(o(`--------------------------------
`)),e.push(d.ALIGN_LEFT);for(const r of t.items){const i=`${r.name} x${r.quantity}  ${r.price.toFixed(2)}
`;e.push(o(i))}e.push(o(`--------------------------------
`)),e.push(o(`Subtotal:    ${t.subtotal.toFixed(2)}
`)),t.discount>0&&e.push(o(`Discount:   -${t.discount.toFixed(2)}
`)),e.push(o(`Tax:         ${t.taxAmount.toFixed(2)}
`)),e.push(d.BOLD_ON),e.push(o(`TOTAL:       ${t.total.toFixed(2)}
`)),e.push(d.BOLD_OFF),t.amountTendered!==void 0&&t.changeDue!==void 0&&(e.push(o(`Paid:        ${t.amountTendered.toFixed(2)}
`)),e.push(o(`Change:      ${t.changeDue.toFixed(2)}
`))),e.push(o(`--------------------------------
`)),e.push(d.ALIGN_CENTER),e.push(o(`Payment: ${t.paymentMethod}
`)),t.customerName&&e.push(o(`Customer: ${t.customerName}
`)),e.push(o(`
Thank you for your purchase!
`)),t.efrisFdn&&(e.push(o(`EFRIS FDN: ${t.efrisFdn}
`)),t.efrisVerificationCode&&e.push(o(`Verify: ${t.efrisVerificationCode}
`))),e.push(o(`
`)),e.push(d.CUT);const a=n===5?d.OPEN_DRAWER_PIN_5:d.OPEN_DRAWER_PIN_2;return e.push(a),H(...e)}async function $(t){var i,u;if(!navigator.usb)throw new Error("WebUSB API not available in this browser");const n=await navigator.usb.requestDevice({filters:[{classCode:7}]});if(!n)throw new Error("No USB device selected");await n.open(),n.configuration===null&&await n.selectConfiguration(1);const e=(i=n.configuration)==null?void 0:i.interfaces[0];if(!e)throw new Error("No USB interface found");await n.claimInterface(e.interfaceNumber);const a=(u=e.alternates[0])==null?void 0:u.endpoints.find(s=>s.direction==="out"&&s.type==="bulk");if(!a)throw new Error("No bulk OUT endpoint found on printer");const r=4096;for(let s=0;s<t.length;s+=r){const h=t.slice(s,s+r);await n.transferOut(a.endpointNumber,h)}return await n.close(),!0}async function _(t,n){if(!n.ip_address)throw new Error("Printer IP address not configured");const e=n.port||9100,a=`http://${n.ip_address}:${e}/print`,r=await fetch(a,{method:"POST",body:t,headers:{"Content-Type":"application/octet-stream"}});if(!r.ok&&r.status!==0)throw new Error(`Network printer returned status ${r.status}`);return!0}function x(t){const n=new Date(t.date).toLocaleString(),e=t.items.map(i=>`<tr><td>${l(i.name)}</td><td class="r">${i.quantity}</td><td class="r">${i.price.toFixed(2)}</td><td class="r">${(i.price*i.quantity).toFixed(2)}</td></tr>`).join(""),a=`<!DOCTYPE html>
<html><head><title>Receipt</title>
<style>
    body { font-family: 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; padding: 16px; }
    h2 { text-align: center; margin: 0 0 4px; font-size: 14px; }
    .center { text-align: center; }
    .line { border-top: 1px dashed #999; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 2px 0; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .total-row td { border-top: 1px solid #999; padding-top: 4px; }
    .offline-badge { background: #fef3c7; border: 1px solid #fbbf24; padding: 4px; margin: 4px 0; text-align: center; font-weight: bold; font-size: 10px; }
</style></head><body>
    <h2>${l(t.branchName)}</h2>
    <p class="center">${n}</p>
    <p class="center">${l(t.invoiceNumber)}</p>
    ${t.offline?'<div class="offline-badge">OFFLINE SALE - PENDING SYNC</div>':""}
    <div class="line"></div>
    <table>${e}</table>
    <div class="line"></div>
    <table>
        <tr><td>Subtotal</td><td class="r">${t.subtotal.toFixed(2)}</td></tr>
        ${t.discount>0?`<tr><td>Discount</td><td class="r">-${t.discount.toFixed(2)}</td></tr>`:""}
        <tr><td>Tax</td><td class="r">${t.taxAmount.toFixed(2)}</td></tr>
        <tr class="total-row"><td class="b">Total</td><td class="r b">${t.total.toFixed(2)}</td></tr>
    </table>
    ${t.amountTendered!==void 0&&t.changeDue!==void 0?`
    <div class="line"></div>
    <table>
        <tr><td>Paid</td><td class="r">${t.amountTendered.toFixed(2)}</td></tr>
        <tr><td>Change</td><td class="r">${t.changeDue.toFixed(2)}</td></tr>
    </table>`:""}
    <div class="line"></div>
    <p class="center">${l(t.paymentMethod)}</p>
    ${t.customerName?`<p class="center">Customer: ${l(t.customerName)}</p>`:""}
    <p class="center" style="margin-top:12px;font-size:10px;color:#999;">Thank you for your purchase!</p>
    ${t.efrisFdn?`<div class="line"></div><p class="center" style="font-size:10px;">EFRIS FDN: ${l(t.efrisFdn)}</p>${t.efrisVerificationCode?`<p class="center" style="font-size:10px;">Verify: ${l(t.efrisVerificationCode)}</p>`:""}`:""}
</body></html>`,r=window.open("","_blank","width=400,height=600");return r?(r.document.write(a),r.document.close(),setTimeout(()=>{r.focus(),r.print()},300),!0):(console.warn("[Printer] Popup blocked; cannot print via browser"),!1)}function l(t){const n=document.createElement("div");return n.textContent=t,n.innerHTML}async function M(t){try{const n=t.drawer_pin===5?d.OPEN_DRAWER_PIN_5:d.OPEN_DRAWER_PIN_2;return t.type==="usb"?await $(n):t.type==="network"?await _(n,t):(console.warn("[Printer] Cash drawer requires USB or Network printer (browser mode is receipt-only)"),!1)}catch(n){return console.error("[Printer] Failed to open cash drawer:",n),!1}}async function q(t,n){try{if(n.type==="usb"){const e=g(t,n.drawer_pin);return await $(e)}else if(n.type==="network"){const e=g(t,n.drawer_pin);return await _(e,n)}else return n.type==="browser"?x(t):(console.log("[Printer] Printer disabled; skipping print"),!1)}catch(e){console.error("[Printer] Print failed:",e);try{return x(t)}catch(a){return console.error("[Printer] Browser fallback also failed:",a),!1}}}async function W(t){const n=await N();if(n.type==="disabled")return{drawer:!1,printed:!1};if(n.type==="usb"||n.type==="network"){const a=g(t,n.drawer_pin);let r=!1;return n.type==="usb"?r=await $(a).catch(u=>(console.error("[Printer] USB send failed:",u),!1)):r=await _(a,n).catch(u=>(console.error("[Printer] Network send failed:",u),!1)),r?{drawer:!0,printed:!0}:{drawer:!1,printed:x(t)}}return{drawer:!1,printed:x(t)}}function Y(t){const n=new Date(t.date).toLocaleString(),e=t.items.map(a=>`<tr><td>${l(a.name)}</td><td class="r">${a.quantity}</td><td class="r">${a.price.toFixed(2)}</td><td class="r">${(a.price*a.quantity).toFixed(2)}</td></tr>`).join("");return`<!DOCTYPE html>
<html><head><title>Receipt</title>
<style>
    body { font-family: 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; padding: 16px; }
    h2 { text-align: center; margin: 0 0 4px; font-size: 14px; }
    .center { text-align: center; }
    .line { border-top: 1px dashed #999; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 2px 0; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .total-row td { border-top: 1px solid #999; padding-top: 4px; }
    .offline-badge { background: #fef3c7; border: 1px solid #fbbf24; padding: 4px; margin: 4px 0; text-align: center; font-weight: bold; font-size: 10px; }
</style></head><body>
    <h2>${l(t.branchName)}</h2>
    <p class="center">${n}</p>
    <p class="center">${l(t.invoiceNumber)}</p>
    ${t.offline?'<div class="offline-badge">OFFLINE SALE - PENDING SYNC</div>':""}
    <div class="line"></div>
    <table>${e}</table>
    <div class="line"></div>
    <table>
        <tr><td>Subtotal</td><td class="r">${t.subtotal.toFixed(2)}</td></tr>
        ${t.discount>0?`<tr><td>Discount</td><td class="r">-${t.discount.toFixed(2)}</td></tr>`:""}
        <tr><td>Tax</td><td class="r">${t.taxAmount.toFixed(2)}</td></tr>
        <tr class="total-row"><td class="b">Total</td><td class="r b">${t.total.toFixed(2)}</td></tr>
    </table>
    ${t.amountTendered!==void 0&&t.changeDue!==void 0?`
    <div class="line"></div>
    <table>
        <tr><td>Paid</td><td class="r">${t.amountTendered.toFixed(2)}</td></tr>
        <tr><td>Change</td><td class="r">${t.changeDue.toFixed(2)}</td></tr>
    </table>`:""}
    <div class="line"></div>
    <p class="center">${l(t.paymentMethod)}</p>
    ${t.customerName?`<p class="center">Customer: ${l(t.customerName)}</p>`:""}
    <p class="center" style="margin-top:12px;font-size:10px;color:#999;">Thank you for your purchase!</p>
    ${t.efrisFdn?`<div class="line"></div><p class="center" style="font-size:10px;">EFRIS FDN: ${l(t.efrisFdn)}</p>${t.efrisVerificationCode?`<p class="center" style="font-size:10px;">Verify: ${l(t.efrisVerificationCode)}</p>`:""}`:""}
</body></html>`}const nt=Object.freeze(Object.defineProperty({__proto__:null,ESCPOS:d,buildReceiptBytes:g,buildReceiptHTML:Y,getPrinterConfig:N,openDrawer:M,openDrawerAndPrintReceipt:W,printReceiptOnly:q,setPrinterConfig:P,usePrinterConfig:G},Symbol.toStringTag,{value:"Module"})),j=["title"],Z={class:"relative"},J={key:1,class:"inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold bg-amber-500 text-white"},rt=L({__name:"GlobalSyncIndicator",props:{showLabel:{type:Boolean,default:!0},variant:{default:"full"}},setup(t){const n=t,{isOnline:e}=k(),{pendingSalesCount:a,isSyncing:r,lastSyncAt:i}=U(),u=c(()=>!0),s=c(()=>a.value),h=c(()=>r.value?"bg-blue-500":e.value?s.value>0?"bg-amber-500":"bg-emerald-500":"bg-red-500"),D=c(()=>r.value?"text-blue-700":e.value?s.value>0?"text-amber-700":"text-emerald-700":"text-red-700"),F=c(()=>n.variant==="dot"?"":r.value?"Syncing...":e.value?s.value>0?`${s.value} pending`:"Synced":"Offline"),I=c(()=>(n.variant==="badge","gap-1.5")),O=c(()=>r.value?"Syncing offline data...":e.value?s.value>0?`${s.value} item(s) pending sync`:i.value?`Last synced: ${new Date(i.value).toLocaleString()}`:"All data synced":"You are offline. Data will sync when reconnected.");return(K,Q)=>u.value?(m(),y("div",{key:0,class:w(["flex items-center gap-1.5",I.value]),title:O.value},[C("div",Z,[C("div",{class:w(["w-2 h-2 rounded-full",h.value])},null,2),R(r)?(m(),y("div",{key:0,class:w(["absolute inset-0 w-2 h-2 rounded-full animate-ping opacity-75",h.value])},null,2)):b("",!0)]),F.value?(m(),y("span",{key:0,class:w(["text-xs font-medium",D.value])},E(F.value),3)):b("",!0),s.value>0?(m(),y("span",J,E(s.value>99?"99+":s.value),1)):b("",!0)],10,j)):b("",!0)}});export{nt as P,et as S,rt as _,W as a,N as g,M as o,P as s};
