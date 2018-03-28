function htmlDecode(value){ 
   return jQuery('<div/>').html(value).text(); 
}

function go_back_twopages() {
    window.history.go(-2);
}

function loadCalendar(tableDiv, fullcalendar, htmltable, htmldiv, showlong_events, month, year, cat_chosen, author_chosen, contact_person_chosen, location_chosen, not_cat_chosen,template_chosen,holiday_chosen,weekdays,language) {
         if (fullcalendar === undefined) {
             fullcalendar = 0;
         }

         if (showlong_events === undefined) {
             showlong_events = 0;
         }
         fullcalendar = (typeof fullcalendar == 'undefined')? 0 : fullcalendar;
         showlong_events = (typeof showlong_events == 'undefined')? 0 : showlong_events;
         month = (typeof month == 'undefined')? 0 : month;
         year = (typeof year == 'undefined')? 0 : year;
         cat_chosen = (typeof cat_chosen == 'undefined')? '' : cat_chosen;
         not_cat_chosen = (typeof not_cat_chosen == 'undefined')? '' : not_cat_chosen;
         author_chosen = (typeof author_chosen == 'undefined')? '' : author_chosen;
         contact_person_chosen = (typeof contact_person_chosen == 'undefined')? '' : contact_person_chosen;
         location_chosen = (typeof location_chosen == 'undefined')? '' : location_chosen;
         template_chosen = (typeof template_chosen == 'undefined')? 0 : template_chosen;
         holiday_chosen = (typeof template_chosen == 'undefined')? 0 : holiday_chosen;
         weekdays = (typeof weekdays == 'undefined')? '' : weekdays;
         jQuery.post(self.location.href, {
            eme_ajaxCalendar: 'true',
            calmonth: parseInt(month,10),
            calyear: parseInt(year,10),
            full : fullcalendar,
            long_events: showlong_events,
            htmltable: htmltable,
            htmldiv: htmldiv,
            category: cat_chosen,
            notcategory: not_cat_chosen,
            author: author_chosen,
            contact_person: contact_person_chosen,
            location_id: location_chosen,
            template_id: template_chosen,
            holiday_id: holiday_chosen,
            weekdays: weekdays,
            lang: language,
         }, function(data){
            tableDiv.replaceWith(data);
         });
      }
jQuery(document).ready( function($) {
   // Managing bookings delete operations 

   function eme_calc_price_json() {
      var alldata = $('#eme-rsvp-form').serializeArray();
      alldata.push({name: 'eme_override_eventAction', value: 'calc_price'});
      jQuery('span#eme_calc_price').html('<img src="'+emebasic.translate_plugin_url+'images/spinner.gif">');
      jQuery.post(self.location.href, alldata, function(data){
         jQuery('span#eme_calc_price').html(data.total);
      }, "json");
   }

   var timer;
   var delay = 600; // 0.6 seconds delay after last input
   jQuery('input.seatsordiscount').on('input',function() { 
        window.clearTimeout(timer);
        timer = window.setTimeout(function(){
           eme_calc_price_json();
        }, delay);
   });
   jQuery('select.seatsordiscount').change(function() {
      eme_calc_price_json();
   });

   // now calculate the price, but only do it if we have a "full" form
   // during rsvp double-posting the form gets reposted and this would otherwise also trigger
   if (jQuery('span#eme_calc_price').length) {
      eme_calc_price_json();
   }
});

