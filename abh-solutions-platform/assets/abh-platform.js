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
    if(form) updatePreview(form);
  });
  document.addEventListener('DOMContentLoaded',function(){
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
    });
  });
  document.addEventListener('submit',function(event){
    var form=event.target;
    if(!form||!form.classList.contains('abhsp-wizard')) return;
    var type=form.querySelector('#abhsp_content_type');
    if(!type||!type.value){
      event.preventDefault();
      setStep(form,1,true);
      if(type){type.focus();type.setCustomValidity('Bitte wählen Sie zuerst Fahrzeug oder Aktuelles aus.');type.reportValidity();}
    }
  });
})();