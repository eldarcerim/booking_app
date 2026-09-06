(function () {
  'use strict';

  function init(root) {
    var config;
    try { config = JSON.parse(root.getAttribute('data-most-config') || '{}'); }
    catch (e) { return; }
    var strings = config.strings || {};
    var calendar = root.querySelector('[data-most-calendar]');
    var monthLabel = root.querySelector('[data-most-month]');
    var weekdays = root.querySelector('[data-most-weekdays]');
    var slotsWrap = root.querySelector('[data-most-slots-wrap]');
    var slotEmpty = root.querySelector('[data-most-slot-empty]');
    var slotsEl = root.querySelector('[data-most-slots]');
    var dateLabel = root.querySelector('[data-most-selected-date]');
    var form = root.querySelector('[data-most-form]');
    var success = root.querySelector('[data-most-success]');
    var message = root.querySelector('[data-most-message]');
    var summary = root.querySelector('[data-most-summary]');
    var emailInput = form.querySelector('[name="email"]');
    var reminderInput = form.querySelector('[name="reminder_user"]');
    var view = new Date(config.today + 'T12:00:00');
    view.setDate(1);
    var cache = {};

    strings.weekdays.forEach(function (day) {
      var el = document.createElement('span'); el.textContent = day; weekdays.appendChild(el);
    });

    function post(data) {
      data.action = data.action || 'most_month_availability';
      data.nonce = config.nonce;
      return fetch(config.ajaxUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams(data).toString()
      }).then(function (r) { return r.json(); });
    }

    function key() { return view.getFullYear() + '-' + String(view.getMonth() + 1).padStart(2, '0'); }
    function iso(y, m, d) { return y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0'); }
    function pretty(date) {
      var value = new Date(date + 'T12:00:00');
      if (config.language === 'en') {
        return strings.day_names[value.getDay()] + ', ' + strings.month_names[value.getMonth()] + ' ' + value.getDate() + ', ' + value.getFullYear();
      }
      return strings.day_names[value.getDay()] + ', ' + value.getDate() + '. ' + strings.month_names[value.getMonth()] + ' ' + value.getFullYear() + '.';
    }

    function syncReminderChoice() {
      if (!emailInput || !reminderInput) return;
      reminderInput.disabled = !emailInput.value.trim();
      if (reminderInput.disabled) reminderInput.checked = false;
    }

    function loadMonth() {
      calendar.innerHTML = '<div class="most-loading">' + strings.loading + '</div>';
      monthLabel.textContent = strings.months[view.getMonth()] + ' ' + view.getFullYear();
      var k = key();
      var request = cache[k] ? Promise.resolve(cache[k]) : post({year:view.getFullYear(), month:view.getMonth()+1, language:config.language}).then(function (json) {
        if (!json.success) throw new Error(json.data && json.data.message || strings.generic_error);
        cache[k] = json.data.days; return cache[k];
      });
      request.then(renderMonth).catch(function () { calendar.innerHTML = '<p class="most-error">' + strings.calendar_error + '</p>'; });
    }

    function renderMonth(days) {
      calendar.innerHTML = '';
      var y = view.getFullYear(), m = view.getMonth();
      var first = new Date(y, m, 1).getDay();
      var offset = first === 0 ? 6 : first - 1;
      for (var i=0;i<offset;i++) { var blank=document.createElement('span'); blank.className='most-day-blank'; calendar.appendChild(blank); }
      var count = new Date(y, m+1, 0).getDate();
      for (var d=1;d<=count;d++) {
        (function(day){
          var date=iso(y,m,day), info=days[date] || {available:0,total:0};
          var btn=document.createElement('button'); btn.type='button'; btn.className='most-day'; btn.textContent=day;
          if (date === config.today) btn.classList.add('is-today');
          if (!info.total || !info.available) { btn.classList.add('is-full'); btn.disabled=true; btn.setAttribute('aria-label', pretty(date) + ', ' + strings.unavailable.toLowerCase()); }
          else { btn.classList.add(info.available === info.total ? 'is-free' : 'is-partial'); btn.setAttribute('aria-label', pretty(date) + ', ' + info.available + ' ' + strings.available_count); btn.addEventListener('click', function(){ selectDate(date, info, btn); }); }
          calendar.appendChild(btn);
        })(d);
      }
    }

    function selectDate(date, info, button) {
      root.querySelectorAll('.most-day.is-selected').forEach(function(el){el.classList.remove('is-selected');});
      button.classList.add('is-selected'); slotEmpty.hidden=true; slotsWrap.hidden=false; dateLabel.textContent=pretty(date); slotsEl.innerHTML=''; form.hidden=true;
      info.slots.forEach(function(slot){
        var btn=document.createElement('button'); btn.type='button'; btn.className='most-slot';
        var time=document.createElement('span'); time.textContent=slot.time; btn.appendChild(time);
        if (!slot.available) {
          var status=document.createElement('small'); status.textContent=strings.busy; btn.appendChild(status);
          btn.classList.add('is-unavailable'); btn.disabled=true; btn.setAttribute('aria-label', slot.time + ', ' + strings.busy.toLowerCase());
        } else {
          btn.setAttribute('aria-label', slot.time + ', ' + strings.free);
          btn.addEventListener('click', function(){
            root.querySelectorAll('.most-slot.is-selected').forEach(function(el){el.classList.remove('is-selected');}); btn.classList.add('is-selected');
            form.elements.date.value=date; form.elements.time.value=slot.time; summary.textContent=pretty(date) + (config.language === 'en' ? ' at ' : ' u ') + slot.time; form.hidden=false; form.scrollIntoView({behavior:'smooth',block:'start'});
          });
        }
        slotsEl.appendChild(btn);
      });
    }

    root.querySelector('[data-most-prev]').addEventListener('click', function(){
      var now=new Date(config.today+'T12:00:00');
      if (view.getFullYear()===now.getFullYear() && view.getMonth()===now.getMonth()) return;
      view.setMonth(view.getMonth()-1); loadMonth();
    });
    root.querySelector('[data-most-next]').addEventListener('click', function(){ view.setMonth(view.getMonth()+1); loadMonth(); });
    root.querySelector('[data-most-change]').addEventListener('click', function(){ form.hidden=true; slotsWrap.scrollIntoView({behavior:'smooth'}); });
    root.querySelector('[data-most-new]').addEventListener('click', function(){ success.hidden=true; root.querySelector('.most-booking-layout').hidden=false; cache={}; loadMonth(); });
    if (emailInput) emailInput.addEventListener('input', syncReminderChoice);
    syncReminderChoice();

    form.addEventListener('submit', function(e){
      e.preventDefault(); message.textContent='';
      if (!form.checkValidity()) { form.reportValidity(); return; }
      var submit=form.querySelector('[type="submit"]'); submit.disabled=true; submit.textContent=strings.sending;
      var data={action:'most_submit_booking'}; new FormData(form).forEach(function(v,k){data[k]=v;});
      post(data).then(function(json){
        if (!json.success) throw new Error(json.data && json.data.message || strings.send_error);
        form.reset(); syncReminderChoice(); form.hidden=true; slotsWrap.hidden=true; root.querySelector('.most-booking-layout').hidden=true; success.hidden=false; success.scrollIntoView({behavior:'smooth',block:'center'});
      }).catch(function(err){ message.textContent=err.message; cache={}; loadMonth(); }).finally(function(){submit.disabled=false;submit.textContent=strings.send_request;});
    });
    loadMonth();
  }
  document.addEventListener('DOMContentLoaded', function(){ document.querySelectorAll('[data-most-booking]').forEach(init); });
})();
