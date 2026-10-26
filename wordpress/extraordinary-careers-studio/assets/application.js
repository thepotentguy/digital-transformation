/* Private applications prototype. No browser storage of applicant details. */
(() => {
  'use strict';
  const config = window.ecsApplications;
  if (!config) return;
  const el = (tag, text, attrs = {}) => { const n = document.createElement(tag); if (text !== null) n.textContent = text; Object.entries(attrs).forEach(([k,v]) => n.setAttribute(k,v)); return n; };
  const api = async (path, options = {}) => {
    const response = await fetch(config.restUrl + path, {credentials:'same-origin',...options,headers:{...(config.nonce ? {'X-WP-Nonce':config.nonce} : {}),...(options.headers || {})}});
    let data; try { data = await response.json(); } catch { throw new Error('The server returned an unexpected response. Please retry.'); }
    if (!response.ok) throw new Error(data.message || 'The request failed.');
    return data;
  };
  const json = (method, data) => ({method,headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
  if (config.mode === 'public') {
    let settings;
    const getSettings = () => settings || (settings = api('application-settings'));
    document.addEventListener('click', event => {
      const link = event.target.closest('a[href^="mailto:"]');
      if (!link || !link.closest('.ecs-career__apply')) return;
      if (!config.hrEmail || decodeURIComponent(link.getAttribute('href').slice(7).split('?')[0]).toLowerCase() !== config.hrEmail.toLowerCase()) return;
      getSettings().then(s => api(`jobs/${config.jobId}/contact-click`,json('POST',{token:s.token}))).catch(() => {});
    });
    const root = document.getElementById('ecs-application-form');
    if (!root) return;
    getSettings().then(s => {
      if (!s.enabled) { root.append(el('p',s.reason)); return; }
      const form = el('form',null,{'aria-label':'Online career application',class:'ecs-career__application-form'});
      form.append(el('p',s.privacyNotice));
      form.append(el('p','We retain your application for twelve months. Upload a PDF CV no larger than 5 MB.'));
      for (const [name,label,type,required] of [['name','Full name','text',true],['email','Email address','email',true],['phone','Phone number (optional)','tel',false],['cv','PDF CV','file',true]]) {
        const p=el('p',null), l=el('label',label,{for:`ecs-apply-${name}`}), input=el('input',null,{id:`ecs-apply-${name}`,name,type});
        if (required) input.required=true;
        if (name==='cv') input.accept='.pdf,application/pdf';
        else input.maxLength=name==='name'?200:name==='email'?254:80;
        p.append(l,el('br',''),input); form.append(p);
      }
      const trap=el('input',null,{name:'website',type:'text',tabindex:'-1',autocomplete:'off','aria-hidden':'true'}); trap.style.display='none'; form.append(trap);
      const consent=el('label',null,{class:'ecs-career__consent'}), check=el('input',null,{type:'checkbox',name:'consent',value:'1'}); check.required=true; consent.append(check,document.createTextNode(' I have read the privacy notice and consent to processing my application.')); form.append(consent);
      const button=el('button','Submit application',{type:'submit'}), message=el('p','',{role:'status','aria-live':'polite',class:'ecs-career__application-result'}); form.append(el('p',''),button,message); root.append(form);
      const requestKey = window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}_${Math.random().toString(36).slice(2)}_${Math.random().toString(36).slice(2)}`;
      form.addEventListener('submit', async e => {
        e.preventDefault(); if (!form.reportValidity()) return;
        const file=form.elements.cv.files[0]; if (!file || file.size>s.maxBytes || !file.name.toLowerCase().endsWith('.pdf')) { message.textContent='Choose a PDF CV no larger than 5 MB.'; return; }
        button.disabled=true; message.textContent='Submitting securely…';
        const body=new FormData(form); body.set('job_id',String(config.jobId)); body.set('token',s.token); body.set('idempotency_key',requestKey);
        try { const result=await api('apply',{method:'POST',body}); form.reset(); message.textContent=result.message + (result.reference ? ` Reference: ${result.reference}` : ''); button.textContent='Application received'; Array.from(form.elements).forEach(input=>input.disabled=true); }
        catch(error) { message.textContent=error.message; button.disabled=false; }
      });
    }).catch(error=>root.append(el('p',error.message,{role:'alert'})));
    return;
  }

})();
