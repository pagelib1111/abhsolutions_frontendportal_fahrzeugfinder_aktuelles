(function(){
  'use strict';
  function updatePreview(form){
    var title=form.querySelector('#abhsp_title');
    var summary=form.querySelector('#abhsp_summary');
    var titleTarget=form.querySelector('[data-preview-title]');
    var summaryTarget=form.querySelector('[data-preview-summary]');
    var facts=form.querySelector('[data-preview-facts]');
    if(titleTarget) titleTarget.textContent=title&&title.value?title.value:'Ihr Titel erscheint hier';
    if(summaryTarget) summaryTarget.textContent=summary&&summary.value?summary.value:'Ihre Kurzbeschreibung erscheint hier.';
    if(facts){
      var type=form.querySelector('#abhsp_content_type');
      var names=type&&type.value==='vehicle'?['vehicle_type','body_type','base_vehicle','location']:['news_kind','event_date','event_location'];
      var values=names.map(function(name){
        var field=form.querySelector('[name="'+name+'"]');
        if(!field||field.disabled||!field.value) return '';
        if(name==='news_kind'){
          var option=field.options[field.selectedIndex];
          return option?option.text:'';
        }
        return field.value;
      }).filter(Boolean);
      facts.textContent=values.join(' · ');
    }
    var typeNode=form.querySelector('[data-preview-type]');
    var selector=form.querySelector('#abhsp_content_type');
    if(typeNode&&selector) typeNode.textContent=selector.value==='vehicle'?'FAHRZEUG':'AKTUELLES';
  }
  function setStep(form,step,moveFocus){
    var current=Math.max(1,Math.min(3,step));
    form.setAttribute('data-step',String(current));
    form.querySelectorAll('[data-step-panel]').forEach(function(panel){
      panel.hidden=Number(panel.getAttribute('data-step-panel'))!==current;
    });
    var fill=form.querySelector('.abhsp-progress-fill');
    var label=form.querySelector('.abhsp-progress-label');
    var labels=['Inhalt auswählen','Informationen ergänzen','Prüfen und einreichen'];
    if(fill) fill.style.width=((current/3)*100)+'%';
    if(label) label.textContent='Schritt '+current+' von 3 · '+labels[current-1];
    var back=form.querySelector('[data-abh-back]');
    var next=form.querySelector('[data-abh-next]');
    var submit=form.querySelector('.abhsp-step-submit');
    if(back) back.hidden=current===1;
    if(next) next.hidden=current===3;
    if(submit) submit.hidden=current!==3;
    if(current===3) updatePreview(form);
    var heading=form.querySelector('[data-step-panel="'+current+'"] h3');
    if(heading&&moveFocus){heading.setAttribute('tabindex','-1');heading.focus();}
  }
  function setType(form,type){
    form.setAttribute('data-type',type||''); var hiddenType=form.querySelector('input[name="content_type"]'); if(hiddenType) hiddenType.value=type||'';
    form.querySelectorAll('.abhsp-fieldset').forEach(function(group){
      var active=!!type&&group.classList.contains(type==='vehicle'?'abhsp-vehicle-fields':'abhsp-news-fields');
      group.disabled=!active;
    });
    updatePreview(form);
  }
  function readForm(form){
    var data={};
    form.querySelectorAll('input[name],textarea[name],select[name]').forEach(function(field){
      if(field.type==='file'||field.type==='hidden'||field.name.indexOf('nonce')!==-1||field.name==='privacy'||field.name==='abhsp_portal_submit') return;
      data[field.name]=field.type==='checkbox'?field.checked:field.value;
    });
    return data;
  }
  function saveDraft(form){
    var key=form.getAttribute('data-draft-key');
    if(!key) return;
    var data=readForm(form);
    if(!data.title&&!data.summary&&!data.details) return;
    try{
      localStorage.setItem(key,JSON.stringify({savedAt:Date.now(),fields:data}));
      var status=form.querySelector('[data-abh-autosave-status]');
      if(status) status.textContent='Entwurf lokal gespeichert · '+new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'});
    }catch(error){
      var status=form.querySelector('[data-abh-autosave-status]');
      if(status) status.textContent='Automatisches Speichern ist in diesem Browser nicht verfügbar.';
    }
  }
  function scheduleSave(form){
    window.clearTimeout(form._abhspSaveTimer);
    form._abhspSaveTimer=window.setTimeout(function(){saveDraft(form);},700);
  }
  function applyDraft(form,fields){
    Object.keys(fields||{}).forEach(function(name){
      var field=form.querySelector('[name="'+name+'"]');
      if(!field||field.type==='file') return;
      if(field.type==='checkbox') field.checked=!!fields[name];
      else field.value=fields[name]||'';
    });
    var selector=form.querySelector('#abhsp_content_type');
    if(selector){setType(form,selector.value);}
    updatePreview(form);
  }
  document.addEventListener('click',function(event){
    var next=event.target.closest('[data-abh-next]');
    var back=event.target.closest('[data-abh-back]');
    if(!next&&!back) return;
    var form=event.target.closest('.abhsp-wizard');
    if(!form) return;
    if(back){setStep(form,Number(form.getAttribute('data-step'))-1,true);return;}
    var current=Number(form.getAttribute('data-step'));
    var panel=form.querySelector('[data-step-panel="'+current+'"]');
    if(panel){
      var required=panel.querySelectorAll('[required]');
      for(var i=0;i<required.length;i++){
        if(!required[i].checkValidity()){required[i].reportValidity();return;}
      }
    }
    setStep(form,current+1,true);
  });
  document.addEventListener('change',function(event){
    var form=event.target&&event.target.closest('.abhsp-wizard');
    if(!form) return;
    if(event.target.id==='abhsp_content_type'){
      event.target.setCustomValidity('');
      setType(form,event.target.value);
    } else updatePreview(form);
  });
  document.addEventListener('input',function(event){
    var form=event.target&&event.target.closest('.abhsp-wizard');
    if(form){updatePreview(form);scheduleSave(form);}
  });
  document.addEventListener('change',function(event){
    var form=event.target&&event.target.closest('.abhsp-wizard');
    if(form&&event.target.type!=='file') scheduleSave(form);
    if(form&&event.target.type==='file'){
      var preview=form.querySelector('[data-abh-image-preview]');
      var file=event.target.files&&event.target.files[0];
      if(preview){preview.textContent='';if(file&&file.type.indexOf('image/')===0){var image=document.createElement('img');image.alt='Vorschau des Fahrzeugbildes';image.src=URL.createObjectURL(file);preview.appendChild(image);}}
    }
  });
  document.addEventListener('click',function(event){
    var suggest=event.target.closest('[data-abh-suggest-copy]');
    var restore=event.target.closest('[data-abh-restore-draft]');
    var clear=event.target.closest('[data-abh-clear-draft]');
    var form=event.target.closest('.abhsp-wizard');
    if(suggest&&form){
      var type=form.querySelector('#abhsp_content_type').value;
      var title=form.querySelector('[name="title"]').value.trim();
      var summary=form.querySelector('[name="summary"]');
      if(type==='vehicle'){
        var body=form.querySelector('[name="body_type"]').value.trim();
        var base=form.querySelector('[name="base_vehicle"]').value.trim();
        var use=form.querySelector('[name="application"]').value.trim();
        var pieces=[];if(body) pieces.push(body);if(base) pieces.push('auf Basis '+base);
        summary.value='Fahrzeuglösung'+(pieces.length?' mit '+pieces.join(' '):'')+(use?'. Einsatzbereich: '+use:'. Für den professionellen Einsatz konzipiert.');
      }else{
        var kind=form.querySelector('[name="news_kind"]');var kindText=kind&&kind.options[kind.selectedIndex]?kind.options[kind.selectedIndex].text:'Neuigkeit';
        var date=form.querySelector('[name="event_date"]').value;var location=form.querySelector('[name="event_location"]').value.trim();
        var when=date?new Date(date+'T12:00:00').toLocaleDateString('de-DE'):'';
        summary.value=(title?'Informationen zu „'+title+'“. ':'')+'Thema: '+kindText+(when?' · Termin: '+when:'')+(location?' · Ort: '+location:'');
      }
      summary.dispatchEvent(new Event('input',{bubbles:true}));
    }
    if(restore&&form){
      try{var saved=JSON.parse(localStorage.getItem(form.getAttribute('data-draft-key'))||'{}');applyDraft(form,saved.fields||{});restore.hidden=true;var status=form.querySelector('[data-abh-autosave-status]');if(status)status.textContent='Gespeicherter Entwurf geladen.';}catch(error){}
    }
    if(clear&&form){
      try{localStorage.removeItem(form.getAttribute('data-draft-key'));}catch(error){}
      var status=form.querySelector('[data-abh-autosave-status]');if(status)status.textContent='Lokaler Entwurf wurde verworfen.';
    }
  });
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('[data-abh-dropzone]').forEach(function(zone){
      ['dragenter','dragover'].forEach(function(name){zone.addEventListener(name,function(event){event.preventDefault();zone.classList.add('is-dragging');});});
      ['dragleave','drop'].forEach(function(name){zone.addEventListener(name,function(event){event.preventDefault();zone.classList.remove('is-dragging');});});
      zone.addEventListener('drop',function(event){var input=zone.querySelector('input[type="file"]');var files=event.dataTransfer&&event.dataTransfer.files;if(!input||!files||!files.length)return;try{input.files=files;input.dispatchEvent(new Event('change',{bubbles:true}));}catch(error){zone.querySelector('span').textContent='Bitte wählen Sie die Datei über den Dialog aus.';}});
    });
    document.querySelectorAll('.abhsp-wizard').forEach(function(form){
      var prefill=form.getAttribute('data-prefill');
      if(prefill){
        try{
          var values=JSON.parse(prefill);
          Object.keys(values).forEach(function(key){
            if(key==='content_type'){
              var selector=form.querySelector('#abhsp_content_type');
              if(selector) selector.value=values[key];
              var hidden=form.querySelector('input[name="content_type"]');
              if(hidden) hidden.value=values[key];
              return;
            }
            var field=form.querySelector('[name="'+key+'"]') || form.querySelector('#abhsp_'+key);
            if(field&&field.type==='checkbox') field.checked=!!values[key];
            else if(field&&field.type!=='file') field.value=values[key]||'';
          });
        }catch(error){ /* keep the form usable with empty defaults */ }
      }
      var type=form.querySelector('#abhsp_content_type');
      if(type) setType(form,type.value);
      setStep(form,1);
      try{
        var key=form.getAttribute('data-draft-key');var saved=key?JSON.parse(localStorage.getItem(key)||'null'):null;
        if(new URLSearchParams(window.location.search).get('abhsp_sent')==='1'&&key){localStorage.removeItem(key);saved=null;}
        if(saved&&saved.fields){var restore=form.querySelector('[data-abh-restore-draft]');if(restore)restore.hidden=false;var status=form.querySelector('[data-abh-autosave-status]');if(status)status.textContent='Ein lokaler Entwurf vom '+new Date(saved.savedAt).toLocaleString('de-DE')+' ist verfügbar.';}
      }catch(error){}
    });
  });
  document.addEventListener('submit',function(event){
    var form=event.target;
    if(!form||!form.classList.contains('abhsp-wizard')) return;
    var type=form.querySelector('#abhsp_content_type');
    if(!type||!type.value){
      event.preventDefault();
      setStep(form,1,true);
      if(type){type.focus();type.setCustomValidity('Bitte wählen Sie zuerst Fahrzeug oder Aktuelles aus.');type.reportVal