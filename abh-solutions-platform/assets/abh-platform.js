(function(){
  'use strict';
  document.addEventListener('change', function(event){
    if(event.target && event.target.id === 'abhsp_content_type'){
      var form = event.target.closest('.abhsp-wizard');
      if(form){ form.setAttribute('data-type', event.target.value || ''); }
    }
  });
  document.addEventListener('submit', function(event){
    var form = event.target;
    if(form && form.classList.contains('abhsp-wizard')){
      var type = form.querySelector('#abhsp_content_type');
      if(type && !type.value){ event.preventDefault(); type.focus(); type.setCustomValidity('Bitte wählen Sie zuerst aus, ob Sie ein Fahrzeug oder Aktuelles einstellen möchten.'); type.reportValidity(); }
      else if(type){ type.setCustomValidity(''); }
    }
  });
})();